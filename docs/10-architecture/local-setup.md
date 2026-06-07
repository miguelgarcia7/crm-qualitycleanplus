# Local Setup

| Field | Value |
|---|---|
| Status | Living |
| Last updated | 2026-06-07 |
| Owner | Engineering |

First-run guide for the unified app on a local machine (Laravel Herd).

## Prerequisites

- PHP 8.4+, Composer, Node 20+, MySQL 8, **Laravel Herd**.

## Two local hostnames (one app, two domains — ADR-0024)

Herd serves everything at `.test`. Point both surface hostnames at this app:

```bash
cd <project>
herd link qcminute      # this app at qcminute.test  (= qcpstaffing.com / QC Minute)
herd secure qcminute
herd secure qcpminute   # main host (= qualitycleanplus.com / marketing + /admin)
```

`.env` (copied from `.env.example`):

```dotenv
APP_URL=https://qcpminute.test
DOMAIN_MAIN=qcpminute.test       # marketing "/" + back office "/admin"
DOMAIN_QCMINUTE=qcminute.test    # QC Minute
```

## Install + boot

```bash
composer install
cp .env.example .env && php artisan key:generate   # if no .env yet
php artisan migrate:fresh --seed                    # schema + roles/permissions + a super admin
npm install && npm run build                        # or: npm run dev
php artisan optimize:clear                          # after any routing/bootstrap change
```

> **Herd note:** after changing routes or `bootstrap/app.php`, run `php artisan optimize:clear` and `herd restart` — Herd caches a route/app dump, and stale closures surface as `$__herd_closure` errors. Keep route files closure-free (`Route::view` / `Route::inertia` / controllers) so they stay cacheable.

## Background services (Phase 03+)

The live timesheet grid broadcasts over **Laravel Reverb**, and summary recompute
+ notifications run on the **queue**. For the full experience locally, run:

```bash
php artisan reverb:start    # websocket server (live grid updates)
php artisan queue:work      # notifications, broadcasts
php artisan schedule:work   # payroll-period creation, contract-expiration alerts
```

The app works without these — the grid still loads/refreshes on navigation, and
the time-summary recompute runs inline (dispatchSync) — but live push, emails,
and scheduled jobs need the workers running.

## Seeded logins (dev only)

`migrate:fresh --seed` provisions a super admin, and (non-production only) a
`SampleDataSeeder` scenario: one property with Bible rates, contractors on work
orders, and a week of hours.

| Email | Password | Role / use |
|---|---|---|
| `super-admin@example.com` | `password` | Super admin — both domains |
| `recruiter@example.com` | `password` | Recruiter (assigned to the sample property) — back office |
| `pm@example.com` | `password` | Property manager — QC Minute timesheet approval |

Surfaces:
- Back office → `https://qcpminute.test/admin`
- QC Minute → `https://qcminute.test/`

## Quality gates

```bash
composer test    # Pint (--test) + Pest
composer stan    # Larastan (level 5)
composer lint    # Pint (apply)
npm run types    # tsc --noEmit (frontend type-check)
npm run build    # production asset build
```

## Related

- `10-architecture/domain-routing.md` — routing pattern
- `70-decisions/0025-backend-domain-driven-structure.md` — `app/Domain` layout (+ `app/Domain/README.md`)
- `80-plan/roadmap.md` — phase status
- `80-plan/phase-01-foundation.md`, `phase-02-property-bible.md`, `phase-03-work-orders-time-invoicing.md` — what's built
