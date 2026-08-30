# Phase 02 — Property Bible (detailed plan)

| Field | Value |
|---|---|
| Status | ✅ Done (37 Pest tests green, Pint + Larastan clean, build + types clean) |
| Last updated | 2026-06-07 |
| Owner | Engineering |

## As built (current state)

- **Schema:** `departments`, `positions` (seeded catalogs), `properties` (Profile spine), `property_departments`, `property_position_rates` (effective-dated, cents), `files` (polymorphic), `contracts` (provisional v1), `property_assignments`. `people_external_ids.property_id` promoted to a real FK.
- **Structure (ADR-0025):** all backend code lives under `app/Domain/{People,PropertyBible,Shared}/{Models,Enums,Actions,Policies,Concerns}`. See `app/Domain/README.md`.
- **Models/enums:** `Property` (+ `currentRateFor()`, `scopeAssignedTo()`), `Department`, `Position`, `PropertyDepartment`, `PropertyPositionRate`, `Contract`, `PropertyAssignment` (PropertyBible) + `File` (Shared); enums `PropertyStatus`, `ContractType`, `PropertyAssignmentRole`. `Person` (People) gained `assignedProperties()` / `isAssignedTo()`.
- **Actions:** write operations are Action classes (`CreateProperty`, `UpdateProperty`, `AddPropertyDepartment`, `AddPropertyPositionRate`, `UploadContract`, `AssignPersonToProperty`); controllers call them.
- **Authorization:** `PropertyPolicy` (+ sub-section abilities) and `ContractPolicy`, registered via `Gate::policy()` in `AppServiceProvider`. Global roles (super_admin/admin/office_manager/hr/payroll) see all; recruiter/PM scoped to assigned via `property_assignments`. Contracts gated to super_admin + payroll. Base `Controller` uses `AuthorizesRequests`.
- **Back office:** `Route::resource('properties')` + nested department/rate/contract/assignment routes (all in `routes/backoffice.php`, closure-free). Thin controllers + FormRequests (`app/Http/Requests/Property/*`, shared `PropertyValidationRules`). Rates are append-only (new effective-dated row closes out the prior one; dollars→cents on save). Contract upload/download via local `Storage`. All Bible mutations logged to the activity log **performed on the property** so the History tab is one stream.
- **UI:** `views/admin/properties/{index,form,show}.tsx`. `show` is a tabbed Bible (Profile / Departments / Positions & Rates / Contracts / Team / History) with React-state tabs styled to the theme's `hs-tab` look; client-side gating via shared `auth.permissions`. Sidebar "Property Bible" item now links to `/admin/properties` (no longer "Soon").
- **Scheduled job:** `contracts:expiration-check` (daily 07:00, via `routes/console.php`) → `ContractExpiringNotification` (database channel) to super_admin + payroll at the 30- and 14-day marks.
- **Dev fix:** `config/inertia.php` testing `page_paths` corrected to `resource_path('js/admin/views')` (was the stale `js/views`), so `assertInertia()->component()` resolves.

> **Note:** Larastan v3 didn't infer the enum/date `casts()` types in array-building closures here, so the Phase 02 models carry `@property` PHPDoc for the cast attributes the controllers touch.

---

Just-in-time detailed plan for Phase 02 of `80-plan/roadmap.md`. Builds the **Property Bible** — the central source of truth for everything about a property — on top of the Phase 01 foundation.

**Acceptance (roadmap):** an office manager can create a property, add departments, add positions with rates, and upload a contract; a recruiter sees only properties they're assigned to; contracts are visible only to ownership (super_admin) + payroll.

Implements against `20-domain/property-bible.md`, `30-schema/conventions.md`, ADR-0004, ADR-0010, and `90-open/contracts-data-model.md` (provisional v1).

## Key decisions

- **`app/Domain` structure** (ADR-0025) — backend code is organized by context: `app/Domain/PropertyBible/{Models,Enums,Actions,Policies,Concerns}` and `app/Domain/People/…`, with thin controllers in `app/Http` that call Actions. (Phase 02 originally shipped flat under `app/Models`/`app/Policies`; it was migrated to `app/Domain` mid-phase, and Phase 01's `Person`/identity moved with it.)
- **Contracts = provisional v1** per `90-open/contracts-data-model.md` (David deferred the richer model): metadata + one uploaded file via a polymorphic `files` table. Extendable later without breaking.
- **File storage = local disk** for now; S3 is a prod concern. The `files` table is reusable by KB / invoices later.
- **`property_assignments` built now** — drives the `(own)` policy scoping required by acceptance (recruiter/PM see only assigned properties).
- **Departments + Positions seeded** from the predefined lists in `property-bible.md`. Catalog-management UIs were built later at `/admin/positions`, `/admin/departments` and `/admin/holidays`.
- **Effective-dated rates** — a rate change adds a new `property_position_rates` row; old rows are never mutated. "Current" = latest `effective_date ≤ today` with `is_active`.
- **`people_external_ids.property_id`** gets its real FK to `properties` (left bare in Phase 01). Import logic stays in Phase 05.

## Increments

0. **Phase doc** — this file.
1. **Schema & models** — migrations for `departments`, `positions`, `properties`, `property_departments`, `property_position_rates`; add property FK to `people_external_ids`. Models (`Property`, `Department`, `Position`, `PropertyDepartment`, `PropertyPositionRate`) + `PropertyStatus` enum + factories; `DepartmentSeeder` + `PositionSeeder`. `migrate:fresh --seed`.
2. **Contracts + files + assignments** — polymorphic `files` table/model; `contracts` table/model + `ContractType` enum; `property_assignments` table/model + `PropertyAssignmentRole` enum; relationships + scopes.
3. **Authorization** — `PropertyPolicy` (+ `ContractPolicy`) gating on `bible.*` permissions with `(own)` assignment scoping; register policies.
4. **Back office CRUD + UI** — `properties` resource + nested section routes; thin controllers + FormRequests (`PropertyValidationRules` concern); Inertia views `index`/`form`/`show` (tabbed Bible: Profile / Departments / Positions & Rates / Contracts / History); wire the sidebar item to `/admin/properties`.
5. **Contract expiration job** — `ContractExpirationCheck` command (30/14 day) + `ContractExpiringNotification` (database channel); scheduled daily.
6. **Tests + docs** — Pest feature tests (CRUD, permission gating, rate resolution, assignment scoping, contract access, expiration); pint + stan + test + build/types green; update this doc + roadmap.

## Schema summary

| Table | Purpose |
|---|---|
| `departments` | Global reference catalog (Housekeeping, Banquets, …), extensible |
| `positions` | Global job catalog (Housekeeper, Banquet Server, …) |
| `properties` | Bible Profile spine (identity, contact, timezone, geo, tax, status) |
| `property_departments` | Per-property department + manager contact |
| `property_position_rates` | Effective-dated pay/bill/OT rates (cents) per (property, position) |
| `files` | Polymorphic uploads (`fileable_*`) |
| `contracts` | Provisional v1 — metadata + one file (super_admin/payroll only) |
| `property_assignments` | Recruiters / PMs assigned to properties (drives `(own)`) |

## Permissions (already seeded in Phase 01)

`bible.properties.{view,edit}`, `bible.departments.{view,edit}`, `bible.positions.{view,edit}`, `bible.rates.{view,edit}`, `bible.contracts.{view,edit,download}` (contracts: super_admin + payroll only). See `10-architecture/permissions-matrix.md`.

## Verification

`migrate:fresh --seed` → log in as super admin → sidebar "Property Bible" active → create property → add department/position+rate/contract → second rate row supersedes by effective date → recruiter sees only assigned properties → contractor blocked → contracts hidden except super_admin/payroll → `ContractExpirationCheck` notifies on 30/14 day → `composer test` + `composer stan` + `npm run build`/`types` green.

## Out of scope (later phases)

Rate-floor enforcement from contracts; richer contract structured data (revisit `90-open/contracts-data-model.md`); global department/position catalog management UI; import matching logic (Phase 05); geofence math + maps (Phase 07); S3 storage (prod); email channel for expiration alerts (database notification for now).

## Related

- `20-domain/property-bible.md`, `30-schema/conventions.md`, `30-schema/erd.md`
- `90-open/contracts-data-model.md`, `10-architecture/permissions-matrix.md`
- `80-plan/roadmap.md`, `80-plan/phase-01-foundation.md`
- ADR-0004, ADR-0005, ADR-0010
