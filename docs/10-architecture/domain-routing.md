# Domain Routing

| Field | Value |
|---|---|
| Status | Accepted |
| Last updated | 2026-06-06 |
| Owner | Engineering |

## Pattern

Laravel's `Route::domain()` lets us mount different route trees on different hostnames within the same app. There are **two domains** and **three surfaces** (see ADR-0024):

- **`qualitycleanplus.com`** — marketing at `/` (public Blade) **and** the back office at `/admin` (React/Inertia)
- **`qcpstaffing.com`** — QC Minute at `/` (React/Inertia, PMs + contractors) and tablet clock-in at `/device/*`
- Super admins can use either authenticated surface

Domain names are **never hardcoded** — they come from config so local (`.test`) and production (`.com`) differ.

## Route files

```
routes/
├── marketing.php       ← qualitycleanplus.com "/"      (public Blade: pages, job listings, application form)
├── backoffice.php      ← qualitycleanplus.com "/admin"  (React/Inertia; staff roles)
├── qcminute.php        ← qcpstaffing.com "/"            (React/Inertia; PM + contractor + super-admin)
├── device.php          ← qcpstaffing.com "/device/*"    (Sanctum tablets)
├── web.php             ← shared infrastructure: auth scaffolding (Fortify), error pages
└── console.php         ← schedule definitions
```

## Config

`config/domains.php`:

```php
return [
    'main'     => env('DOMAIN_MAIN', 'qualitycleanplus.com'),   // marketing + back office
    'qcminute' => env('DOMAIN_QCMINUTE', 'qcpstaffing.com'),    // qcminute + device
];
```

```dotenv
# local (.env)                          # production (.env)
DOMAIN_MAIN=qcpminute.test              DOMAIN_MAIN=qualitycleanplus.com
DOMAIN_QCMINUTE=qcminute.test           DOMAIN_QCMINUTE=qcpstaffing.com
```

## Bootstrap

In `bootstrap/app.php` (`->withRouting(... then:)`):

```php
then: function () {
    // DOMAIN 1 — qualitycleanplus.com
    Route::domain(config('domains.main'))->middleware('web')->group(function () {
        // Marketing — public, server-rendered Blade, at "/"
        Route::group([], base_path('routes/marketing.php'));

        // Back office — React/Inertia, at "/admin"
        Route::prefix('admin')
            ->middleware(['auth', 'allowed_on_backoffice'])
            ->group(base_path('routes/backoffice.php'));
    });

    // DOMAIN 2 — qcpstaffing.com
    Route::domain(config('domains.qcminute'))->middleware('web')
        ->middleware(['auth', 'allowed_on_qcminute'])
        ->group(base_path('routes/qcminute.php'));

    Route::domain(config('domains.qcminute'))
        ->prefix('device')
        ->middleware('auth:sanctum')
        ->group(base_path('routes/device.php'));
},
```

## Domain access middleware

Two middleware classes enforce "right person on the right surface":

```php
// app/Http/Middleware/AllowedOnQcMinute.php  (alias: allowed_on_qcminute)
abort_unless(
    $request->user()?->hasAnyRole(['property_manager', 'contractor', 'admin', 'super_admin']),
    403, 'This account cannot access QC Minute.'
);

// app/Http/Middleware/AllowedOnBackoffice.php (alias: allowed_on_backoffice)
abort_unless(
    $request->user()?->hasAnyRole([
        'super_admin', 'admin', 'office_manager', 'front_desk',
        'hr', 'payroll', 'recruiter', 'w2_employee',
    ]),
    403, 'This account cannot access the back office.'
);
```

Marketing (`/`) has no auth middleware — it's public.

## Login flow

Each authenticated surface has its own login page; both authenticate through the same Fortify backend:

- `qcpstaffing.com/login` → QC Minute dashboard (PM or contractor, by role)
- `qualitycleanplus.com/admin/login` → back office dashboard (recruiter, office manager, etc., by role)

If a user authenticates but lacks a role permitted on that surface, they see a clear "wrong door" message linking to the correct surface's login.

## Super admin

A super admin can log in on either surface. The shell they see depends on which surface they entered through, but they have full data access regardless. A "Switch to back office" / "Switch to QC Minute" link lives in the super-admin nav.

## Per-surface chrome

The three surfaces are **separate asset bundles**, each with its own root layout:

- **Marketing** — a Blade layout (`resources/views/site/`), `site` bundle.
- **Back office** — its Inertia root layout in the `admin` bundle (`resources/js/admin/`), pages under `views/admin/`.
- **QC Minute** — its Inertia root layout in the `minute` bundle (`resources/js/minute/`), pages under `views/minute/`.

Shared React components (e.g. a timesheet detail view both PMs and recruiters see) are imported by whichever bundle needs them.

## URL generation

Use named routes; for cross-surface links, generate against the target domain from config:

```php
// link to a QC Minute page from a back-office notification
URL::secure(
    route('timesheet.show', ['timesheet' => $t], absolute: false),
    domain: config('domains.qcminute'),
);
```

(Helper to be wrapped in a service.)

## Local development

Herd serves both hostnames at `.test` (its dnsmasq resolves `*.test` to 127.0.0.1 — no `/etc/hosts` edits needed):

```bash
cd <project>
herd link qcminute      # this app at qcminute.test  (= qcpstaffing.com)
herd secure qcminute    # https
# qcpminute.test (= qualitycleanplus.com) is already linked + secured
```

Set `DOMAIN_MAIN=qcpminute.test` and `DOMAIN_QCMINUTE=qcminute.test` in `.env`, then `php artisan config:clear`. (`php artisan serve` can't do multi-domain — use Herd/Valet.)

## Related

- `10-architecture/overview.md` — the system shape
- `10-architecture/identity-and-auth.md` — login flows in detail
- `10-architecture/permissions-matrix.md` — which roles are allowed where
- ADR-0001 — One app, multiple domains (refined by ADR-0023, ADR-0024)
- ADR-0023 — Marketing site in-monorepo as a Blade surface
- ADR-0024 — Two-domain layout + per-surface bundles
