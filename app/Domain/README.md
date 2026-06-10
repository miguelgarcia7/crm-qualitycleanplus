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
| `Pto` | W-2 PTO (Phase 08a, ADR-0016): tier-based accrual on hire-anniversary cycles, three buckets, deduct-on-submission, HR self-approval guardrail; `PtoYearAllotment`/`PtoGrant`/`PtoRequest`, `PtoTenure`, `ProcessPtoTenureCrossings` daily job |
| `Recruiting` | Job board + applications (Phase 08b, ADR-0023): `JobPosting` (advertised openings — distinct from the Property-Bible `positions` catalog) and `JobApplication` (one immutable submission per applicant, with at-the-time legal attestations + review/promotion trail); `SubmitApplication` writes durable facts to a `Person(status=applicant)`; back office manages postings + the review queue, and `People` actions promote/reverse (onboarding-checklist gated) |
| `Marketing` | Marketing-site data (Phase 08b-i): `ContactInquiry` — Job Seeker + Business leads from the public contact forms |
| `KnowledgeBase` | Versioned KB articles (Phase 08c): `KbArticle` (immutable `KbArticleVersion` snapshot before every edit), hierarchical `KbCategory`, auto-created `KbTag`, role-based visibility via `kb_article_role` (zero roles = all authed readers; super_admin bypass), `KbSearch` (FULLTEXT/LIKE), `SubmitKbFeedback` vote dedupe; readers on both surfaces |
| `Shared` | Generic primitives reused across contexts (e.g. the polymorphic `File` model, the polymorphic `Feedback` model + `FeedbackType`) |

Added as their phases land: `Reporting`, …

## Wiring notes

- Policies are registered with `Gate::policy()` in `AppServiceProvider` (auto-discovery
  only works for `App\Models`).
- Factories live in `database/factories` (basename-named); a `Factory::guessFactoryNamesUsing()`
  resolver in `AppServiceProvider` maps a domain model → its factory, and each factory sets `protected $model`.
