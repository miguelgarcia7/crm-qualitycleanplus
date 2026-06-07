# ADR-0001: One App, Two Domains

| Field | Value |
|---|---|
| Status | Accepted |
| Decided | 2026-05-21 |
| Owner | Product + Engineering |
| Supersedes | — |
| Superseded by | — |
| Refined by | ADR-0023 (marketing site moves into this codebase as a Blade surface); ADR-0024 (concrete two-domain layout: qualitycleanplus.com + qcpstaffing.com; back office at /admin) |

## Context

QCP has two operational systems today:

- **QC Minute** (Laravel 10, Vue SPA) — time tracking and invoicing
- **QCP CRM** (Laravel 11, Blade) — internal back office (HR, properties, inventory, PTO, KB)

They have separate codebases, separate databases, and overlapping concepts (Property exists in both with no FK linking them). Data drifts between systems. Reporting across both requires manual exports.

The original plan was to keep them separate but link them via an internal API, with QC Minute eventually becoming multi-tenant SaaS sold to hotels. That plan is being abandoned (see ADR-0002).

With multi-tenancy off the table, the question becomes: should we keep two apps + DBs and bridge them via API, merge them into one app + DB, or take a hybrid approach?

## Decision

**One Laravel application, one MySQL database, two domains routed within the same codebase.**

```
qcminute.com               → Property Manager + Contractor UI
backoffice.qcpstaffing.com → All QCP staff UI
qcminute.com/device/*      → Tablet clock-in endpoints
```

Both domains share:
- The same Eloquent models
- The same migrations
- The same deployment pipeline
- The same authentication backend

They differ in:
- Which routes are mounted (per domain via `Route::domain()`)
- Which Blade layout wraps the page (per-domain layout)
- Which roles are allowed (per-domain middleware)
- Branding and navigation

The **public marketing site** stays as a separate Laravel app at `qualitycleanplus.com`. It is not being rebuilt as part of this project.

## Consequences

### Positive

- One canonical Property, one canonical Person, one canonical Work Order — no sync, no drift
- One codebase to maintain, upgrade, test, and deploy
- Reports can join across all concerns without cross-system queries
- New cross-cutting features (workflow engine, audit log, KB integration) land everywhere at once
- A super admin sees a consistent system, not two siloed views
- Schema migrations always atomic — no "remember to also run this on the other DB"

### Negative

- One codebase means slower deploys when the app grows large (acceptable trade)
- A bug deployed to production affects all surfaces simultaneously (mitigated by staged rollout + feature flags)
- Domain-aware middleware logic adds a small mental overhead for new contributors
- We give up the option of selling QC Minute separately without significant refactoring

### Implementation requirements

- `routes/qcminute.php`, `routes/backoffice.php`, `routes/device.php`, `routes/public.php` as separate route files
- `bootstrap/app.php` wires each route file to its domain via `Route::domain()`
- Two layout files: `resources/views/layouts/qcminute.blade.php`, `resources/views/layouts/backoffice.blade.php`
- Two middleware: `AllowedOnQcMinute`, `AllowedOnBackoffice`
- Local dev requires hosts-file entries + Valet/Herd linking per domain

## Alternatives considered

### A. Keep two apps, bridge with internal API

Rejected. Adds operational complexity (two deploys, two DBs, two test suites, API contract maintenance), adds latency to cross-cutting reads, and creates the exact "drift between systems" problem the rebuild is meant to solve.

### B. Merge into one app, one domain

Rejected. Property managers and contractors should not see the back-office UI even if they're somehow authorized. The two-domain split provides a hard UX boundary even if the underlying data is shared. Also gives super admins a clear "I'm in PM mode" vs "I'm in back-office mode" context.

### C. Microservices

Rejected. Massive overkill for a single small company's operational system. One Laravel app is the right scale.

## Related

- ADR-0002 — No multi-tenancy (this decision became possible because of that one)
- ADR-0003 — Fresh build on Laravel 13
- `10-architecture/overview.md`
- `10-architecture/domain-routing.md`
- `10-architecture/deployment-topology.md`
