# Domain Routing

| Field | Value |
|---|---|
| Status | Accepted |
| Last updated | 2026-05-21 |
| Owner | Engineering |

## Pattern

Laravel's `Route::domain()` lets us mount different route trees on different hostnames within the same app. We use this to enforce that:

- Property managers and contractors can only log in via `qcminute.com`
- All QCP back-office staff can only log in via `backoffice.qcpstaffing.com`
- Super admins can use either

## Route files

```
routes/
├── public.php          ← marketing site → fetched JSON / submitted applications
├── qcminute.php        ← QC Minute domain (PM + contractor + super-admin)
├── backoffice.php      ← Back office domain (everyone else + super-admin)
├── device.php          ← Tablet endpoints (sanctum)
├── web.php             ← Shared infrastructure: login, password reset, error pages
└── console.php         ← Schedule definitions
```

## Bootstrap

In `bootstrap/app.php`:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    commands: __DIR__.'/../routes/console.php',
    then: function () {
        Route::middleware('web')->group(function () {
            Route::domain(config('app.domains.qcminute'))
                ->group(base_path('routes/qcminute.php'));

            Route::domain(config('app.domains.backoffice'))
                ->group(base_path('routes/backoffice.php'));
        });

        Route::middleware('api')
            ->prefix('api')
            ->group(base_path('routes/public.php'));

        Route::middleware(['auth:sanctum'])
            ->domain(config('app.domains.qcminute'))
            ->prefix('device')
            ->group(base_path('routes/device.php'));
    },
)
```

Domains live in config so they're environment-aware (local uses `qcminute.test`, etc.).

## Domain access middleware

Two middleware classes enforce "right person on right domain":

```php
// app/Http/Middleware/AllowedOnQcMinute.php
public function handle(Request $request, Closure $next)
{
    $user = $request->user();
    abort_unless(
        $user?->hasAnyRole(['property_manager', 'contractor', 'super_admin']),
        403,
        'This account cannot access QC Minute.'
    );
    return $next($request);
}

// app/Http/Middleware/AllowedOnBackoffice.php
public function handle(Request $request, Closure $next)
{
    $user = $request->user();
    abort_unless(
        $user?->hasAnyRole([
            'super_admin', 'office_manager', 'hr', 'payroll',
            'recruiter', 'w2_employee'
        ]),
        403,
        'This account cannot access the back office.'
    );
    return $next($request);
}
```

Applied to the route groups in `qcminute.php` and `backoffice.php`.

## Login flow

Each domain has its **own login page** at `/login` on that domain. Both submit to the same backend (the auth controller is shared), but:

- Login form on `qcminute.com/login` redirects to `qcminute.com/dashboard` (PM dashboard or contractor dashboard depending on role)
- Login form on `backoffice.qcpstaffing.com/login` redirects to `backoffice.qcpstaffing.com/dashboard` (recruiter dashboard, office-manager dashboard, etc. depending on role)

If a user successfully authenticates but doesn't have a role permitted on that domain, they see a clear "wrong door" message with a link to the correct domain's login.

## Super admin

A super admin can log in on either domain. The UI shell they see depends on which domain they entered through, but they have full data access regardless. There's a "Switch to back office" / "Switch to QC Minute" link in the super-admin nav.

## Per-domain branding

Layout selection happens at the layout level based on the request's host:

```blade
@extends(request()->getHost() === config('app.domains.qcminute')
    ? 'layouts.qcminute'
    : 'layouts.backoffice')
```

Different navigation, different theming, different page chrome. Same underlying components for shared views (e.g. the timesheet detail view that both PMs and recruiters see).

## URL generation

Always use named routes with explicit domain context when generating URLs that cross domains:

```php
URL::route('invoice.show', ['invoice' => $invoice], true);
// Generates the URL on whatever domain the route was registered on
```

For notifications that link back to a specific domain:

```php
$pmDashboard = URL::secure(
    route('timesheet.show', ['timesheet' => $t], false),
    domain: config('app.domains.qcminute')
);
```

(Helper to be wrapped in a service.)

## Local development

Local hosts file entries:

```
127.0.0.1   qcminute.test
127.0.0.1   backoffice.qcpstaffing.test
127.0.0.1   qualitycleanplus.test
```

Local config sets:

```
APP_DOMAIN_QCMINUTE=qcminute.test
APP_DOMAIN_BACKOFFICE=backoffice.qcpstaffing.test
```

`php artisan serve` doesn't support multi-domain natively; local dev uses Laravel Herd or Valet with `valet link` per domain.

## Related

- `10-architecture/overview.md` — the system shape
- `10-architecture/identity-and-auth.md` — login flows in detail
- `10-architecture/permissions-matrix.md` — which roles are allowed where
- ADR-0001 — One app, two domains
