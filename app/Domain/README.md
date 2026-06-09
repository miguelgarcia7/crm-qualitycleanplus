# app/Domain

Backend business code is organized **by context** (Spatie "Laravel: Beyond CRUD"),
not in fat controllers. See ADR-0025. Each context folder holds, as needed:

```
<Context>/
├── Models/      Eloquent models
├── Enums/       backed enums
├── Actions/     one Action per write operation (CreateProperty, AddPropertyPositionRate, …)
├── Policies/    authorization
├── Concerns/    context-specific traits
└── Scopes/      query scopes
```

Controllers (`app/Http/Controllers/…`) stay thin: authorize → validate via a Form
Request (`app/Http/Requests/…`) → call one Action → return an Inertia response or redirect.

## Contexts

| Context | Covers |
|---|---|
| `People` | `Person` (the one identity spine for every human — applicant/contractor/staff), `PersonStatus`, legal-hold + lifecycle |
| `PropertyBible` | Properties, departments, positions, per-property rates (effective-dated), contracts, property assignments |
| `WorkOrders` | Work orders (contractor↔property↔position + rates); authoritative rate source for time entries |
| `Time` | Payroll periods, time entries, time summaries, `RecomputeTimeSummary` bucketing, `TimeEntrySaved` broadcast; QR clock in/out (`ClockInContractor`/`ClockOutContractor`, GPS + selfie, Phase 07a) |
| `Billing` | Timesheets (approval state machine), invoices + items, `GenerateInvoice` / `SendInvoice` |
| `Workflows` | Generic workflow engine (ADR-0026): `Workflow`/`WorkflowStep`, code-defined `WorkflowDefinition`s + registry, engine Actions, shared My Tasks surface |
| `Inventory` | Unified inventory (ADR-0012): categories/items/variants, stock movements, purchase orders, equipment assignments, supply requests, contractor charge schedules |
| `Adjustments` | Payroll incentives/deductions (`adjustment_items`, `time_entry_adjustments`); billable incentives flow to invoices, deductions are payroll-only |
| `Imports` | Excel hour import for import-only properties (Phase 05): `ImportBatch`/`ImportBatchRow`, `HourImportParser`, `CreateImportBatch`/`CommitImport`/`RollbackImport`; produces imported time entries → auto-approved timesheet → frozen invoice |
| `Dashboards` | Role-aware dashboard read service (Phase 06): `DashboardMetrics` assembles property-scoped stat/list/chart widgets from across contexts for the back office + QC Minute PM landing pages |
| `FieldVisits` | Recruiter visit check-in/out (Phase 07b, ADR-0017): `FieldVisit` + `CheckInRecruiter`/`CheckOutRecruiter` (GPS + selfie, informational geofence, forgot-to-check-out) — accountability logging, not billable time |
| `Devices` | Front-desk tablet kiosk (Phase 07c, ADR-0017): `Device` (Sanctum-paired to a property) + `ActivateDevice`; the kiosk reuses the Time clock actions (`clock_method=tablet`, no geofence) |
| `Shared` | Generic primitives reused across contexts (e.g. the polymorphic `File` model) |

Added as their phases land: `Reporting`, …

## Wiring notes

- Policies are registered with `Gate::policy()` in `AppServiceProvider` (auto-discovery
  only works for `App\Models`).
- Factories live in `database/factories` (basename-named); a `Factory::guessFactoryNamesUsing()`
  resolver in `AppServiceProvider` maps a domain model → its factory, and each factory sets `protected $model`.
