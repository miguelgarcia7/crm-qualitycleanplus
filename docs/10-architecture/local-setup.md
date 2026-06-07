# Local Setup

| Field | Value |
|---|---|
| Status | Living |
| Last updated | 2026-06-06 |
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

## Seeded super admin (dev only)

| Email | Password |
|---|---|
| `admin@qcpstaffing.com` | `password` |

Can log in on **either** domain:
- Back office → `https://qcpminute.test/admin`
- QC Minute → `https://qcminute.test/`

## Quality gates

```bash
composer test    # Pint (--test) + Pest
composer stan    # Larastan (level 5)
composer lint    # Pint (apply)
```

## Related

- `10-architecture/domain-routing.md` — routing pattern
- `80-plan/phase-01-foundation.md` — what's built
