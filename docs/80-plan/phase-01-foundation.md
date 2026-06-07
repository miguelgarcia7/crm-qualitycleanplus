# Phase 01 — Foundation (detailed plan)

| Field | Value |
|---|---|
| Status | ✅ Done (foundation complete; 19 Pest tests green, Pint + Larastan clean) |
| Last updated | 2026-06-07 |
| Owner | Engineering |

## As built (current state)

- **Two domains, config-driven** (ADR-0024): `routes/web.php` registers `Route::domain(config('domains.main'))` for marketing (`/`, Blade) + back office (`/admin`, Inertia) and `Route::domain(config('domains.qcminute'))` for QC Minute (`/`). Local Herd hosts: `qcpminute.test` (main) + `qcminute.test`. Route files are closure-free so they cache. (No `withRouting(then:)` closure — kept route registration in `web.php`; not because of closures, that was a red herring — see below.)
- **Login** reuses the reference `auth/sign-in` view wired to Fortify (`loginView` → `auth/sign-in/index`); host-aware post-login redirect; registration + email verification disabled.
- **Admin shell is the real theme `MainLayout`** (sidebar + topbar). The sidebar (`layouts/components/data.ts`) shows OUR menu — Dashboard + Profile live; upcoming sections are disabled "Soon" placeholders — **gated by permission** (filter in `Sidenav/AppMenu` against `auth.permissions`, shared via `HandleInertiaRequests`). Topbar trimmed to functional controls; user dropdown shows the real person + working Fortify logout.
- **`minute` surface renders from the `admin` bundle** (views under `views/minute/`); a separate `minute` Vite bundle is deferred (build optimization, not functional).
- **Dev login:** `super-admin@example.com` / `password` (seeded in `DatabaseSeeder`).

> **Gotcha resolved:** an early 500-on-every-request came from renaming `sessions.user_id` → `person_id` (Laravel's DB session driver requires `user_id`); Herd's profiler masked it as a `$__herd_closure` error. Fixed by keeping `sessions.user_id`. See the `diagnosing-500s-and-herd` memory.

Just-in-time detailed plan for Phase 01 of `80-plan/roadmap.md`. Stands up the real foundation so every later phase builds on it.

**Acceptance (roadmap):** a super admin can log in to **either** domain, see an empty dashboard, navigate to Settings; the activity log records the login.

Implements against the canonical architecture: ADR-0004 (one `people` table), ADR-0022 (React/Inertia + Fortify), ADR-0024 (two domains / three surfaces / per-surface bundles). Executed in 5 ordered increments.

## Key decisions

- **`User`→`Person`, `users`→`people`** (ADR-0004). Greenfield → rewrite base migration + `migrate:fresh`.
- **Disable Fortify public registration + email verification** — accounts are created by Admin/HR (`admin.users.create`); no public signup.
- **Marketing** = Blade placeholder (no JS bundle yet); `site` Vite bundle deferred.
- **`minute` bundle** = thin skeleton so `qcpstaffing.com` renders its own bundle.
- **Sanctum / `/device/*` deferred** to the tablet flow.
- **`people` = spine only** (identity, auth, status, lifecycle dates, PII/legal-hold, `primary_recruiter_id`); bulk application/onboarding columns come with Phases 02/08.

## Increments

0. **Phase doc** — this file.
1. **Identity** — `people` migration (spine) + retargeted 2FA migration + `people_external_ids`; `Person` model (`HasRoles`, `SoftDeletes`, `HasLegalHold`, `TwoFactorAuthenticatable`, `status`→`PersonStatus`, immutable-history guard); `PersonStatus` enum; `HasLegalHold` trait; `PersonFactory`; `config/auth.php` + Fortify actions + validation retyped. `migrate:fresh`.
2. **Roles & permissions** — publish Spatie; `RolePermissionSeeder` (~100 permissions + 10 roles from `permissions-matrix.md`); `DatabaseSeeder` seeds a super_admin; register `role`/`permission` middleware aliases. `migrate:fresh --seed`.
3. **Domains/routing/auth** — `config/domains.php` + `DOMAIN_*` env; route files `marketing.php` (`/`, Blade) · `backoffice.php` (`/admin`) · `qcminute.php` (`/`); prune demo routes; wire `bootstrap/app.php` domain groups; `AllowedOnBackoffice`/`AllowedOnQcMinute` middleware; Fortify per-surface login + role redirect + wrong-door; marketing Blade placeholder; `minute` bundle skeleton + Vite multi-entry; dashboard placeholders; `settings/profile`; login→activity-log listener.
4. **CI/tests/docs** — `phpstan.neon` + `composer stan`; Pest feature tests (seeding, Person model, domain access, login activity log); local-setup doc.

## Verification

`migrate:fresh --seed` (10 roles, ~100 perms) → `npm run build` clean → Herd: `qcpminute.test/` marketing, `qcpminute.test/admin/login` + `qcminute.test/login` render → log in as super admin on both → dashboard + Settings → contractor blocked on `/admin` (wrong-door) → `activity_log` has login row → `composer test` + `composer stan` green.

## Open follow-ups (small, not blocking Phase 02)

- Seed a sample user per role so sidebar permission-gating can be tested across roles (only the super admin exists today).
- Clean up ~5 pre-existing theme `tsc` errors (`ApexChart.tsx`, `preline.ts`) so `npm run types` is fully green. (`npm run types` now actually runs; our code is type-clean.)
- Horizontal nav menu is not permission-gated yet (only the vertical Sidenav is). Default orientation is vertical.

## Out of scope (later phases)

Sanctum/`/device/*`, `site` Vite bundle + real marketing pages, full `people` application/onboarding columns (02/08), "(own)" policies (per-resource phases), Reverb (03), S3 (prod), KB/PTO/etc.

## Related

- `80-plan/roadmap.md`, `10-architecture/overview.md`, `10-architecture/domain-routing.md`, `10-architecture/permissions-matrix.md`
- `20-domain/people-lifecycle.md`, `30-schema/conventions.md`
- ADR-0004, ADR-0010, ADR-0013, ADR-0022, ADR-0024
