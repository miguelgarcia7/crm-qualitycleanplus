# Phase 04b-iii — More-Staff Request + Change-Personal-Info

| Field | Value |
|---|---|
| Status | ✅ Done (Pest green — 17 tests; Pint + Larastan clean, build + types clean, `migrate:fresh --seed` clean). |
| Last updated | 2026-06-07 |
| Owner | Engineering |

## As built (current state)

- **Engine wiring:** `WorkflowType` += `MoreStaff`/`ChangePersonalInfo`, both registered
  in `AppServiceProvider`.
- **More-staff:** `more_staff_requests` table + `work_orders.more_staff_request_id`;
  `MoreStaffRequest` model (+ `MoreStaffUrgency`/`MoreStaffStatus` enums, factory);
  `MoreStaffDefinition` (one `fulfill_staffing` recruiter step; notify on start,
  decline → `declined`, cancel → `cancelled`); `RecordMoreStaffPlacement` (recompute
  count, status transition, PM notify, auto-complete the step at threshold). PM submits
  on QC Minute (`Minute/MoreStaffController` + `views/minute/more-staff`); recruiter
  queue + decline + super-admin cancel in back office (`MoreStaffController` +
  `views/admin/more-staff`); link offered in the WO create form (filtered by property).
- **Change-personal-info:** `ChangePersonalInfoDefinition` (subject = Person; one
  `verify_change` HR step; `onStepCompleted` applies name/email/phone — email nulls
  `email_verified_at`, phone recomputes `normalized_phone`). Employee self-requests on
  QC Minute (`Minute/ChangePersonalInfoController` + `views/minute/info-changes`); HR
  verifies / declines / initiates-on-behalf in back office (`ChangePersonalInfoController`
  + `views/admin/info-changes`). Both human steps also surface in My Tasks.
- **Tests:** `tests/Feature/{MoreStaffTest,ChangePersonalInfoTest}.php` (8 + 7).
- **Seeder:** one open more-staff request (PM) and one pending info-change request.

> QC Minute is restricted to property_manager/contractor/admin (not w2_employee), so
> W-2 staff use the back-office on-behalf path for info changes.

## Context

The final slice of Phase 04b, completing the Phase 04 workflow set. Two workflows
on the ADR-0026 engine, both reusing the pay-increase **two-surface** pattern
(requester initiates on QC Minute; recruiter/HR responds in a back-office queue;
the human step also appears in the shared My Tasks inbox):

- **More-staff request** (ADR-0021/0011) — a PM asks for more contractors at their
  property; a recruiter fulfills by placing people (linking work orders) or declines.
- **Change-personal-info** (workflows.md) — an employee requests a change to their
  own name/email/phone; HR verifies and applies (or rejects).

## Scope decisions (locked with owner)

- Build **both** workflows in this slice.
- More-staff = **v1 core**: request → recruiter links WOs (auto-progress to
  fulfilled) → decline + PM/super-admin cancel + notifications. **Deferred:** overdue
  daily job, dashboard widgets, "create job posting" shortcut, and auto-linking
  placements made via transfer/temp (v1 links on the recruiter WO-create path only).
- Change-info = **name/email/phone only** (no new PII columns), via **both surfaces**.

## Anchored ADRs

ADR-0021 + ADR-0011 (more-staff) · ADR-0026 (engine) · `20-domain/workflows.md`
(change-info). Permissions pre-seeded: `workflows.more_staff.*`,
`workflows.change_personal_info.verify`, `people.own_profile.request_change`.

## Reuse (don't rebuild)

- **Engine:** `StartWorkflow` / `CompleteStep` / `RejectStep` / `CancelWorkflow` /
  `AdvanceWorkflow` + `WorkflowDefinition` hooks; `StepBlueprint::humanRole`.
- **Two-surface template:** `Minute/PayIncreaseController` (PM initiate) +
  `PayIncreaseController` (back-office queue, `authorizeProperty`/`GLOBAL_ROLES`).
- **WO creation:** `CreateWorkOrder`, `StoreWorkOrderRequest`, `WorkOrderController` —
  extended with the optional `more_staff_request_id` link.
- **`WorkflowNotice`** notification; **My Tasks** is generic (`WorkflowTaskController`).

## Increments

0. **Infra** — this doc; `WorkflowType` += `MoreStaff`/`ChangePersonalInfo`;
   `MoreStaffUrgency`/`MoreStaffStatus` enums; `more_staff_requests` +
   `work_orders.more_staff_request_id` migrations; `MoreStaffRequest` model + factory.
1. **More-staff** — `MoreStaffDefinition` (+register) + `RecordMoreStaffPlacement`;
   WO-create link integration; back-office `MoreStaffController` queue + QC Minute
   `Minute/MoreStaffController`; routes, sidebar, dashboard card, views.
2. **Change-personal-info** — `ChangePersonalInfoDefinition` (+register); back-office
   `ChangePersonalInfoController` (queue + on-behalf + approve/decline) + QC Minute
   `Minute/ChangePersonalInfoController` (self-request); routes, sidebar, card, views.
3. **Tests + docs** — `MoreStaffTest` + `ChangePersonalInfoTest`; gates green; demo
   seed; update this doc + roadmap (Phase 04 → done).

## More-staff mechanics

Subject = `MoreStaffRequest`. One human step `fulfill_staffing` (recruiter). The
request row carries operational state; `quantity_fulfilled` = count of linked WOs.
`RecordMoreStaffPlacement` (called after a linked WO is created) recomputes the
count, transitions status (`submitted`→`in_progress`→`fulfilled`), and completes the
workflow step when the threshold is met. Decline → `RejectStep`; cancel → `CancelWorkflow`.

## Change-info mechanics

Subject = the `Person`. One human step `verify_change` (hr). `onStepCompleted`
applies `data.changes` (name/email/phone) — email change nulls `email_verified_at`,
phone change recomputes `normalized_phone`. Reject notifies the requester.

## Out of scope (later)

More-staff overdue job + dashboard widgets + job-posting shortcut + transfer/temp
auto-linking; change-info address/emergency/bank fields + sensitive-field
verification + a full QC Minute settings surface.

## Related

- ADR-0021, ADR-0011, ADR-0026; `80-plan/phase-04b-ii-termination.md`; `80-plan/roadmap.md`
