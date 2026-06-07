# Flow: Termination

| Field | Value |
|---|---|
| Status | Accepted |
| Last updated | 2026-05-21 |
| Owner | Product (David) + Engineering |

End-to-end flow for terminating a contractor or W-2 staff member. Handles voluntary resignations, involuntary terminations, end-of-assignment, abandonment, and mutual departures via one unified workflow.

Related: ADR-0018, `20-domain/workflows.md`, `20-domain/people-lifecycle.md`.

## Actors

| Actor | Role |
|---|---|
| Initiator | Recruiter (own contractors), HR (anyone), Admin, Super Admin |
| Front Desk | Handles physical office tasks (Recover Equipment + File Move) |
| Payroll | Processes final paycheck + QuickBooks removal |
| System | Orchestrates auto-steps (roster removal, clock-in close, workflow cancellations) |

## Pre-conditions

- Person being terminated exists in the system
- Initiator has appropriate permission (`workflows.termination.initiate`)
- For Recruiter initiation: person must be a contractor at one of recruiter's assigned properties

---

## Path 1: Standard contractor termination (effective today, all parties present)

```
1. Recruiter views Maria's profile → clicks "Terminate"
   │
   ▼
2. Modal opens with form:
   ┌─────────────────────────────────────────────┐
   │  Terminate Maria Lopez                       │
   │                                               │
   │  Effective date: [today ▼]                   │
   │                                               │
   │  Termination type:                            │
   │    ○ Voluntary Resignation                   │
   │    ○ Involuntary Termination                 │
   │    ○ End of Assignment                       │
   │    ○ Abandonment                              │
   │    ○ Mutual Agreement                         │
   │                                               │
   │  Reason category:                             │
   │    [dropdown: performance / misconduct /     │
   │     attendance / personal / end_of_need /    │
   │     no_call_no_show / other]                  │
   │                                               │
   │  Notes (required):                            │
   │    [text]                                    │
   │                                               │
   │  Rehireable?  ○ Yes   ○ No                   │
   │                                               │
   │  [Cancel]  [Submit Termination]               │
   └─────────────────────────────────────────────┘
   │
   ▼
3. Submit
   │
   ▼
4. Server creates termination workflow:
   - workflow row created
   - termination_records row created with all form fields
   - Effective date is today → kick off immediate steps:
     a. Status: contractor_active → pending_termination
     b. Roster: remove Maria from Marriott DT property_assignments
     c. Work orders: close any active work_orders at effective_date
     d. Open clock-in: auto-close any open time_entry using effective_date as end time
     e. Pending workflows: auto-cancel any pending workflows Maria initiated
     f. Field visits (if any): auto-close
   │
   ▼
5. Workflow advances to "Recover Equipment" step
   - Assigned to: front_desk (fallback: office_manager)
   - Front Desk sees task in their queue
   │
   ▼
6. Front Desk opens task
   - Detail view shows all of Maria's equipment_assignments where status=assigned
   - For each item: [Returned] [Not Returned] toggle + optional notes
   - Front Desk processes each item (per `40-flows/supply-request.md` equipment return flow)
   - Clicks "Complete Equipment Recovery"
   │
   ▼
7. Workflow advances to "Move file to HR retention" step
   - Assigned to: front_desk
   - Front Desk physically moves the file
   - Clicks "Mark Complete"
   │
   ▼
8. CRITICAL: status transition
   - Status: pending_termination → terminated
   - terminated_at = now (this is when "terminated" status takes effect)
   - termination_records.file_moved_at, file_moved_by populated
   - termination_records.terminated_at = file_moved_at
   │
   ▼
9. Workflow advances to "Process final paycheck" step
   - Assigned to: payroll
   - Waits until the final payroll_period's timesheet is approved (per ADR-0014)
   - When ready, Payroll opens task:
     - Detail shows: final payroll_period, hours worked, contractor_charge_schedules to consolidate
     - For Maria (contractor):
       * Sums remaining entries across active charge schedules: $25
       * Computes consolidated deduction with cap-at-zero
       * Final pay: $X gross - $25 deductions - taxes
     - Payroll processes payment in QuickBooks externally
     - Clicks "Mark Complete — Final paycheck processed"
   - termination_records.final_paycheck_processed_at, final_paycheck_processed_by populated
   │
   ▼
10. Workflow advances to "Remove from QuickBooks" step (delayed 7 days)
    - Scheduled job creates the task 7 days after final_paycheck_processed_at
    - Payroll receives notification when task surfaces
    - Payroll removes Maria from QuickBooks externally
    - Clicks "Mark Complete"
    - termination_records.quickbooks_removed_at, quickbooks_removed_by populated
    │
    ▼
11. Workflow status: completed
    Audit trail captures the full timeline
```

## Path 2: Forward-dated termination (2-week notice)

```
1. HR initiates termination for staff member Jane
   - Today: 2026-05-21
   - Effective date: 2026-06-04 (2 weeks out)
   - Termination type: voluntary_resignation
   
2. Workflow created with status=in_progress
   - effective_date stored
   - Person status is UNCHANGED (Jane is still staff_active)
   - Jane keeps working normally for the 2-week notice
   
3. During notice period:
   - Jane can clock in, request PTO, submit workflows normally
   - Note: per ADR-0016, vacation during notice period shows a soft warning
   - Termination workflow is visible on Jane's profile as "Termination scheduled for 2026-06-04"
   
4. Daily scheduled job (ProcessScheduledTerminations):
   - On 2026-06-04, finds the workflow with effective_date=today
   - Advances to "Initialize termination" effect:
     a. Status: staff_active → pending_termination
     b. Remove from property_assignments (if applicable)
     c. Auto-close any open field_visits
     d. Auto-cancel pending workflows (any pending PTO requests, etc.)
   
5. Workflow continues with Recover Equipment → File Move → Final Paycheck → QuickBooks Removal
   (Same as Path 1 from step 5 onward)
```

## Path 3: Backdated termination (post-hoc entry for walk-off)

```
1. Maria walked off the job on Monday 2026-05-19
   No notice given. Recruiter didn't realize until Wednesday 2026-05-21.
   
2. Recruiter initiates termination on Wednesday:
   - Effective date: 2026-05-19 (backdated)
   - Termination type: abandonment
   - Reason: no_call_no_show
   - Notes: "Maria didn't show up Monday, hasn't responded to calls. Walking off."
   - Rehireable: No
   
3. Server processes:
   - effective_date is in the past → status flips to pending_termination immediately
   - Roster removal effective backdated to 2026-05-19
   - Open clock-in (if Maria was somehow still showing as clocked in): auto-closed with end_time = 2026-05-19 (Monday)
   - Activity log: "Backdated termination — effective 2026-05-19 (entered 2026-05-21)"
   
4. Workflow continues normally (Front Desk recovers equipment, moves file, etc.)
```

## Path 4: Contractor walked off mid-shift (immediate termination, open clock-in)

```
1. Maria was clocked in at 9:00 AM. At 11:30 AM, she leaves without clocking out and doesn't return.
   
2. Recruiter initiates termination at 2:00 PM same day:
   - Effective date: today
   - Effective time: TBD (the system uses now() but ideally captures actual walk-off time)
   - Type: abandonment
   - Notes: "Walked off at ~11:30 AM, no contact since"
   
3. Server:
   - Status → pending_termination
   - Auto-close open clock-in: end_at_utc = effective_time (or now if not specified)
   - The morning's hours (9:00 AM to walk-off time) are still billable; the timesheet for the week captures them
   - Final paycheck includes those hours
```

## Path 5: W-2 staff termination (e.g. terminating a recruiter)

Similar to Path 1, but with W-2-specific differences:

```
- Equipment recovery: more likely (laptops, monitors, etc.)
- PTO forfeit: open pto_year_allotment moves to status=forfeited; all unused vacation/scheduled/unscheduled hours are lost
- No contractor_charge_schedules
- No work_orders to close (recruiters aren't on work orders)
- Property assignments: remove from recruiter_properties + any property_managers relationships
- Field visits (if recruiter has open ones): auto-close with was_late_close=true
- Final paycheck: hourly accrued + any final-week salary; no PTO payout
- Pending PTO requests: auto-cancelled
```

## Path 6: Workflow cancellation (rare, super_admin only)

```
1. Termination was initiated by mistake (e.g. wrong person selected)
   
2. Super Admin opens the termination workflow → "Cancel Workflow"
   - Modal: "This will cancel the termination. Why?"
   - Required reason text
   - Warning: "Auto-cancelled workflows will NOT be restored. Auto-closed time_entries can be manually restored. This action is logged."
   
3. Super Admin confirms
   
4. Server:
   - workflow status → cancelled, cancelled_by, cancellation_reason
   - termination_records.cancelled_at, cancelled_by, cancellation_reason
   - Person status reverts:
     - pending_termination → contractor_active (or staff_active, whichever was prior)
     - terminated_at cleared (back to NULL)
   - work_orders that were closed by the workflow: NOT auto-reopened (super_admin can manually reopen)
   - Auto-closed time_entries: NOT auto-restored (super_admin can manually restore)
   - Auto-cancelled pending workflows: NOT auto-restored (person resubmits if needed)
   - Activity log captures the cancellation
   
5. Person is back to active. May need manual cleanup if downstream effects need reversal.
```

Cancellation is **only available before the file-move step completes** (i.e. before status reaches `terminated`). After file move, reversing requires a rehire workflow (different process).

## Auto-cancellations summary

When termination's "Initialize" step runs, the following are auto-cancelled with reason="Initiator was terminated":

| Resource | Behavior |
|---|---|
| Pending PTO requests (initiated by this person) | Cancelled — hours return to balance immediately (but balance is about to be forfeited anyway) |
| Pending Supply Requests (initiated by this person, awaiting approval) | Cancelled |
| Pending Pay-Increase Requests (initiated by this person) | Cancelled |
| Open Field Visits (recruiter being terminated) | Closed with `was_late_close = true` |
| Open `time_entry` (contractor clocked in) | Closed at effective_date/time |
| Active `work_orders` (contractor) | Closed at effective_date |
| Active `contractor_charge_schedules` | Left active until final paycheck step (then consolidated per ADR-0014) |
| Active `equipment_assignments` | Surface in Recover Equipment step (Front Desk processes each) |

## Notifications

| Event | Recipient(s) | Channels |
|---|---|---|
| Termination initiated | HR, Initiator's manager (or admin if no manager), the subject person (if voluntary; not if involuntary) | Mail + in-app |
| Equipment recovery task ready | Front Desk | In-app |
| File-move task ready | Front Desk | In-app |
| Status changed to `terminated` | HR, payroll, initiator | In-app |
| Final paycheck task ready | Payroll | Mail + in-app |
| QuickBooks removal task ready (after 7-day delay) | Payroll | In-app |
| Workflow completed | HR, initiator | In-app |
| Workflow cancelled | HR, initiator, the person | Mail + in-app |

## Permissions

| Action | Roles |
|---|---|
| `workflows.termination.initiate` | super_admin, admin, hr, recruiter (own contractors) |
| `workflows.termination.physical_tasks` (equipment + file move) | super_admin, admin, front_desk (primary), office_manager (fallback), hr (fallback) |
| `workflows.termination.payroll_tasks` (final paycheck, QuickBooks removal) | super_admin, admin, payroll |
| `workflows.termination.cancel` | super_admin only |
| `termination_records.view` | super_admin, admin, hr, payroll |

## Audit trail example (Path 1)

```
[14:00] Workflow initiated  - Jane (recruiter) → Termination workflow #1247 for Maria
                                effective_date=2026-05-21
                                type=involuntary_termination, reason=attendance, rehireable=false

[14:00] Status changed      - System → Maria: contractor_active → pending_termination
[14:00] Roster removed      - System → Maria removed from Marriott DT
[14:00] Work order closed   - System → WO #4521 (Maria @ Marriott DT) closed effective 2026-05-21
[14:00] PTO requests N/A    - System → No pending PTO requests for Maria (contractor)
[14:00] Workflows cancelled - System → 1 pending Pay-Increase Request cancelled

[15:30] Equipment task started - Front Desk David → opened Recover Equipment task
[15:35] Equipment recovered    - Front Desk David → Returned: 2 Housekeeping Polos; Not Returned: 1 Name Tag (lost)
[15:35] Equipment task complete

[15:45] File-move task started - Front Desk David → opened File Move task
[15:50] File-move complete     - Front Desk David → File moved from active to HR retention
[15:50] Status changed         - System → Maria: pending_termination → terminated (terminated_at=2026-05-21 15:50)

[Day +1, 09:00] Final paycheck task ready - Payroll Sarah (timesheet for week of 2026-05-19 approved)
[Day +1, 11:30] Final paycheck processed  - Payroll Sarah → Maria's final pay processed
                                              charge_schedule consolidated: $5 of $5 deducted (Name Tag was $5; covered)
                                              cap_applied: false
[Day +8, 09:00] QuickBooks removal ready  - Payroll Sarah (7-day delay complete)
[Day +8, 10:15] QuickBooks removed         - Payroll Sarah → Maria removed from QuickBooks

[Day +8, 10:15] Workflow #1247 complete
```

## Edge cases

| Case | Handling |
|---|---|
| Termination initiated for contractor still clocked in | Auto-close clock-in at effective_date; hours billed normally for that day |
| Termination initiated for contractor with future approved shifts | Work_orders closed at effective_date; future shifts won't be scheduled (system won't accept clock-ins after effective_date for that person) |
| Front Desk delays the file-move task for weeks | Person stays in `pending_termination` indefinitely; surfaces on admin dashboard as "Stuck Terminations" |
| Equipment recovery requires items that don't exist (already lost) | Front Desk marks as Not Returned with notes; logged as expense per inventory rules |
| Termination initiator is themselves being terminated | Workflow remains initiated; cancellation is a separate action (super_admin only) |
| Person has multiple identities (recruiter + contractor both, per ADR-0004) | Termination workflow applies to ONE identity; the other identity remains separately. HR must initiate both if both should end. |
| Forward-dated effective_date passes while person is on PTO | On the effective date: status flips, PTO auto-cancelled, normal flow continues. Their last day of work was the start of PTO. |
| Cancel termination after equipment was already recovered | Equipment_assignments stay in returned state; Super Admin can manually re-issue if needed via Manual Stock Out + assignment |
| Backdated termination on a date the contractor had clocked in (recorded normally) | System keeps recorded time_entries (they're real hours). Effective_date is just the "last day of work" marker. |

## Related

- ADR-0018 — Termination workflow (full decision)
- `20-domain/workflows.md` — termination catalog entry
- `20-domain/people-lifecycle.md` — pending_termination status
- `20-domain/inventory.md` — equipment recovery details
- `20-domain/pto.md` — PTO forfeit on termination
- `20-domain/adjustments.md` — final paycheck consolidation
- ADR-0014 — contractor_charge_schedule cap-at-zero
- ADR-0016 — PTO forfeit policy
- `10-architecture/permissions-matrix.md` — termination permissions
