# ADR-0018: Termination Workflow

| Field | Value |
|---|---|
| Status | Accepted |
| Decided | 2026-05-21 |
| Owner | Product (David) + Engineering |
| Supersedes | — (refines `termination` entry in `20-domain/workflows.md`) |
| Superseded by | — |

## Context

Termination touches many concerns across the system:

- **Person lifecycle:** status transition through `pending_termination` → `terminated`
- **Inventory:** equipment recovery via `equipment_assignments` (per ADR-0012)
- **Payroll:** final-check consolidation of `contractor_charge_schedules` with cap-at-zero (per ADR-0014)
- **PTO** (W-2 only): forfeit unused balance, no payout (per ADR-0016)
- **Work orders:** closed at effective date
- **Time entries:** any open clock-in auto-closed at effective date
- **Pending workflows:** PTO requests, etc. initiated by terminated person are auto-cancelled
- **Physical office tasks:** Front Desk handles file move + equipment recovery (per ADR-0013)
- **External systems:** QuickBooks removal happens 7 days after termination (so final paycheck can process)

A single coherent workflow needs to orchestrate all of this, work for both contractors and W-2 staff, and support voluntary/involuntary cases with the same shape.

## Decision

A **single `termination` workflow type** with conditional steps based on the person's role (contractor vs W-2). Initiated by Recruiter (own contractors) or HR (anyone), with the following design.

### Trigger sources

- **Recruiter** — for contractors at their assigned properties only
- **HR** — for any contractor or W-2 staff
- **Admin / Super Admin** — for anyone

### Form fields

| Field | Type | Required | Notes |
|---|---|---|---|
| `subject_person_id` | FK → people | yes | Who is being terminated |
| `effective_date` | date | yes | Defaults to today; can be forward (notice period) or backward (post-hoc) |
| `termination_type` | enum | yes | `voluntary_resignation` / `involuntary_termination` / `end_of_assignment` / `abandonment` / `mutual_agreement` |
| `reason_category` | enum | yes | `performance` / `misconduct` / `attendance` / `personal` / `end_of_need` / `no_call_no_show` / `other` |
| `notes` | text | yes | Free-text context |
| `rehireable` | bool | yes | Yes/No — affects future hiring eligibility |

### Effective date logic

Three scenarios:

| Effective date | Behavior |
|---|---|
| **Today** | Status flips to `pending_termination` immediately on initiation. Downstream cleanup starts. |
| **Forward (notice period)** | Workflow created. Person stays in their current active status. A scheduled job (`ProcessScheduledTerminations`) advances status on the effective date. Person continues working until then. |
| **Backward (post-hoc, e.g. "walked off Monday, entering Wednesday")** | Status flips to `pending_termination` immediately. Activity log records the effective vs initiation discrepancy. Final paycheck logic uses effective date for cutoff. |

### Status transitions

A new status is added to `people`: **`pending_termination`**.

```
contractor_active ──┐                              
                     ├──→ pending_termination ──→ terminated
contractor_inactive ─┤                              
                     │
staff_active ────────┤
                     │
staff_inactive ──────┘

(Or reverse path if termination workflow is cancelled — super_admin only)
```

**`pending_termination` semantics:** the person is being terminated but the file-move step isn't complete yet. They cannot:
- Be on new work orders
- Clock in (QR or tablet)
- Submit new PTO requests
- Initiate new workflows
- Be assigned to new property assignments

They can still:
- View their own profile, hours, paychecks (read-only)
- Be referenced in historical data (timesheets, invoices, etc.)

**Status change moments:**

| Moment | Status |
|---|---|
| Workflow initiation + effective date is today or earlier | `pending_termination` |
| Workflow initiation + effective date is forward | No status change yet (scheduled) |
| Scheduled job arrives at forward effective date | `pending_termination` |
| Front Desk completes file-move step | `terminated` (with `terminated_at` = now) |
| Workflow cancelled before file-move (super_admin only) | Reverts to prior status |

### Workflow steps

The workflow definition includes these steps, executed in order:

```json
{
  "slug": "termination",
  "steps": [
    {
      "type": "action",
      "name": "Initialize termination",
      "actor": "system",
      "effect": "transition_status_to_pending_termination (if effective_date <= today)"
    },
    {
      "type": "action",
      "name": "Remove from active rosters",
      "actor": "system",
      "effect": "remove_from_property_assignments + close_active_work_orders_at_effective_date"
    },
    {
      "type": "action",
      "name": "Auto-close open clock-in",
      "actor": "system",
      "effect": "close_any_open_time_entry_using_effective_date_or_now"
    },
    {
      "type": "action",
      "name": "Auto-cancel pending workflows by this person",
      "actor": "system",
      "effect": "cancel_all_pending_workflows_initiated_by_subject"
    },
    {
      "type": "action",
      "name": "Recover Equipment",
      "assigned_role": "front_desk",
      "fallback_role": "office_manager",
      "subform": "equipment_recovery"
    },
    {
      "type": "action",
      "name": "Move physical file to HR retention",
      "assigned_role": "front_desk",
      "fallback_role": "office_manager",
      "effect_on_completion": "transition_status_to_terminated + set_terminated_at"
    },
    {
      "type": "action",
      "name": "Process final paycheck",
      "assigned_role": "payroll",
      "effect": "consolidate_contractor_charge_schedules + apply_cap_at_zero + handle_pto_forfeit_if_w2"
    },
    {
      "type": "action",
      "name": "Remove from QuickBooks",
      "assigned_role": "payroll",
      "delay_days": 7,
      "delay_anchor": "after_final_paycheck_processed"
    }
  ]
}
```

### Conditional behavior (contractor vs W-2)

Same workflow handles both. Conditional logic at runtime:

| Step | Contractor | W-2 |
|---|---|---|
| Remove from rosters | Close active work_orders | Remove from property_assignments (if recruiter / PM) |
| Open clock-in | Auto-close any open time_entry | (W-2 don't clock-in this way, but field_visits get auto-closed if any are open) |
| Equipment recovery | Only if contractor has equipment_assignments (unusual) | Always — laptops, etc. |
| File move | Yes (contractor application + onboarding docs) | Yes (HR personnel file) |
| Final paycheck — contractor_charge_schedules | Yes — consolidate via ADR-0014 cap-at-zero | N/A (no charge schedules) |
| Final paycheck — PTO | N/A (contractors don't get PTO) | Forfeit balance per ADR-0016, no payout |
| Final paycheck — final hours | Last week's timesheet stays with property, billed normally | N/A (W-2 salaried/hourly tracked differently) |
| QuickBooks removal | Yes | Yes |

### Auto-cancellations on initiation

When the termination workflow's "Initialize" step runs (effective date arrives):

- **All pending workflows initiated by this person** are auto-cancelled with cancel_reason = "Initiator was terminated"
  - PTO requests (pending)
  - Supply requests (pending, if any awaiting Admin approval — those they submitted)
  - Pay-increase requests they may have initiated
- **Any open `field_visit`s** are auto-closed with `was_late_close = true`
- **Any open `time_entry`** is auto-closed using effective_date as end time
- **Active `contractor_charge_schedules`** stay active until final paycheck step (no immediate cancellation; final-check consolidation handles them)

### Workflow cancellation (rare, super_admin only)

Termination workflows can be cancelled BEFORE the file-move step completes:

- Permission: super_admin only
- Cancellation reverts person status to prior state (`contractor_active`, `staff_active`, etc.)
- Auto-cancelled pending workflows are **NOT auto-restored** — they stay cancelled. Person must resubmit if needed.
- Auto-closed time_entries can be restored manually by super_admin if explicit (e.g. "Maria didn't actually walk off, restore her clock-in")
- Closed work_orders can be reopened by super_admin
- Activity log captures the cancellation with required reason

After file-move completes, status is `terminated`. Reversing is no longer a workflow cancellation — it's a rehire, which is a different workflow.

### Final paycheck handling

When the "Process final paycheck" step is reached, the system:

1. Identifies the final `payroll_period` (per ADR-0014 — mid-week vs post-close logic)
2. **For contractors:**
   - Sums remaining entries across all active `contractor_charge_schedules`
   - Consolidates into a single `time_entry_adjustment` on the final period
   - Applies cap-at-zero (deduction limited to available pay; remainder logged as QCP expense)
   - Each contributing schedule's status → `accelerated_to_final_paycheck`
3. **For W-2:**
   - Computes final pay (hourly accrued + any final-week salary)
   - **PTO balance is forfeited** per ADR-0016 — no payout
   - Closes the open `pto_year_allotment` with status = `forfeited`
4. Payroll role marks this step complete when payroll has been processed in their external system (QuickBooks, etc.)

### Audit trail

Each step in the workflow creates an `activity_log` entry. Key events:

```
[T+0min] Workflow initiated   - HR Jane → Termination workflow for Maria (contractor)
                                  effective_date=2026-05-21
                                  termination_type=involuntary_termination
                                  reason_category=attendance
                                  rehireable=false

[T+0min] Status changed       - System → Maria: contractor_active → pending_termination
[T+0min] Roster removed       - System → Maria removed from Marriott DT roster
[T+0min] Clock-in auto-closed - System → Maria's open time_entry closed at 2026-05-21 15:00 (effective date/time)
[T+0min] PTO requests cancelled - System → 2 pending PTO requests cancelled (auto)

[T+1h]  Equipment recovered   - Front Desk David → Recovered: 1 laptop, 1 polo (per recovery form)
[T+1h]  File moved             - Front Desk David → File moved from active cabinet to HR retention
[T+1h]  Status changed         - System → Maria: pending_termination → terminated (terminated_at=2026-05-21 16:00)

[T+1day] Final paycheck       - Payroll Sarah → Processed final paycheck for Maria
                                  Contractor charge balance consolidated: $25 (cap-at-zero applied; full balance covered)

[T+8days] QuickBooks removed  - Payroll Sarah → Maria removed from QuickBooks
[T+8days] Workflow complete   - System → Termination workflow #1247 closed
```

## Consequences

### Positive

- Single coherent flow handles all termination types and person types
- Two-step status (`pending_termination` → `terminated`) captures the operational reality: person stops working immediately but final administrative steps take time
- Effective date flexibility supports notice periods, post-hoc entries, and walked-off scenarios
- Auto-cancellations of pending workflows prevents zombie tasks
- Cap-at-zero rule on final paycheck handles edge cases gracefully (no negative checks, no collection pursuit)
- Equipment recovery is structured (Front Desk completes form), not informal
- Audit trail captures every step with timestamps and actors
- 7-day QuickBooks delay aligns with payroll cycle, ensuring final check processes

### Negative

- Adds `pending_termination` status — one more state to handle in queries that filter "active" people
- Workflow can stall if Front Desk doesn't promptly do the file-move task (person stays in `pending_termination` indefinitely)
- Backdated terminations create cascading edge cases (clock-ins that happened "after" effective date but were already recorded — usually rare, but possible)
- Cancellation has many side effects; super_admin must be careful

### Implementation requirements

Schema additions:

- **`people.status`** enum gains `pending_termination`
- **`workflows`** table (existing engine): `effective_date` column on termination workflow data (jsonb)
- **`equipment_assignments`** already supports the recovery form (per ADR-0012)
- **`pto_year_allotments`** status enum already supports `forfeited` (per ADR-0016)
- **`contractor_charge_schedules`** status enum already supports `accelerated_to_final_paycheck` (per ADR-0014)

New tables:

- **`termination_records`** — denormalized snapshot for reporting:
  ```
  - id, workflow_id, person_id
  - initiated_by, initiated_at
  - effective_date
  - termination_type, reason_category, notes, rehireable
  - file_moved_at, file_moved_by
  - terminated_at (= file_moved_at)
  - final_paycheck_period_id, final_paycheck_processed_at, final_paycheck_processed_by
  - quickbooks_removed_at, quickbooks_removed_by
  - cancelled_at, cancelled_by, cancellation_reason (for cancelled terminations)
  ```

Scheduled jobs:

- **`ProcessScheduledTerminations`** — daily; advances workflows whose `effective_date` is today (transitions status, runs auto-close steps)
- **`ProcessFinalPaycheckTrigger`** — when a final period's timesheet is approved AND person status = `terminated`, surface the "Process final paycheck" task to Payroll
- **`ProcessQuickBooksRemovalDelay`** — daily; advances workflows where final paycheck was processed 7+ days ago

Permissions:

- `workflows.termination.initiate` — recruiter (own), hr, admin, super_admin
- `workflows.termination.physical_tasks` — front_desk, office_manager, hr (fallback), admin, super_admin
- `workflows.termination.payroll_tasks` — payroll, admin, super_admin
- `workflows.termination.cancel` — super_admin only

## Alternatives considered

### A. Status change immediately on initiation (no `pending_termination` state)

Rejected. Email 3 was explicit that status changes only when receptionist completes file move. The two-step model captures the operational reality.

### B. Separate workflows for voluntary vs involuntary

Rejected. The flow shape is identical; the type is just a field. One workflow is simpler.

### C. Separate workflows for contractor vs W-2

Rejected. Conditional steps in one workflow handles the differences; one definition is easier to maintain.

### D. Allow status to skip directly from `contractor_active` to `terminated`

Rejected. Loses the file-move-as-completion-signal that the client explicitly requested.

### E. No effective date — termination always immediate

Rejected. Notice periods are real. Forward-dated terminations enable proper "2-week notice" handling.

### F. Auto-restore cancelled workflows on termination cancellation

Rejected as too risky. Pending workflows may have been routed and acted on; restoring creates inconsistencies. Person resubmits if needed.

## Related

- `40-flows/termination.md` — step-by-step flow walkthrough
- `20-domain/workflows.md` — termination catalog entry
- `20-domain/people-lifecycle.md` — pending_termination status
- ADR-0012 — equipment_assignments + Recover Equipment step
- ADR-0014 — contractor_charge_schedules + final paycheck cap-at-zero
- ADR-0016 — PTO forfeit on termination
- ADR-0013 — Front Desk role (physical tasks)
- `10-architecture/permissions-matrix.md` — termination permissions
