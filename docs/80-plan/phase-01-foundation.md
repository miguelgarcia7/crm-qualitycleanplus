# Phase 01 — Foundation (detailed plan)

| Field | Value |
|---|---|
| Status | Built (foundation complete; 19 Pest tests green, Larastan clean) |
| Last updated | 2026-06-06 |
| Owner | Engineering |

## As built — deviations from the plan

- **Login reuses the reference `auth/sign-in` view** (wired to Fortify), not a bespoke login page — per the reuse-the-library principle. Fortify `loginView` → `auth/sign-in/index`.
- **`minute` surface renders from the `admin` bundle** (views under `views/minute/`) for now; the separate `minute` Vite bundle is deferred to when QC Minute is built out (it's a build optimization, not a functional requirement).
- **Dashboards are minimal placeholders** (BaseLayout) — the full reference shell/nav comes when the back office is built.
- **Domain groups live in `routes/web.php`**, not a `withRouting(then:)` closure — Herd can't serialize closures (see the Herd routing gotcha). Route files are closure-free (`Route::view`/`Route::inertia`/controllers).

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

## Out of scope (later phases)

Sanctum/`/device/*`, `site` Vite bundle + real marketing pages, full `people` application/onboarding columns (02/08), "(own)" policies (per-resource phases), Reverb (03), S3 (prod), KB/PTO/etc.

## Related

- `80-plan/roadmap.md`, `10-architecture/overview.md`, `10-architecture/domain-routing.md`, `10-architecture/permissions-matrix.md`
- `20-domain/people-lifecycle.md`, `30-schema/conventions.md`
- ADR-0004, ADR-0010, ADR-0013, ADR-0022, ADR-0024
