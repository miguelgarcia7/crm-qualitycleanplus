# Flow: Temporary Assignment

| Field | Value |
|---|---|
| Status | Accepted |
| Last updated | 2026-05-21 |
| Owner | Product (David) + Engineering |

End-to-end flow for **temporarily** assigning a contractor to a different property for a defined period. The contractor's primary recruiter and roster ownership stay unchanged; they just work elsewhere for a while.

For **permanent** moves, see `40-flows/transfer.md`.

Related: ADR-0019, `20-domain/work-orders.md`.

## When to use this (vs. transfer)

| Scenario | Use |
|---|---|
| Contractor moves to new property; home base changes; doesn't return | **Transfer** |
| Contractor needs to cover at another property for 1-14 days, then returns to home property | **Temporary Assignment** |
| Contractor stays at home but changes position | **Transfer** (position change) |
| Recruiter changes hands for the contractor permanently | **Transfer** |
| Multi-property simultaneous (contractor works regularly at two properties) | Create two regular work orders manually (not a workflow) |

The key distinction: **does primary recruiter / roster ownership change?**

- **Yes** → Transfer
- **No** (just a temporary visit) → Temporary Assignment

## Actors

| Actor | Role |
|---|---|
| Initiator | Home recruiter (contractor's primary), HR, Admin, Super Admin |
| Receiving recruiter | Notified (no approval needed) |
| System | Auto-creates temp WO, auto-closes at end_date |

## Pre-conditions

- Contractor is `contractor_active`
- Contractor has a `primary_recruiter_id` set
- Initiator has appropriate permission (`workflows.temporary_assignment.initiate`)
- New property exists in Property Bible with rates for the target position

---

## Standard flow

```
1. Home recruiter (Jane) views Maria's profile → clicks "Temporary Assignment"
   │
   ▼
2. Modal opens with form:
   ┌─────────────────────────────────────────────┐
   │  Temporary Assignment for Maria Lopez        │
   │                                               │
   │  Maria stays under your management.          │
   │  Her primary recruiter and home property      │
   │  do not change.                              │
   │                                               │
   │  Home property: Marriott Downtown Phoenix    │
   │                                               │
   │  Temp property: [Hyatt Regency Atlanta ▼]    │
   │  → Receiving recruiter: David Smith          │
   │                                               │
   │  Position at temp property:                  │
   │    [Housekeeper ▼]                            │
   │                                               │
   │  Start date: [May 22, 2026 ▼]                │
   │  End date:   [June 5, 2026 ▼]                │
   │                                               │
   │  Rates at temp property (auto-filled):       │
   │    Pay rate:  [$ 22.00 / hr]                 │
   │    Bill rate: [$ 35.00 / hr]                 │
   │    OT pay:    [$ 33.00 / hr]                 │
   │    OT bill:   [$ 52.50 / hr]                 │
   │                                               │
   │  Reason: [Coverage Need ▼]                    │
   │  Notes: [text]                                │
   │                                               │
   │  [Cancel]  [Submit]                           │
   └─────────────────────────────────────────────┘
   │
   ▼
3. Submit
   │
   ▼
4. Server processes:
   a. Validate (start_date <= end_date; new property exists; no overlapping temp assignment)
   b. Home WO at Marriott: NO CHANGES (stays active)
   c. Create temp WO at Hyatt:
      - status = active
      - start_date = start_date from form
      - end_date = end_date from form (REQUIRED — distinguishes from permanent)
      - parent_wo_id = Marriott home WO.id
      - source = temporary_assignment_from_wo_id
      - is_temporary_assignment = true
      - rates from form
   d. people.primary_recruiter_id = Jane (UNCHANGED — Maria still belongs to Jane)
   e. contractor_charge_schedules: NO REMAPPING (they belong to the person; deductions apply on whichever WO is active during a period)
   f. Activity log entry on workflow + new WO
   │
   ▼
5. Notify receiving recruiter (David):
   - In-app + mail: "Maria Lopez is temporarily assigned to your property Hyatt Atlanta from May 22 to June 5. She remains under Jane Doe's primary management."
   - Maria appears on David's "Visiting Contractors" list (not main roster count)
   │
   ▼
6. During temp period (May 22 → June 5):
   - Maria has TWO active WOs:
     * Home WO at Marriott (start_date in past, end_date null)
     * Temp WO at Hyatt (start_date = May 22, end_date = June 5)
   - Maria clocks in wherever she's physically working
     * Marriott QR: GPS verifies, matches home WO → time_entry on home WO
     * Hyatt QR: GPS verifies, matches temp WO → time_entry on temp WO
   - Time entries are billed to whichever property she was at
   - For the duration: Marriott may or may not be utilizing her (depends on operational reality)
   - Hyatt benefits from her time during this window
```

## End of temp period — auto-close

```
1. Today: June 5 (end_date)
   │
   ▼
2. Daily scheduled job: ProcessTemporaryAssignmentEnds
   - Finds: temp WOs where end_date <= today AND status = active
   - For each:
     a. status = closed
     b. Activity log entry
     c. Notify home recruiter (Jane): "Maria's temp assignment at Hyatt has ended; she's back at Marriott"
     d. Notify receiving recruiter (David): "Maria's temp assignment has ended; she returns to her home property"
   │
   ▼
3. Maria's WOs after auto-close:
   - Home WO at Marriott: still active (unchanged)
   - Temp WO at Hyatt: closed (in history)
   │
   ▼
4. Maria resumes clocking in at Marriott (only home WO is active for her now)
```

## Extending a temp assignment

If the temp needs to go longer than originally planned:

**Option A: Edit the end_date directly**

- Home recruiter (or admin) edits the temp WO's `end_date` (extend forward)
- Permission: `work_orders.edit` on own (recruiter) or any (admin)
- Activity log captures the extension and reason

**Option B: Initiate another temporary_assignment workflow**

- After current temp ends (or before), submit new workflow with new end_date
- Creates a new temp WO if needed
- Useful if the gap between assignments is significant

Recommended: Option A for short extensions; Option B for non-contiguous periods.

## Multiple concurrent temps

A contractor could be in multiple temp assignments simultaneously (rare but possible — e.g. covering at Hyatt Mon/Tue, then Marriott DT North Wed/Thu). Each is its own temp WO with its own dates. The system handles this naturally:

- Multiple active WOs simultaneously
- Clock-in uses GPS + property to match the right WO
- Reports filter by property + date range to attribute time correctly

System constraint: end_date is required for each temp WO. There's no "open-ended" temp.

## Notifications

| Event | Recipient(s) | Channels |
|---|---|---|
| Temp assignment created | Receiving recruiter | Mail + in-app |
| Temp assignment created (informational) | Home recruiter (if initiated by HR/admin) | In-app |
| Auto-close at end_date | Home + receiving recruiters | In-app |
| Extension applied | Home + receiving recruiters | In-app |
| Cancellation | Home + receiving recruiters | Mail + in-app |

## Workflow cancellation (super_admin only)

```
- Super_admin clicks Cancel on the temp workflow
- Modal: required reason
- Server:
  * If start_date is in the future (temp WO created but not started): delete temp WO; clean state
  * If start_date has passed (temp is in progress):
    - Close temp WO with current date as end_date
    - Set was_cancelled = true on the WO
    - time_entries during the partial period stay attributed to where they were recorded
- Notify both recruiters
- Activity log entry
```

After end_date has passed and temp is already auto-closed, there's nothing to cancel — the temp is over.

## Reporting: what counts as whose

**Home recruiter (Jane) sees:**

- Maria on her roster (always, regardless of where Maria is currently working)
- Maria counts in Jane's total active contractor count
- Maria's primary work — Marriott DT — is Jane's responsibility
- Jane can see Maria's current location ("Currently temp at Hyatt until June 5")

**Receiving recruiter (David) sees:**

- Maria on his "Visiting Contractors" section (not main roster)
- Maria does NOT count in David's total active contractor count
- Maria's work at Hyatt shows up in Hyatt's hours, billable, etc.
- David can see Maria's home assignment ("Home: Marriott DT, primary recruiter: Jane")

**Reports:**

- "Recruiter contractor count" (roster size): uses `primary_recruiter_id` — Maria counts for Jane only
- "Recruiter property hours" (work delivered): uses property → recruiter (via property_assignments) — Maria's Hyatt hours count for David
- "Contractor activity" reports: use WO or time_entry-level joins — show full picture across both properties

## Edge cases

| Case | Handling |
|---|---|
| Contractor already in a temp assignment elsewhere; new temp overlaps | Allowed if dates don't conflict (i.e. ends before next starts); system surfaces overlap as a warning if any |
| Receiving recruiter declines | Out-of-band — not supported as a system flow. They raise it with home recruiter; home recruiter cancels if needed |
| Contractor doesn't show up at temp property | No different from any clock-in failure; recruiter follows up out-of-system |
| Home WO is closed/contractor terminated during temp | Standard termination flow handles it — temp WO is one of the WOs that closes; effective_date applies as usual |
| Temp end_date in the past on submission | Block at validation; end_date must be > start_date and start_date must be >= today (no backdating temp assignments — use a regular WO + close it if you forgot to log a past temp) |
| Pay-increase request initiated during temp period | Routes to home recruiter (Jane), not receiving recruiter, since Jane retains primary management |
| Charge schedules deductions during temp | Apply normally; person_id is what matters, not WO. The deduction lands on whatever payroll period the entry references — typically the home property's |

## What this enables

- Cross-property coverage without losing roster ownership
- Clear accountability ("who's responsible for Maria?" → her primary recruiter, always)
- Receiving property still tracks Maria's work for billing/reporting purposes
- No artificial transfer-then-transfer-back when the move is temporary
- Reports stay clean — roster counts don't yo-yo

## Related

- ADR-0019 — Transfer + Temporary Assignment workflows
- `40-flows/transfer.md` — for permanent moves
- `20-domain/workflows.md` — temporary_assignment catalog entry
- `20-domain/work-orders.md` — WO model + is_temporary_assignment flag
- `20-domain/people-lifecycle.md` — primary_recruiter_id field
- `10-architecture/permissions-matrix.md` — temporary_assignment permissions
