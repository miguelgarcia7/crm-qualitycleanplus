# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

One Laravel 13 app (PHP 8.4, MySQL) that replaces two legacy apps for Quality Cleaning Plus: the QC Minute
timesheet app and the QCP back office. `docs/` is the full spec and plan; start with `docs/README.md`.
Architectural decisions live in `docs/70-decisions/` as ADRs. ADRs are immutable: to change one, write a
new ADR that supersedes it instead of editing the old one.

## Commands

Herd's default `php` is 8.5, but the project targets 8.4. Run PHP tooling through `php84`
(`/Users/miguelgarcia/Library/Application Support/Herd/bin/php84`).

```bash
php84 artisan test                                   # full Pest suite (SQLite :memory:, array session/cache/mail)
php84 artisan test tests/Feature/WorkOrderTest.php   # one file
php84 artisan test --filter="part of a test name"    # one test by name
php84 vendor/bin/pint --dirty                        # format changed PHP files (Laravel preset)
php84 vendor/bin/phpstan analyse --memory-limit=512M # Larastan level 5 (app, database, routes)
npm run types                                        # tsc --noEmit
npm run build                                        # both bundles: admin (vite.config.ts) + marketing (vite-site.config.ts)
npm run dev / npm run dev:site                       # HMR for the admin bundle / the marketing bundle
php84 artisan migrate:fresh --seed                   # roles/permissions, a super admin, sample data
php84 artisan demo:reset / demo:simulate             # Acme Hotel demo (only with DEMO_MODE=true); see docs/demo-guide.md
```

`composer test` runs `pint --test` and then the suite. Never run `prettier --write`: there is no Prettier
config, and it reformats whole files against the house style.

## Local hosts

Herd serves the app on two `.test` hosts. `php artisan serve` cannot route between domains.

- `https://qcpminute.test`: marketing site at `/`, back office at `/admin` (`DOMAIN_MAIN`)
- `https://qcminute.test`: QC Minute at `/`, plus `/clock-in/*` and `/device/*` (`DOMAIN_QCMINUTE`)

After any change to routes or `bootstrap/app.php`, run `php84 artisan optimize:clear` (and `herd restart`
if needed). Herd's profiler hides real app 500s behind a `$__herd_closure` error. To see the actual
exception, reproduce it with `php artisan serve`. Keep route files closure-free so the routes stay cacheable.
Seeded dev logins are `*@example.com` / `password` (see `docs/10-architecture/local-setup.md`).

## Architecture

**Two domains, three surfaces, one codebase (ADR-0001/0023/0024).** `routes/web.php` mounts the route files
with `Route::domain(config('domains.*'))`. Domain names always come from config and are never hardcoded.
`AllowedOnBackoffice` and `AllowedOnQcMinute` decide which roles may use each surface. `super_admin` can
use both.

| Surface | Routes | Rendering |
|---|---|---|
| Marketing (public, EN + `/es`) | `routes/marketing.php` | Blade in `resources/views/site/`. Separate Vite bundle in `resources/{css,js}/site` → `public/site-build`, loaded by the `UseMarketingVite` middleware |
| Back office | `routes/backoffice.php` | Inertia + React, pages in `resources/js/admin/views/admin/` |
| QC Minute | `routes/qcminute.php` | Inertia + React, pages in `resources/js/admin/views/minute/` |
| Clock-in / tablet | `routes/clock-in.php`, `routes/device.php` | Public QR clock-in (phone + GPS + selfie). The tablet kiosk uses a Sanctum device token and is exempt from CSRF |

Back office and QC Minute share one React bundle on purpose (`resources/js/admin/app.tsx`, alias `@` →
`resources/js/admin`). Where ADR-0024 says "three bundles", that text is out of date. Inertia page names
match paths under `views/` (`Inertia::render('admin/properties/index')`). Wayfinder generates typed
routes and actions into `resources/js/admin/{actions,routes,wayfinder}`. Those folders are gitignored, so
never edit them by hand.

**Backend by domain context (ADR-0025).** Business code lives in `app/Domain/<Context>/` (Models, Enums,
Actions, Policies, Scopes, …). `app/Domain/README.md` lists each context and what it owns. Controllers in
`app/Http/Controllers` stay thin: authorize, validate with a Form Request, call one Action, then return
Inertia or redirect. Wiring that's easy to miss:
- Policies are registered by hand with `Gate::policy()` in `AppServiceProvider`.
- Factories live in `database/factories`, and a `guessFactoryNamesUsing` resolver maps each domain model
  to its factory.
- The auth model is `App\Domain\People\Models\Person`, not `User`. One `people` table with a status
  (applicant/contractor/staff, ADR-0004) holds every human.

**No multi-tenancy (ADR-0002).** No global scope filters rows. Each query handles its own access, through
policies plus scoped builders such as `Property::accessibleBy($user)`.

**Money and time invariants.** Time entries snapshot their rates (ADR-0005). Work orders are the
authoritative rate source, and property rates are effective-dated. Saving a time entry recomputes
`time_summaries` through `RecomputeTimeSummary`, which in turn refreshes report rollups (ADR-0008/0028).
Timesheets move through a staged approval state machine (ADR-0007). A sent invoice is frozen: changes
mean void and reissue, never editing it in place (ADR-0006). Payroll periods are first-class records
(ADR-0009).

**Workflows (ADR-0026).** All workflows share the `workflows`/`workflow_steps` tables and one My Tasks
inbox. Each workflow type is a PHP `WorkflowDefinition` class kept in its owning context and resolved
through a registry. Workflow definitions are code, never database rows.

**Lists (ADR-0029).** Ledger lists (timesheets, invoices, people, audit log, …) paginate on the server.
Bounded config lists (departments, positions, properties, …) filter on the client.

**Permissions.** Spatie Permission. Roles and permissions are seeded from `RolePermissionSeeder`, which
mirrors `docs/10-architecture/permissions-matrix.md`. Admin → System Reference renders live catalogs from
`app/Domain/SystemReference/Support`, and `SystemReferenceTest` fails when they fall out of sync:
- A new permission goes into the seeder and the matrix doc. If its label reads badly, add it to
  `PermissionCatalog::LABELS`.
- A new notification class needs an entry in `NotificationCatalog`.
- A new scheduled task in `routes/console.php` needs a `->description()`.
- After adding a permission, re-run the seeder on staging.

**Inertia shared props.** `HandleInertiaRequests` shares `name`, `auth`, `surface` and `notifications`
with every page. Never give a page prop one of those names. Overriding `notifications`, for example,
crashes the layout and leaves a blank page.

## Frontend / UI

- Reuse the Paces/Minute theme library before building anything. It's symlinked at `./design-reference`
  (gitignored). Find the matching demo and adapt its markup and classes, following the HRM patterns
  (DataTable, tabs, profile cards). Classes like `btn-soft-*` and `badge-soft-*` don't exist; use
  `bg-x/15` tint classes instead.
- ADR-0027: Preline (data attributes + `autoInit`) is for stateless chrome such as tabs, dropdowns and
  accordions. Anything bound to React state or a form (modals, `SidePanel`, value-bound selects, date
  pickers) is a React-controlled component.
- The back office uses the Sage theme (`docs/50-ui/theme-sage.md`): warm high-contrast grays, 24px page
  titles, tabs under the title. Leave the dark sidebar as it is.

## Tests

Pest 4. Feature tests use `RefreshDatabase` and seed what they need, usually
`beforeEach(fn () => $this->seed([RolePermissionSeeder::class, …]))`. Routes are bound to domains, so build
URLs with the helpers in `tests/Pest.php`: `main('/admin/...')` and `qcminute('/...')`. `person('role')`
creates a `Person` with that role, and `applicationPayload()` returns a valid marketing job application.

## Workflow

- Start a new branch off `main` for each round of edits and never commit to `main` directly. Push only
  when asked. Commit subjects use the form `Area: what changed` (e.g. `Timesheet grid: …`).
- Before adding a dependency, justify it against what the current spec needs, and install it in the
  phase that uses it.

## Git commit conventions
- **Never add `Co-Authored-By: Claude ...` (or any AI assistant) trailer to commit messages.**
- Do not include AI-attribution footers or sign-offs of any kind.
- Don't make commits comment too verbose. Keep them to a single line when possible.
