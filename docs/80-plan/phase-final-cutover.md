# Phase Final — Legacy Cutover (QC Minute → this app)

| Field | Value |
|---|---|
| Status | Importer + verify gate built and rehearsed green end-to-end (2026-09-01 dump) |
| Last updated | 2026-09-01 |
| Owner | Engineering |

## As built

- `legacy` read-only connection (config/database.php, `LEGACY_DB_*` env, default DB
  `minute`); **both MySQL connections pin session `timezone = '+00:00'`** — found
  during rehearsal: a CDT session interprets the app's UTC TIMESTAMP columns in local
  time and rejects instants inside the DST spring-forward gap.
- `php artisan legacy:import` (app/Console/Commands/LegacyImport.php) — 12 idempotent
  steps in `app/Domain/LegacyImport/Steps/`, crosswalked through `legacy_id_map`,
  runnable whole or per-step (`--only=`). `php artisan legacy:verify` is the cutover
  gate: hard checks (coverage, invoice money to the cent, per-invoice equality,
  entry/period integrity, summary coverage) plus quantified known-drift reports.
- Rehearsal procedure: `migrate:fresh` → structural seeders by class
  (RolePermission, Department, Position, Inventory, CompanySettings) →
  `legacy:import --force` → `legacy:verify` → `reports:refresh-rollups --from=2024-08-01`.
- Schema deltas made for the import: `people.email` nullable; `invoices.timesheet_id`
  nullable (10 legacy manual invoices are genuinely timesheet-less);
  `invoices.paid_at` added (legacy paid/pending/overdue carried losslessly; overdue
  derives from due_date + unpaid). Legacy invoice notes were all empty — no note column.
- Rate fidelity: invoiced punches snapshot the **billed** rates from their frozen
  invoice item (legacy edited WO rates in place — 287 items differ from current WO
  rates); uninvoiced punches use the WO's rates.
- Punch-property vs WO-property: 201 legacy punches were recorded at a sibling
  property of their work order's (parent/child era). Entries follow the WO's property
  (summaries key on it); the punch property is kept in `source_metadata.legacy_property_id`.
- Files: all legacy files live on the `spaces` object-storage disk — rows import
  verbatim, no bytes move; the app needs a `spaces` disk configured for links to work.
- Accepted drift (quantified by legacy:verify, not failures): 1 negative-duration
  punch clamped to 0; ~380 invoiced weeks where live entries no longer match their
  frozen invoice (~1.6M min — punches edited/voided after invoicing; legacy drifted
  from itself the same way); small per-week bill deltas from the two apps' different
  overtime-split algorithms (frozen invoices remain billed truth); 6 pre-freeze 2024
  invoices imported zero-valued (`legacy_unfrozen` marker: QCM-00081/82/95/102/125/139)
  — candidates for manual void or backfill.

This is the migration plan `00-context/current-systems.md` defers to. It covers moving the
operational data out of **QC Minute** (the legacy time-tracking app, Herd MySQL database
`minute`, repo at `~/Code/www.qcpstaffing.site`) into this app.

**Out of scope:** the QCP CRM database (`qualitycleanplus`). Its "invoices" are
contractor-side documents (generated on the contractor's behalf so QCP can pay them) —
the opposite direction of this app's property billing. Selected data from it (people
records, PTO, KB) may be cherry-picked in a later pass; nothing is bulk-imported.
The `qcp` database in Herd is unrelated to either system.

## Approach

A **one-shot, rehearsable ETL** built inside this app — not a SQL-dump transform, not an
ongoing sync, and not the Phase 05 spreadsheet pipeline (which is scoped to one
property/week, though its conventions are reused: `source = imported`, rate snapshots,
`source_metadata` audit trails).

- A read-only `legacy` connection in `config/database.php` pointing at `minute`.
- `php artisan legacy:import` with per-entity steps (`--only=people`, `--only=time`, …),
  each idempotent through a `legacy_id_map` table (`entity`, `legacy_id`, `new_id`) so
  re-runs update instead of duplicate.
- `php artisan legacy:verify` reconciles the result (see Verification).
- Rehearse repeatedly against refreshed dumps (legacy `db:sync-from-prod` pulls prod →
  local), then run once more on cutover day against a frozen legacy system — the
  four-step approach already recorded in `current-systems.md`.

The legacy dump profiled for this plan is from **2026-08-30**; all counts below are from
that snapshot and will drift slightly by cutover.

## What the legacy data looks like

- 1,480 users (1,375 contractors, 79 property managers, 15 employee managers, 5 admin,
  2 payroll, 3 office managers, 16 device accounts); 129 soft-deleted.
- 70 properties (38 active), 22 of them linked to a parent property.
- 2,600 work orders (861 soft-deleted), 51 job types.
- 68,880 work time records, Aug 2024 → present. Zero orphaned contractor references,
  zero missing work-order links. ~52 open punches at any moment.
- 1,757 weekly timesheets; 1,508 invoices with frozen snapshots (12 pre-2026-03
  invoices lack them); 9,567 invoice items.
- 1,444 hiring-fee deductions; 490 contractors carry a hiring fee.
- 2,666 comments on time records; 34,485 file rows; 248k activity-log rows.
- Both adjustment transaction tables (`work_time_record_adjustments`,
  `invoice_adjustments`) are **empty** — nothing to migrate there.

## The archive-vs-history rule

The reason this migration exists in its current shape: in legacy, soft-deleting a work
order or contractor breaks historical invoice views. This app fixed that structurally —
`invoice_items` snapshots names and rates as copied strings, `time_entries` freezes four
rate columns at punch time, and people/work orders carry status enums separate from soft
deletes.

Therefore: **every legacy row comes over, deleted or not.** Legacy `deleted_at` maps to
an archived *status* (`contractor_inactive`/`terminated` on people; `closed` on work
orders; property status likewise), never to a skipped row. 861 work orders and 129 users
are soft-deleted but load-bearing for two years of invoices.

## Pre-import changes to this app

1. **`people.email` becomes nullable** (unique constraint kept — MySQL allows multiple
   NULLs). 1,365 of 1,480 legacy users have no email; contractors identify by phone. No
   placeholder emails — they would leak into invoices and notifications.
2. **Thin-week deduction cap** (separate task, not blocking the importer):
   `ApplyScheduledContractorCharges` applies installments unconditionally at period open;
   it should cap at available pay like `ProcessFinalPaycheck` does, deferring the
   uncovered remainder. Legacy caps at week earnings, so historical rows are already
   correct — this is about post-cutover behavior.
3. Optional comment field on the manual-entry form, folded into the `payroll` activity
   log (see Comments below). Nice-to-have before cutover, not a blocker.

## Wipe and reseed

1. `php artisan migrate:fresh` — drops all current seed/demo data.
2. Re-seed structural reference data **by class** (`DatabaseSeeder` would also run
   `SampleDataSeeder` outside production): `RolePermissionSeeder`, `DepartmentSeeder`,
   `PositionSeeder`, `InventorySeeder`, `CompanySettingsSeeder`.
3. `HolidaySeeder` runs **after** the property import — it attaches default holidays to
   whatever properties exist.

## Table-by-table mapping

| Legacy (`minute`) | Target | Notes |
|---|---|---|
| `users` (contractor role) | `people` | status from `deleted_at` + open work orders; phone is the identifier; legacy id → `legacy_id_map` |
| `users` (admin / property_manager / employee_manager / payroll / office manager) | `people` + role | `employee_manager` → **`recruiter`**; `property_manager` → `property_manager`; others map by name to this app's role set, which is unchanged |
| `users` (device role, 16) | — | skip; tablets re-onboard via new `devices` activation codes |
| `properties` | `properties` | `tax_rate` ÷ 100 (legacy float percent → DECIMAL(5,4) fraction); `time_source = clock_in`; QR/geofence columns 1:1; `parent_id` dropped (see Flattening) |
| `job_types` (51) | `positions` | merge by name into the global catalog; `property_job_type.job_coding` → `property_position_codes` |
| `property_user` (PM/EM rows) | `property_assignments` | role mapping as above |
| `work_orders` | `work_orders` | rates cents→cents; `source = imported`; ended/deleted → `closed`; `probationary_period` (hours) × 60 → `direct_hire_threshold_minutes`; `ot_*` rates filled at 1.5× base (verified against invoice snapshots); optionally backfill `property_position_rates` history from distinct WO rates |
| `work_time_records` (68,880) | `time_entries` | `source = imported`; `method` → `clock_method`; GPS columns 1:1; `was_updated` 1:1; dual UTC+local 1:1; rate snapshots from the WO; legacy id + punch metadata → `source_metadata`; open punches import as open entries |
| `property_time_sheets` (1,757) | `payroll_periods` + `timesheets` | one period per property/week (historical periods created by the importer — `payroll:ensure-periods` only materializes forward), status `closed`/`invoiced`; one timesheet per period with status mapped |
| `comments` (2,666) | `activity_log` (`payroll` log) | subject = the contractor, causer = mapped author, description = comment text, legacy ids + entry date/property in properties JSON, original `created_at`; inserted directly (historical causers/timestamps bypass the `activity()` helper) |
| `invoices` (1,508) | `invoices` | **snapshots copied verbatim — never recomputed**; `invoice_number` = deterministic legacy series `QCM-{legacy id}`; 12 pre-snapshot invoices backfilled first (legacy `FreezeExistingInvoicesCommand` logic) |
| `invoice_items` (9,567) | `invoice_items` | verbatim, including denormalized names/rates/minutes/amounts |
| `manual_invoice_data` (17 rows, dormant since 2025-01) | `time_entries` `source = imported` | per ADR-0008 |
| `users.hiring_fee` + `contractor_fee_deductions` | `contractor_charge_schedules` + entries | see Hiring fees |
| `holidays` / `property_holiday` | same | reconcile by slug against the seeded catalog |
| `devices` | `devices` | rows only; new activation codes; Sanctum tokens not migrated — tablets re-activate |
| `adjustment_items` | `adjustment_items` | catalog only (transaction tables are empty) |
| `files` (34,485) | `files` | morph FQCNs rewritten to this app's model classes; physical files copied; legacy `fileable_id` is `unsignedInteger` — cast carefully |
| `activity_log`, `notifications`, telescope, sessions, tokens, queue tables | **skip** | legacy FQCNs / transient; archive the final dump for cold history |
| `expense_categories` / `property_expenses` (8 rows) | **skip** | no target table; re-enter manually if wanted |

### Flattening the property hierarchy (decided: flat)

The 22 parent-linked legacy properties are mostly departments/venues modeled as child
properties (Embassy Market Center ×4, Kimpton Pittman ×3, Legends Hospitality ×9, plus
The Olana Servers, one self-referencing row, and 5 dead children). Each child owns its
punches and invoices, so every one imports as its own **top-level** property with the
parent link dropped — nothing splits or merges. `property_departments` restructuring, if
ever wanted, is a manual future exercise, because it would mean merging separate invoice
histories.

### Hiring fees

Legacy: `users.hiring_fee` + `hiring_fee_deduction_per_timesheet`, deductions written at
timesheet generation, capped at that week's earnings. Target: `contractor_charge_schedules`
(`reason = hiring_fee`) + `contractor_charge_schedule_entries` + non-billable
`time_entry_adjustments`.

- Each contractor with `hiring_fee > 0` → one schedule: `total_amount` = fee,
  `amount_per_payment` = their per-timesheet deduction, `num_payments` = ceiling.
- Each historical deduction → an entry with `status = applied` on the imported payroll
  period for that week, plus the matching non-billable adjustment, linked via
  `applied_adjustment_id`.
- Remaining balances need no future entries: `AllocateChargeScheduleEntries` tops active
  schedules up as periods open, so collection resumes on its own.
- Statuses from the 2026-08-30 reconciliation: 46 fully recouped → `completed`; 278
  partial + 166 not started → `active`; **terminated contractors with a balance →
  `cancelled`, plus a `charge_written_off` activity entry** for the remainder (same
  vocabulary `ProcessFinalPaycheck` uses), so the books read consistently.
- Edge cases the importer normalizes: 10 contractors with deductions but `hiring_fee = 0`
  (total inferred as sum collected); 2 with a fee but per-payment `$0` (defaulted to the
  config $20/period).

### Duplicate people (decided: importer merges)

26 phone numbers are shared across 62 legacy user rows. The importer carries a
**merge map** (`[legacy ids…] → one person`), repointing work orders, time entries,
deductions and comments to the survivor during import — legacy data is not edited.
Survivors chosen by activity/history (see the working list in the migration session;
notable merges: Victoria Segovia → #150, Rosa Salas → #169, Saul Cartagena → #171,
Abigail Vasquez → #175, Itzel Rodriguez → #172, Silmary Torres → #125, Carolina Perez
→ #61). Pure test rows (#140–142, #192) are dropped outright.

## Import order

Structural seeds → `people` (two passes: insert, then backfill self-references) →
`properties` → positions / codes / rates / assignments / `HolidaySeeder` →
`work_orders` → `payroll_periods` → `time_entries` → `timesheets` → `invoices` →
`invoice_items` → hiring-fee schedules + historical deductions → comments-as-activity →
files → recompute `time_summaries` → `reports:refresh-rollups`.

Circular-FK spots the schema already works around, in import order too: `people` ↔
`files` (file pointer columns backfilled after files land) and `timesheets` ↔ `invoices`
(`timesheets.invoice_id` backfilled after invoices land).

## Verification — the gate for cutover

`legacy:verify` reconciles, per property per week:

1. Total minutes: legacy `work_time_records` vs imported `time_entries`.
2. Invoice money **to the cent**: legacy frozen totals vs imported `invoice_items` sums.
3. Row counts per entity vs the legacy source (net of merges/skips, which are reported).
4. Filled WO overtime rates vs `invoice_items.overtime_rate` in legacy snapshots — any
   property with a non-standard OT multiplier surfaces here.

Manual spot checks: an invoice referencing an archived contractor and a closed work order
renders complete (the exact legacy bug, verified fixed); a contractor profile shows
imported history, hiring-fee balance, and comment activity; direct-hire progress bars
show sane numbers on active work orders.

## Production topology (decided 2026-09-02)

Legacy production (`prod_qc_min`) runs on a DigitalOcean droplet via Forge; the new
app deploys to **Laravel Cloud**. The database engine on Laravel Cloud MUST be MySQL
(the app uses ENUM columns, MySQL JSON path syntax, and the UTC session pin).

**The importer runs FROM the DO server, writing TO the Laravel Cloud database.**
Legacy reads stay on localhost where they are heavy; the Cloud DB's external-access
allowlist is restricted to the droplet's single static IP (far simpler than opening
DO's MySQL to Cloud egress). Setup on the droplet: PHP 8.4 side-by-side via Forge, a
checkout of this repo, `.env` with `DB_*` → the Cloud database's external credentials
and `LEGACY_DB_DATABASE=prod_qc_min` through a read-only MySQL user. Expect the run
to take up to an hour over the wire (the summaries step is query-chatty). Dress
rehearsals against the real Cloud DB need no freeze — `migrate:fresh` resets. After
go-live: disable the Cloud DB's external access and remove the droplet checkout.

## Cutover runbook

1. **Rehearsals** (now → ready): refresh dump → wipe/reseed → `legacy:import` →
   `legacy:verify` → fix → repeat, until verify is clean twice in a row.
2. **Freeze window** (planned date, off-hours): legacy app goes read-only / maintenance;
   note the ~52 open punches — they import as open entries and clock out in the new app.
3. Final `db:sync-from-prod` (or dump of the frozen prod DB) → production
   `legacy:import` → `legacy:verify` must be clean.
4. Tablets re-activate against the new app; users get onboarding/password-set flows
   (contractors by phone).
5. Parallel-observation window per `current-systems.md`; legacy retired after N clean
   weeks. Archive the final legacy dump (covers the skipped `activity_log` history).

## Decision log (settled 2026-08-30/31)

1. `people.email` → nullable; phone is the contractor identifier.
2. Duplicate-phone users → merged by the importer via merge map.
3. Property hierarchy → flat; parent links dropped.
4. (list of parent-linked properties reviewed — see Flattening)
5. Invoice numbers → deterministic `QCM-{legacy id}` series.
6. Legacy `employee_manager` → `recruiter`; `property_manager` → `property_manager`;
   this app's role set unchanged.
7. Hiring fees → charge schedules + applied entries; terminated-with-balance →
   `cancelled` + write-off activity entry.
8. Time-record comments → `payroll` activity history on the contractor (no note column).
9. Work orders import base pay/bill rates; OT filled at 1.5× and cross-checked against
   legacy invoice snapshots.
