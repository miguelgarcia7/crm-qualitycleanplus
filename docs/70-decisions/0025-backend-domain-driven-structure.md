# ADR-0025: Backend Code Organized by Domain (app/Domain)

| Field | Value |
|---|---|
| Status | Accepted |
| Decided | 2026-06-07 |
| Owner | Engineering |
| Refines | ADR-0022 |
| Supersedes | — |
| Superseded by | — |

## Context

ADR-0024 settled the **frontend/routing** layout (two domains, three surfaces, per-surface bundles) but said nothing about how **backend PHP** is organized. Phase 01 shipped with the default flat Laravel layout (`app/Models`, `app/Enums`, `app/Policies`, …), and Phase 02 initially followed suit.

The sibling reference app (`mintra.site`, the Paces/Minute build this project is patterned on) organizes its backend by **business context** under `app/Domain/` following Spatie's "Laravel: Beyond CRUD": `app/Domain/<Context>/{Models,Actions,Policies,Enums,Concerns,Scopes,Data}`, with thin controllers in `app/Http` that validate via a Form Request → call one Action → return Inertia.

As the system grows toward ~10 phases (work orders, time, invoicing, workflows, inventory, reporting, …), a flat `app/Models` with dozens of unrelated models becomes hard to navigate. Grouping by context keeps each area's models, authorization, and write-logic together.

The two apps are **similar but not identical** — mintra is multi-tenant (agencies); QCP is single-org with two brand domains. So mintra is a **structural reference only**; QCP defines its own contexts.

## Decision

**Organize backend business code by domain context under `app/Domain/`.**

```
app/Domain/<Context>/
├── Models/      Eloquent models
├── Enums/       backed enums for that context
├── Actions/     one Action class per write operation (Create…, Update…, Add…, Upload…)
├── Policies/    authorization
├── Concerns/    context-specific traits
└── Scopes/      query scopes (as needed)
```

**Controllers stay thin** in `app/Http/Controllers/…` and **Form Requests** in `app/Http/Requests/…`. A controller action: authorize → validate (Form Request) → call one Action → return an Inertia response or redirect. Cross-cutting/generic models (e.g. polymorphic `File`) live in a `Shared` context.

### QCP contexts (initial)

| Context | Covers |
|---|---|
| `People` | `Person` (identity spine), `PersonStatus`, legal-hold + lifecycle |
| `PropertyBible` | Properties, departments, positions, per-property rates, contracts, assignments |
| `Shared` | Generic primitives reused across contexts (e.g. `File`) |

More contexts are added as their phases land (e.g. `WorkOrders`, `Time`, `Invoicing`, `Workflows`, `Inventory`, `Reporting`).

### Integration notes

- **Autoloading:** the default `"App\\": "app/"` PSR-4 map already covers `App\Domain\…` — no composer change.
- **Policies** are registered explicitly via `Gate::policy()` in `AppServiceProvider` (Laravel's auto-discovery only guesses `App\Policies` from `App\Models`).
- **Factories** stay in `database/factories` (basename-named). A `Factory::guessFactoryNamesUsing()` resolver maps a domain model to its factory by basename, and each factory sets `protected $model`.

## Consequences

### Positive

- Related code (model + policy + write Actions + enums) lives together per context.
- Thin controllers; business/write logic is testable in isolation (Actions).
- Scales cleanly as phases add contexts; mirrors the reference app's conventions.

### Negative / cost

- Policies and factory resolution need explicit wiring (one-time, done).
- Slightly more files per write operation (an Action class each) vs. fat controllers.
- A one-time migration of Phase 01/02 code into `app/Domain` (completed in Phase 02).

## Related

- `app/Domain/README.md` — the living context map
- ADR-0022 (frontend stack), ADR-0024 (two-domain layout)
- `80-plan/phase-02-property-bible.md`
