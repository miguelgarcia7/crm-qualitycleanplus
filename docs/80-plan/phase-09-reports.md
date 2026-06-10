# Phase 09 — Reports + Materialized Rollups + Exports

| Field | Value |
|---|---|
| Status | ✅ Built (all increments) |
| Branch | `phase-09-reports` |
| Last updated | 2026-06-10 |
| Depends on | Phase 03 (summaries/invoices), 05 (imports), 06 (dashboards), 08 complete |
| Spec | roadmap §Phase 09 · ADR-0028 (rollups) · ADR-0008 (time summaries) · ADR-0006 (invoice freeze) |

## As built

- **Inc 1** — `report_weekly_rollups` + `report_monthly_revenue` (one combined
  migration), `app/Domain/Reports/{Models,Actions}`, triggers wired inside
  `RecomputeTimeSummary` (weekly cell) and after the `GenerateInvoice` /
  `VoidInvoice` transactions (monthly cell — covers the import commit path,
  which freezes through `GenerateInvoice`), `reports:refresh-rollups` nightly
  at 01:30. The seeder populates rollups purely through the real triggers — no
  seeder-side refresh call needed.
- **Inc 2** — `ReportController` + `/admin/reports` catalog (cards grouped by
  permission), four standard reports with shared `ReportTable` component.
  Sidebar `MenuItemType.permission` extended to accept `string[]` (any-of) so
  the Reports entry serves financial/operational/payroll holders.
- **Inc 3** — Excel exports share each report's data builder (one generic
  `ArrayReportExport`), payouts PDF via dompdf (`pdf/payouts.blade.php`),
  `/admin/timesheets` history (status tabs, hours/billed per week from
  summaries, invoice links, Excel export). `Timesheet::invoice()` relation +
  timestamp `@property` docblocks added.
- **Inc 4** — 15 Pest tests (rollup math incl. void retraction + cell deletion,
  backstop rebuild, catalog/report/export permission gates, xlsx/pdf downloads,
  history page) — suite 236 passing. Docs + roadmap synced.

**Goal:** best-in-class reporting on pre-aggregated data. Office manager pulls
"revenue by property by month" → loads <1s → Excel export works. Payroll pulls
"payouts by contractor" for the current period.

## Already in place (no work needed)

- `time_summaries` — per-(work order, week) materialized minutes + pay/bill cents
  (ADR-0008), maintained by `RecomputeTimeSummary`. Contractor-grain source.
- Frozen `invoices` with totals incl. adjustments + tax (ADR-0006). Financial source.
- Permissions seeded since Phase 01: `reports.financial.view` (admin, office_manager,
  payroll), `reports.operational.view` (admin, office_manager, hr, recruiter,
  property_manager), `reports.payroll.view` (admin, payroll),
  `timesheets.view_history` / `timesheets.export` / `invoices.export`.
- Sidebar placeholders ("Soon"): Reports, Timesheets.
- Export deps installed: `maatwebsite/excel` (Phase 05), `barryvdh/laravel-dompdf` (Phase 03).

## Deviations from the roadmap sketch

- **No daily property rollup** — imported properties have weekly totals only; a
  daily grain would silently undercount them. Week is the finest honest grain.
  Full rationale in ADR-0028.
- **Report subscriptions (scheduled emails) deferred** — marked optional in the
  roadmap; no notification-email infrastructure exists yet (same deferral as KB
  publish notifications). The rollup + catalog layer ships now.
- **Invoice/payroll PDF**: invoice PDF exists since Phase 03; this phase adds a
  payouts (payroll) PDF. No other PDFs.

## Increments

### Inc 0 — Phase doc + ADR
This document + ADR-0028.

### Inc 1 — Rollup layer
- Migrations: `report_weekly_rollups` (UNIQUE property+position+week) and
  `report_monthly_revenue` (UNIQUE property+month) per ADR-0028.
- `app/Domain/Reports/Models/{ReportWeeklyRollup,ReportMonthlyRevenue}`.
- Actions `RefreshWeeklyRollup` / `RefreshMonthlyRevenue` — idempotent
  delete-and-rebuild of one (property, week|month) cell.
- Triggers: `RecomputeTimeSummary` → weekly cell; `GenerateInvoice` + `VoidInvoice`
  → monthly cell.
- Command `reports:refresh-rollups {--from=} {--to=}` (default trailing 90 days),
  scheduled nightly.

### Inc 2 — Report catalog + standard reports
- `/admin/reports` catalog page: report cards grouped Financial / Operational /
  Payroll, each gated by its permission; sidebar "Reports" goes live.
- `ReportController` + `routes/backoffice.php` block.
- Standard reports (each: filter bar + DataTable + totals row):
  - **Revenue by property** (financial) — grain month/quarter/year + date range,
    from `report_monthly_revenue`; columns invoices, work, adjustments, tax, total.
  - **Gross income vs payouts** (financial) — by property over a range, from
    `report_weekly_rollups`; bill vs pay vs margin.
  - **Hours by position** (operational) — week grain, property filter, from
    `report_weekly_rollups`; minutes per bucket + contractor count.
  - **Payouts by contractor** (payroll) — payroll-period picker + property filter,
    from `time_summaries`; hours, pay per bucket, total payout.

### Inc 3 — Exports + timesheet history
- Excel export per report (maatwebsite, same filters as the page; one generic
  export class fed by the report's rows + headings).
- Payouts-by-contractor PDF (dompdf) for payroll hand-off.
- `/admin/timesheets` history page (gated `timesheets.view_history`): DataTable of
  timesheets w/ period/property/status filters, links to grid + invoice, Excel
  export gated `timesheets.export`. Sidebar "Timesheets" goes live.

### Inc 4 — Tests + docs + seed
- Pest: rollup math (incl. invoice void corrects the month; import commit fills
  the week), trigger wiring, command window, permission gates per report,
  export downloads (xlsx/pdf headers), timesheet history filters + export gate.
- Seed: run `reports:refresh-rollups` at the end of `SampleDataSeeder` so a fresh
  seed has populated rollups.
- Docs: this file "as built", roadmap Phase 09 ✅, `app/Domain/README.md` +=
  Reports context, ERD += rollup tables.

## Gates (every increment)

Pint `--dirty` · Larastan `--memory-limit=512M` · `npm run types` · clean build ·
`php84 artisan migrate:fresh --seed` (MySQL) · full Pest suite.

## Verification (manual)

Fresh seed → log in as `office_manager@example.com`: Reports in sidebar → catalog
shows 4 reports → Revenue by property shows the two seeded invoiced months with
non-zero totals → Excel downloads. As `payroll@example.com`: payouts by contractor
for last period matches the grid; PDF downloads. As `hr@example.com`: financial
reports hidden, hours by position visible. Timesheets page lists 15 seeded
timesheets, filters work, export gated.
