# Architecture Overview

| Field | Value |
|---|---|
| Status | Accepted |
| Last updated | 2026-06-06 |
| Owner | Engineering |

## The shape in one diagram

```
ONE LARAVEL 13 APPLICATION  ·  ONE MySQL DATABASE
(same models · migrations · policies · jobs · one deployment)

 ├─ qualitycleanplus.com         MARKETING        Blade (server-rendered, SEO)      bundle: site
 │     public pages · job listings · application form
 │     (form POST → person with status='applicant', written in-app)
 │
 ├─ qcminute.com                 TIME TRACKING    React + Inertia                   bundle: contractor (planned)
 │     roles: Property Manager · Contractor (RO) · Admin · Super Admin
 │     live weekly grid · approve timesheet · invoice list/view · send invoice
 │     contractor self-service · KB (role-gated) · QR clock-in (GPS + selfie)
 │
 ├─ qcminute.com/device/*        TABLET CLOCK-IN  Sanctum token auth (backup flow)
 │     property-locked clock in/out endpoints
 │
 └─ backoffice.qcpstaffing.com   INTERNAL OPS     React + Inertia (under /admin)    bundle: admin
       roles: Admin · Super Admin · Office Manager · Front Desk · HR · Payroll
              · Recruiter · W-2 Employee (PTO/KB only)
       Property Bible · dashboards · import wizard · workflows · applicants/job postings
       KB authoring · PTO admin · reports · inventory (Front Desk) · audit (Admin/SA)
       · field check-in (FAB)

Each surface compiles its own asset bundle (Vite multi-entry) — see "Surfaces & asset bundles".
The public marketing site is part of THIS codebase (a Blade surface), not a separate app — see ADR-0023.
```

## Stack

| Layer | Choice | Why |
|---|---|---|
| Language | PHP 8.4+ | Laravel 13 requires 8.3+; we target 8.4 for language features (property hooks, asymmetric visibility) |
| Framework | Laravel 13 | Latest, long-term support |
| Database | MySQL 8 | Operational continuity from legacy systems |
| Queue / cache | Redis | Background jobs for time_summary recomputation, notification dispatch, retention purges |
| Frontend | React 19 + Inertia 2 + TypeScript for the app surfaces (Vite 7, Tailwind v4, Paces/Minute theme); **Blade** for the public marketing site | App surfaces are server-driven SPAs (Inertia, no separate API); marketing is server-rendered HTML for SEO. Per-surface asset bundles. Mobile-responsive throughout |
| Auth | Laravel Fortify (headless: login, password reset, 2FA scaffolding) | Sanctum added later only for the `/device/*` tablet token path |
| Permissions | Spatie Permission | Battle-tested, drives both UI gating and policy enforcement |
| Real-time | Laravel Reverb + Echo (deferred to Phase 03) | First-party WebSocket server; live clock-in widget, live weekly grid for PMs |
| Storage | S3 (production), local (dev) | Documents, KB attachments, contractor photos, contracts |
| Mail | Postmark | Transactional notifications |
| Testing | Pest 4 | Faster setup, expressive syntax |

## How the surfaces share one codebase + database

The surfaces are **not separate applications**. They are route groups in one Laravel app, mounted per domain:

```php
// Marketing — public, server-rendered Blade (no auth)
Route::domain('qualitycleanplus.com')
    ->group(base_path('routes/public.php'));

Route::domain('qcminute.com')
    ->middleware(['auth', 'allowed_on_qcminute'])
    ->group(base_path('routes/qcminute.php'));

Route::domain('backoffice.qcpstaffing.com')
    ->middleware(['auth', 'allowed_on_backoffice'])
    ->group(base_path('routes/backoffice.php'));

Route::domain('qcminute.com')
    ->prefix('device')
    ->middleware('auth:sanctum')
    ->group(base_path('routes/device.php'));
```

- Same models, services, policies, jobs
- Same migrations
- Same deployment (one CI/CD pipeline)
- Domain-aware middleware controls *who* can be on *which* domain
- Super Admin can be on either authenticated domain; everyone else is on their assigned one

See `10-architecture/domain-routing.md` for the detailed routing pattern.

### Surfaces & asset bundles

Each surface compiles its **own** asset bundle via Vite multi-entry, so bundles don't bleed into each other and the marketing pages stay lightweight and crawlable:

| Surface | Render | Entry / bundle | Status |
|---|---|---|---|
| Back office (`/admin`) | React + Inertia | `resources/js/admin/app.tsx` → `admin` | Built (scaffold) |
| Marketing | Blade | `resources/css/site/app.css` + `resources/js/site/app.js` → `site` | Planned |
| qcminute (contractor/PM) | React + Inertia | `resources/js/contractor/app.tsx` → `contractor` | Planned |

Per-surface assets also live in per-surface folders: `resources/{css,js,images,data}/admin/` today, with `site/` and `contractor/` siblings added as those surfaces are built.

## What's NOT in this architecture

- **No multi-tenancy.** No `tenant_id` column anywhere. Row-level access is via permissions and role-scoped relationships. See ADR-0002.
- **No external API layer between the surfaces.** They share the database directly; no REST or webhook bridge needed.
- **No microservices.** One Laravel app, one DB, one deployment.
- **No native mobile app.** Browser-based responsive UI. Tablet devices run a browser-loaded SPA-ish page authenticated via Sanctum tokens.
- **No GraphQL.** Standard Laravel routes + controllers. App surfaces return Inertia (React) responses; the marketing surface returns server-rendered Blade for SEO.

## Data isolation strategy

Different roles see different rows of the same tables. This is enforced at three layers:

1. **Policies** — every authorize() check applies role + ownership rules (e.g. recruiters see only their assigned properties)
2. **Scoped query builders** — controllers call `Property::accessibleBy($user)` instead of `Property::all()`
3. **UI gating** — Inertia/React conditionals (driven by shared props) or middleware route guards prevent rendering links to inaccessible resources

There is no global tenant scope. Each query is responsible for its own access scoping via the patterns above.

## Background processing

| Job type | Trigger | Purpose |
|---|---|---|
| `RecomputeTimeSummary` | After `time_entry` insert/update/delete | Re-aggregate the affected (contractor, work order, week) into `time_summaries` |
| `RecomputeDailyPropertySummary` | After `time_summary` update | Roll up to daily property-level dashboards |
| `SendNotification` | Various model events | Mail + database + broadcast notifications |
| `RetentionPurge` | Daily scheduler | Hard-delete soft-deleted PII past retention, skipping legal holds |
| `ContractExpirationCheck` | Daily scheduler | Surface contracts expiring in 30 / 14 days |
| `StaleTimesheetCheck` | Daily scheduler | Surface draft timesheets that haven't been sent for approval |
| `ImportApplyJob` | User action (commit import wizard) | Apply parsed import_batch rows as time_entries in a transaction |

## Related

- `10-architecture/deployment-topology.md` — what gets deployed where
- `10-architecture/domain-routing.md` — Laravel routing pattern details
- `10-architecture/identity-and-auth.md` — login, sessions, devices
- `10-architecture/permissions-matrix.md` — roles × capabilities
- ADR-0001 — One app, two domains (refined by ADR-0023)
- ADR-0002 — No multi-tenancy
- ADR-0003 — Fresh build on Laravel 13
- ADR-0022 — Frontend stack: React + Inertia + Fortify
- ADR-0023 — Marketing site in-monorepo as a Blade surface
