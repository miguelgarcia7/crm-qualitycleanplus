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
| `Time` | Payroll periods, time entries, time summaries, `RecomputeTimeSummary` bucketing, `TimeEntrySaved` broadcast |
| `Billing` | Timesheets (approval state machine), invoices + items, `GenerateInvoice` / `SendInvoice` |
| `Shared` | Generic primitives reused across contexts (e.g. the polymorphic `File` model) |

Added as their phases land: `Workflows`, `Inventory`, `Reporting`, …

## Wiring notes

- Policies are registered with `Gate::policy()` in `AppServiceProvider` (auto-discovery
  only works for `App\Models`).
- Factories live in `database/factories` (basename-named); a `Factory::guessFactoryNamesUsing()`
  resolver in `AppServiceProvider` maps a domain model → its factory, and each factory sets `protected $model`.
