# Phase 06 — Role-Specific Dashboards

| Field | Value |
|---|---|
| Status | ✅ Done (Pest green — 7 dashboard tests; Pint + Larastan clean, build + types clean, `migrate:fresh --seed` clean). |
| Last updated | 2026-06-08 |
| Owner | Engineering |

## As built (current state)

- **Service:** `app/Domain/Dashboards/Services/DashboardMetrics.php` — `forBackOffice(Person)`
  and `forPropertyManager(Person)` return `{stats, lists, charts}` of plain arrays, gated by
  role and property-scoped (`scopedPropertyIds` → null for `GLOBAL_ROLES`, else assigned ids).
- **Back office:** `DashboardController@index` → role widgets. Widget sets: recruiter
  (my properties/contractors/invoices-to-send + draft-timesheets + expiring-contracts lists +
  weekly-hours chart); office_manager/admin (properties, supply requests, low-stock, recent
  imports, expiring contracts, weekly-revenue chart); payroll (open/locked periods,
  invoices-to-send, voided, expiring contracts, revenue); hr (info-changes, applicants,
  terminations list); super_admin (active WOs, people, open workflows, invoiced-this-month,
  revenue); front_desk (supply requests). All include the shared "My open tasks".
- **QC Minute:** `Minute/DashboardController` (replaced `Route::inertia('/')`) — PMs get
  timesheets-to-approve + open-staffing + approval list + weekly-hours chart; contractors fall
  back to the existing link-cards (`widgets: null`).
- **Front-end primitives:** `views/admin/dashboard/widgets/{StatCard,ListCard,TrendChart}.tsx`
  (TrendChart wraps the theme `ApexChart`). Both dashboard pages compose whatever widgets arrive.
- **New scopes:** `Contract::scopeExpiringWithin`, `Timesheet::scopePendingApproval`,
  `Invoice::scopeAwaitingSend`, `SupplyRequest::scopePending`.
- **Tests:** `tests/Feature/DashboardTest.php` (7). **Seed:** demo logins
  `office_manager@ / payroll@ / hr@ / super_admin@example.com` (password `password`).

## Context

Phases 01–05 built deep capability but almost no surface that pulls it together. Both
landing pages are near-empty: the back office shows only a "pending tasks" count, and QC
Minute is four static link-cards. Phase 06 turns them into **role-aware dashboards** that
read existing data and deep-link into the pages already built — the glue that makes the
prior phases usable, and the validation gate before building more.

## Scope decisions (locked with owner)

1. v1 widgets = **counts + "needs my attention" lists + a few trend charts** (ApexCharts,
   lightweight inline weekly aggregation; Phase 09 may later formalize via rollups).
2. **Contractor dashboard deferred to Phase 08** (paychecks + KB don't exist yet). QC Minute
   this phase = the **Property Manager** dashboard; contractors keep the current link-cards.
3. Read-only — widgets surface and link out; no new mutations.

## Approach

Widget-composition, not per-role pages: a `DashboardMetrics` service assembles only the
widgets a user should see based on their **roles + permissions** (already shared via
`HandleInertiaRequests` as `auth.roles`/`auth.permissions`). One back-office page and one
QC Minute page render whatever widgets arrive — so multi-role/admin users get the superset.
Property-scoped widgets reuse `Property::scopeAssignedTo` unless the user has a
`PropertyPolicy::GLOBAL_ROLES` role.

## Reuse

`WorkflowStep::openForPerson`, `WorkOrder::scopeActive`, `Person::scopePrimaryContractorsOf`,
`Person::outstandingChargeBalance`, `ItemVariant::stockStatus`, all status enums; new scopes
`Contract::scopeExpiringWithin`, `Timesheet::scopePendingApproval`, `Invoice::scopeAwaitingSend`,
`SupplyRequest::scopePending`. Front-end: `ApexChart` wrapper, `Icon`, `CountUp`, `.card`.

## Increments

0. **Infra** — this doc; new model scopes; front-end widget primitives
   (`StatCard`/`ListCard`/`TrendChart`).
1. **Back-office** — `DashboardMetrics::forBackOffice` + `DashboardController` + compose
   `views/admin/dashboard/index.tsx` (recruiter / office_manager / payroll / hr / super_admin
   / admin / front_desk / w2_employee; weekly hours + revenue charts).
2. **QC Minute PM** — `Minute/DashboardController` (replaces `Route::inertia('/')`) +
   `DashboardMetrics::forPropertyManager` + role-aware `views/minute/dashboard/index.tsx`
   (contractor falls back to link-cards).
3. **Tests + docs + seed** — `DashboardTest`; demo users per role; gates; this doc + roadmap.

## Out of scope (deferred)

Contractor dashboard (Phase 08); materialized rollups + report catalog (Phase 09); a
"currently clocked in" live widget (Phase 07); dashboard-initiated mutations; per-widget
filtering/customization.

## Related

`80-plan/roadmap.md`; ADR-0025 (domain contexts); `phase-05-import.md`.
