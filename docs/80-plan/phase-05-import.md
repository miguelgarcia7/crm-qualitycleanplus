# Phase 05 — Excel Hour Import

| Field | Value |
|---|---|
| Status | ✅ Done (Pest green — 12 import tests; Pint + Larastan clean, build + types clean, `migrate:fresh --seed` clean). |
| Last updated | 2026-06-08 |
| Owner | Engineering |

## As built (current state)

- **Schema/enums:** `import_batches` + `import_batch_rows` tables; `properties.time_source`
  (`clock_in`/`import`). Enums `ImportBatchStatus`, `ImportRowStatus`, `PropertyTimeSource`.
  `ImportBatch`/`ImportBatchRow` models + factories. `PersonExternalId` model added (the
  table predated it).
- **Parse + match:** `HourImportParser` (maatwebsite/excel; flexible header detection;
  hard-fails on missing header/column, `$0`/negative rate, duplicate id) →
  `CreateImportBatch` (sha256 duplicate-file guard, week-match check, classify each row
  via `ImportRowMatcher`).
- **Wizard:** `ImportController` (index/create/store/show/resolve/adjustments/commit/rollback)
  + `views/admin/imports/{index,create,show}`. Review/resolve/adjustments live on one
  Inertia page; the read-only `show` doubles as the result screen (invoice link + void/
  re-import). Property Bible gained a Time Source toggle.
- **Commit:** `CreateImportedTimeEntry` (duration-only, null clock times) + `CommitImport`
  (resolve/create WO → entries → adjustments → approved timesheet → `GenerateInvoice`).
  Rates resolved by the shared `ResolvesImportRates` trait (file pay authoritative; bill
  from file or contract rate; OT 1.5× fallback).
- **Void/rollback/re-import:** reusable `VoidInvoice` (voids invoice + timesheet, reopens
  period) + `RollbackImport` (void → soft-delete imported entries + their adjustments →
  recompute summaries → batch `rolled_back`). "Void and Re-import" chains rollback into a
  prefilled new upload.
- **Tests:** `tests/Feature/ImportTest.php` (12). **Seed:** an import-only property with
  one committed weekly import (frozen invoice) in `SampleDataSeeder` (non-prod).

> Toolchain: built/tested on **PHP 8.4** (`php84`) with `config.platform.php=8.4.21`, since
> Herd's default CLI moved to 8.5 and maatwebsite/excel only supports php <8.5.

## Context

Hotels that don't use QC Minute clock-in still have QCP contractors placed there, and
QCP still bills for their hours. Those hotels send a **weekly Excel export**. Phase 05
builds a back-office **wizard** that turns that file into the same money artifacts the
clock-in path produces: imported time entries → auto-approved timesheet → frozen
invoice — with a full audit trail and a void/rollback/re-import path for corrections.

This is **orchestration over existing Phase 03/04 primitives**, not new pipeline. The
canonical spec is `docs/40-flows/import-hours.md`.

## Scope decisions (locked with owner)

1. **Full** void + rollback + "Void and Re-import" (build a reusable `VoidInvoice`).
2. **Add** a `time_source` flag on properties (default `clock_in`) + a Property Bible
   toggle; the import property dropdown is scoped to `import` properties.
3. **Include** the optional per-contractor adjustments step (reuses `CreateManualAdjustment`).

## Toolchain note

The project targets **PHP 8.4** (`composer.json` `php: ^8.4`). Herd's default CLI is
now 8.5.5, so Composer's platform is pinned to `8.4.21` (`config.platform.php`) and the
Phase 05 toolchain is run via the `php84` binary. `maatwebsite/excel ^3.1` (the spec's
named xlsx library) only supports php <8.5, so it installs/runs under 8.4.

## Anchored docs

`40-flows/import-hours.md` (executable spec) · ADR-0005 (rate snapshots) · ADR-0006
(invoice freeze/void/reissue) · ADR-0007 (import skips PM approval) · ADR-0008
(entries + summaries) · ADR-0009 (payroll periods) ·
`20-domain/{work-orders,people-lifecycle,adjustments}.md`.

## Reuse (don't rebuild)

- WO + rates: `Property::currentRateFor()`, `CreateWorkOrder::handle` (extended with a `source` param).
- Summaries: `RecomputeTimeSummary::dispatchSync($workOrderId, $periodId)`.
- Invoice: `GenerateInvoice::handle($timesheet)` (idempotent; self-sets timesheet/period + freeze).
- Adjustments: `CreateManualAdjustment::handle($period, $data, $actor)`.
- Matching: `people_external_ids` on `(property_id, external_id)`.
- Already present: `TimeEntrySource::Imported` + nullable clock times + `source_metadata`;
  `timesheets.source` enum incl. `imported`; all invoice-void columns + `InvoiceStatus::Voided`;
  permissions `imports.{upload,commit,rollback}`.

## Increments

0. **Infra** — this doc; `composer require maatwebsite/excel` (php84); enums
   `ImportBatchStatus`/`ImportRowStatus`/`PropertyTimeSource`; migrations
   `import_batches` + `import_batch_rows` + `properties.time_source`; `ImportBatch` /
   `ImportBatchRow` models + factories; `Property` cast.
1. **Parse + match (upload)** — `HourImportParser` service (header detection +
   validation); `CreateImportBatch` action; `ImportController` create/store; upload
   view; Property Bible `time_source` toggle.
2. **Review / resolve / adjustments** — `ImportController` show/resolve/adjustments;
   row resolutions; pending adjustments; wizard page.
3. **Commit** — `CreateImportedTimeEntry` (duration-only); `CommitImport`
   (WO resolve/create → entries → adjustments → recompute → approved timesheet →
   `GenerateInvoice`); `ImportController@commit` + result page.
4. **Void / rollback / re-import** — `VoidInvoice` action; `RollbackImport`;
   `ImportController` rollback + re-import; batch index; routes + sidebar.
5. **Tests + docs + seed** — `ImportTest` (xlsx fixtures in-test); all gates; seed an
   import-only property + a committed demo batch; this doc "as built"; roadmap → Done.

## Out of scope (deferred)

Multi-week files per upload (one period per import); CSV input (xlsx only); a
column-mapping UI (auto header detection only); emailing the invoice (Postmark still
deferred); scheduled/auto import.

## Related

`40-flows/import-hours.md`; ADR-0005/0006/0007/0008/0009; `80-plan/roadmap.md`.
