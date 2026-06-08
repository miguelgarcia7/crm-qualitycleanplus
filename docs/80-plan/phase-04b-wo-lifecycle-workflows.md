# Phase 04b-i — Work-Order Lifecycle Workflows (Transfer · Temporary Assignment · Pay Increase)

| Field | Value |
|---|---|
| Status | ✅ Done (Transfer · Temp · Pay Increase; Pest green, Pint + Larastan clean, build + types clean). |
| Last updated | 2026-06-07 |
| Owner | Engineering |

## As built (current state)

- **Engine wiring:** `WorkflowType` += `Transfer`/`TemporaryAssignment`/`PayIncrease`,
  registered in `AppServiceProvider`. `work_orders.is_temporary_assignment` added.
- **Shared mechanic:** `SupersedeWorkOrder` (close old at `effective−1`, open new
  `active` WO with `parent_wo_id` + `source`) and `OpenTemporaryWorkOrder` (temp
  child WO); `WorkflowNotice` database notification.
- **Transfer** (`TransferDefinition`, system `apply` step): supersede WO, reassign
  `primary_recruiter_id`, best-effort charge-schedule remap to the new property's
  same-week period, notify the new recruiter. Initiated from the Work Orders list
  ("Transfer" row action → modal).
- **Temporary assignment** (`TemporaryAssignmentDefinition`): opens the temp child
  WO (home WO untouched), notifies the host recruiter; `ProcessTemporaryAssignmentEnds`
  (daily) closes temps at `end_date`. Roster counts use `Person::primaryContractorsOf`
  so temps don't inflate them. "Temp" row action → modal.
- **Pay increase** (`PayIncreaseDefinition`): PM submits an increase on QC Minute
  (`Minute/PayIncreaseController`, never sees pay rate) → recruiter approves in the
  back office (`PayIncreaseController`, editable rates with the **bill-rate floor
  enforced**, effective period = next/2-out) → superseding WO; PM notified (bill),
  contractor notified (pay). Recruiter-initiated path applies immediately. Decline
  rejects + notifies PM, no WO. New "Pay Increases" sidebar entry; PM links from
  the QC Minute dashboard.
- **Tests:** `tests/Feature/{TransferWorkflowTest,TemporaryAssignmentTest,PayIncreaseTest}.php`
  (11 tests) — supersede correctness, charge remap, temp auto-close, floor
  enforcement, dual notifications, decline, recruiter-initiated, role gating.
- **Seeder:** a pending PM pay-increase is seeded for demo (shows in the recruiter
  queue + My Tasks).

The first slice of Phase 04b (the people/WO workflows on the ADR-0026 engine). It
builds the three **work-order lifecycle** workflows, which share one mechanic —
*close the old work order, open a new one* — and span both surfaces (recruiter
back office + PM QC Minute). Termination and the request workflows (more-staff,
change-personal-info) are later slices.

## Scope decisions (locked with owner)

- **WO-lifecycle trio first:** Transfer (permanent), Temporary Assignment, Pay Increase.
- **No new "request" tables:** each workflow's `subject` is the contractor `Person`;
  the form payload lives in `workflow.data`; the WO chain (`parent_wo_id` + `source`)
  is the record of truth.
- **Termination (later) = open-period-only** for final-paycheck consolidation.

## Anchored ADRs

ADR-0026 (engine) · ADR-0019 (transfer + temporary assignment) · ADR-0020 (pay
increase) · ADR-0005 (rate snapshots) · ADR-0009 (payroll periods). Permissions are
pre-seeded (`workflows.transfer.*`, `workflows.temporary_assignment.*`,
`workflows.pay_increase.*`).

## Increments

0. **Infra** — this doc; `WorkflowType` += Transfer/TemporaryAssignment/PayIncrease;
   `work_orders.is_temporary_assignment`; reusable `SupersedeWorkOrder` +
   `OpenTemporaryWorkOrder` actions; `WorkflowNotice` notification; `Person`
   primary-recruiter roster scope.
1. **Transfer** — `TransferDefinition` (system `apply`: supersede WO, reassign
   primary recruiter, remap scheduled charges, notify); WO-index row action + modal.
2. **Temporary assignment** — `TemporaryAssignmentDefinition` (open temp child WO,
   notify); `ProcessTemporaryAssignmentEnds` daily job; WO-index row action + modal.
3. **Pay increase** — `PayIncreaseDefinition` (PM-approval path + recruiter-initiated;
   bill-rate floor; dual notifications); QC Minute PM submit; back-office approval
   queue + recruiter-initiated create.
4. **Tests + docs** — Pest across all three; gates green; demo data; update roadmap.

## v1 simplifications

- New WOs are created immediately with the effective `start_date` (no forward-dated
  scheduling job for transfer/pay-increase; temp auto-close *is* scheduled).
- Charge-schedule remap on transfer is best-effort (repoint scheduled entries to the
  new property's period with the same `week_start` when one exists).
- Pay-increase effective period = next or 2-out only.

## Out of scope (later slices)

Termination; more-staff request (+ `work_orders.more_staff_request_id`);
change-personal-info; recruiter-to-property transfer; forward-dated scheduling;
denormalized `pay_increase_requests` reporting table.

## Related

- ADR-0026, ADR-0019, ADR-0020; `80-plan/phase-04-workflow-inventory.md` (04a)
- `80-plan/roadmap.md`
