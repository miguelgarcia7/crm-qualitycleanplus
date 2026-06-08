# Phase 04b-ii — Termination Workflow

| Field | Value |
|---|---|
| Status | ✅ Done (Pest green — 9 tests; Pint + Larastan clean, build + types clean, `migrate:fresh --seed` clean). |
| Last updated | 2026-06-07 |
| Owner | Engineering |

## As built (current state)

- **Engine wiring:** `WorkflowType::Termination`, registered in `AppServiceProvider`.
- **Definition:** `app/Domain/People/Definitions/TerminationDefinition.php` — 7 steps
  (`initialize` → `remove_from_rosters` → `cancel_pending_workflows` (system) →
  `recover_equipment` → `move_file` (front_desk) → `process_final_paycheck`
  (payroll) → `finalize` (system)). `onStart` saves the prior status + creates the
  record; `move_file` completion flips the person to `terminated`; `onCancelled`
  reverts status + stamps the record.
- **Final paycheck:** `app/Domain/People/Actions/ProcessFinalPaycheck.php` —
  consolidates outstanding charge schedules into one **capped, non-billable**
  deduction on the contractor's open period (cap = gross pay − existing deductions,
  floored at 0); accelerates contributing schedules (`accelerated_to_final_paycheck`,
  entries `skipped`); writes off any remainder as a QCP expense (activity log); no
  open period → returns a flag, no deduction.
- **Record:** `termination_records` (migration + `TerminationRecord` model + factory)
  captures type/reason, file-move, final-paycheck result, QuickBooks marker, cancellation.
- **Surface:** `TerminationController` + `routes/backoffice.php` (`backoffice.terminations.*`)
  + sidebar "Terminations" (gated `workflows.termination.initiate`). UI
  `views/admin/terminations/{index,create,show}.tsx` — list, new-termination form,
  detail page with step timeline + role-appropriate action panel (equipment checklist,
  move-file, final-paycheck preview, super-admin cancel). Steps also surface in My Tasks.
- **Tests:** `tests/Feature/TerminationTest.php` (9) — initiate (status/WO close/workflow
  cancel/record), equipment recovery, file move → terminated, final-paycheck consolidation
  + cap-at-zero + no-open-period, finalize completes, super-admin cancel + cancel-too-late,
  permission gating.
- **Seeder:** an in-progress termination (awaiting front-desk equipment recovery) is
  seeded for the last sample contractor.

## Context

With 04a (engine + inventory + money chain) and 04b-i (transfer / temp / pay
increase) done, the headline remaining people workflow is **termination**
(ADR-0018). It ends a contractor's or W-2 staffer's tenure through a multi-role
process: system steps flip status and clean up rosters; **front desk** recovers
equipment and moves the physical file; **payroll** processes the final paycheck
(consolidating any outstanding contractor charges). It reuses everything 04a/04b-i
built — equipment assignments, charge schedules, adjustments, the WO close
mechanic, and the engine.

## Scope decisions (locked with owner)

- **Dedicated Terminations area** (list + new + detail page where the assigned role
  acts on the current step inline); steps also appear in My Tasks.
- **v1 simplifications:** status flips to `pending_termination` immediately on
  initiate (forward-dated scheduling deferred); final-paycheck consolidation is
  **open-period-only** (cap-at-zero; no open period → surface a payroll flag, no
  invoice void/reissue); **QuickBooks removal is a recorded marker** (no
  integration, no 7-day delay); **W-2 PTO forfeit deferred to Phase 08**.

## Acceptance

HR/recruiter/admin initiates → status `pending_termination`, active WOs closed at
the effective date, the subject's pending workflows auto-cancelled. Front desk
recovers each assigned equipment item (returned/lost) and marks the file moved →
status `terminated`. Payroll processes the final paycheck → outstanding charge
schedules consolidate into one capped, non-billable deduction on the open period
(schedules → `accelerated_to_final_paycheck`). A final system step records the
QuickBooks removal and completes the workflow. Super-admin can cancel before the
file-move step (reverts status; does not auto-restore cancelled workflows). A
`termination_records` row captures it for reporting.

## Anchored ADRs

ADR-0018 (termination) · ADR-0014 (charge consolidation) · ADR-0026 (engine) ·
ADR-0006/0005 (no invoice void in v1). Permissions pre-seeded:
`workflows.termination.{initiate,physical_tasks,payroll_tasks,cancel}`,
`termination_records.view`.

## Reuse (don't rebuild)

- **Engine:** `StartWorkflow` / `CompleteStep` / `CancelWorkflow` / `AdvanceWorkflow`
  (auto-runs leading system steps) + `WorkflowDefinition` hooks.
- **`ReturnEquipment`** for the recovery step; `EquipmentAssignment` `status=assigned`
  query for the subject.
- **`CloseWorkOrder`** for roster removal; **`ContractorChargeSchedule`/Entry**,
  **`TimeEntryAdjustment`** (forces deduction non-billable), **`TimeSummary`** for
  final-paycheck consolidation; enums `AdjustmentSourceType::SupplyRequestTerminationConsolidation`
  + `ChargeScheduleStatus::AcceleratedToFinalPaycheck` already exist.
- **`WorkflowNotice`** (database) for notifications; `PersonStatus::PendingTermination`/`Terminated`.

## Increments

0. **Infra** — this doc; `WorkflowType::Termination`; `TerminationType`/`ReasonCategory`
   enums; `termination_records` migration + `TerminationRecord` model + factory.
1. **Definition + final paycheck** — `TerminationDefinition` (7 steps + hooks),
   register in `AppServiceProvider`; `ProcessFinalPaycheck` action.
2. **Controller + UI** — `TerminationController` (index/create/store/show + step
   actions + cancel); routes; sidebar; `terminations/{index,create,show}.tsx`.
3. **Tests + docs** — `TerminationTest`; gates green; `migrate:fresh --seed` with a
   demo termination; update this doc + roadmap.

## Steps (TerminationDefinition)

1. `initialize` (system) — status → `pending_termination` (prior status saved to `data`).
2. `remove_from_rosters` (system) — `CloseWorkOrder` each active WO at `effective_date`; detach property assignments.
3. `cancel_pending_workflows` (system) — `CancelWorkflow` the subject's other pending/in-progress workflows.
4. `recover_equipment` (human, `front_desk`, `workflows.termination.physical_tasks`).
5. `move_file` (human, `front_desk`, physical_tasks) — `onStepCompleted` → status `terminated`, stamp record.
6. `process_final_paycheck` (human, `payroll`, `workflows.termination.payroll_tasks`).
7. `finalize` (system) — record `quickbooks_removed_at`; workflow completes.

`onStart` creates the record. `onCancelled` reverts status to the pre-termination value + stamps the record.

## Out of scope (later)

Forward-dated termination scheduling + real QuickBooks integration / 7-day delay;
W-2 PTO forfeit (Phase 08); invoice void/reissue for already-invoiced final periods;
auto-restoring auto-cancelled workflows on cancel. More-staff + change-personal-info
are the final 04b slice.

## Related

- ADR-0018, ADR-0014, ADR-0026; `80-plan/phase-04b-wo-lifecycle-workflows.md` (04b-i)
- `80-plan/roadmap.md`
