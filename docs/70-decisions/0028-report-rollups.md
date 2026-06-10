# ADR-0028: Report Rollup Tables (Weekly Operational + Monthly Financial)

| Field | Value |
|---|---|
| Status | Accepted |
| Decided | 2026-06-10 |
| Owner | Engineering |
| Supersedes | — |
| Superseded by | — |

## Context

Phase 09 needs "best-in-class reporting on pre-aggregated data" (roadmap): revenue,
gross income, payouts — by property, position, week, month, year — with the
acceptance bar "revenue by property by month loads in under one second".

The roadmap sketch named three rollups: *daily property, weekly position, monthly
revenue*. Two facts from earlier phases change that sketch:

1. **ADR-0008 already materialized the hardest aggregate.** `time_summaries` is a
   per-(work order, week) rollup with minutes and pay/bill cents per bucket,
   recomputed by `RecomputeTimeSummary` on every entry change. Contractor-grain
   reports (payroll's "payouts by contractor") need no new table at all.
2. **A daily grain cannot be truthful.** Imported properties (ADR — Phase 05) get
   one weekly total per contractor with no daily breakdown. A daily rollup would
   silently undercount any property on `time_source = import`. The week is the
   finest grain that every time source can honestly fill.

There are two distinct "money truths" to report on:

- **Operational** (what was worked): time summaries — exists the moment hours land,
  before approval. Right source for hours/utilization and *projected* revenue.
- **Financial** (what was billed): frozen invoices (ADR-0006) — includes
  adjustments and tax, excludes voids. Right source for *actual* revenue.

## Decision

Two new rollup tables, each owning one truth, plus reuse of `time_summaries`:

### `report_weekly_rollups` — operational, week × property × position

```
property_id, position_id, week_start, week_end
{regular,overtime,holiday,training}_minutes
total_minutes, total_pay, total_bill          (cents)
contractor_count                              (distinct people that week)
last_refreshed_at
UNIQUE(property_id, position_id, week_start)
```

A straight `GROUP BY` of `time_summaries` joined through `work_orders` for the
position. Serves: hours by position, gross income vs payouts, revenue by
property at week/month/quarter/year grain (summing ≤53 rows per property-position
per year keeps every query trivially under the 1-second bar).

### `report_monthly_revenue` — financial, month × property

```
property_id, month_start
invoice_count, work_subtotal, adjustment_total,
tax_amount, invoiced_total, payout_total      (cents)
last_refreshed_at
UNIQUE(property_id, month_start)
```

Computed from non-voided `invoices` (+ `invoice_items.total_payout`), keyed by the
payroll period's `week_start` month. Serves the headline "revenue by property by
month/year" report with billed truth.

### Refresh strategy: targeted triggers + nightly backstop

- `RefreshWeeklyRollup::handle($propertyId, $weekStart)` and
  `RefreshMonthlyRevenue::handle($propertyId, $monthStart)` are idempotent
  delete-and-rebuild actions scoped to one cell.
- **Triggers:** `RecomputeTimeSummary` refreshes the weekly cell it just touched;
  invoice freeze (`GenerateInvoice`) and `VoidInvoice` refresh the monthly cell.
- **Backstop:** `reports:refresh-rollups {--from=} {--to=}` rebuilds a window
  (default: trailing 90 days), scheduled nightly — heals any drift from manual DB
  surgery, replayed imports, or missed triggers.

Rollups are derived data: safe to truncate and rebuild at any time, never a
source of truth, never migrated in cutover (Phase 10 just runs the command).

## Alternatives considered

- **Query `time_summaries`/`invoices` directly with indexes.** Would likely meet
  the 1-second bar at today's volume, but the roadmap explicitly commits to
  materialized rollups, and the report layer reading dedicated tables keeps
  report queries independent of operational-schema churn.
- **Daily property rollup (as sketched).** Rejected: dishonest under imported
  weeks (see Context). Daily detail where it truly exists (clock/manual entries)
  remains available to dashboards via `time_entries`; revisit if a daily report
  is ever requested, scoped to non-import properties.
- **One wide rollup with a `grain` column.** Harder to index, easy to misquery;
  two narrow tables with clear truths are simpler.

## Consequences

- Reports never join operational tables at request time; the heaviest report is
  a sum over a few hundred narrow rows.
- Voiding an invoice retroactively corrects the monthly revenue rollup (the
  trigger refreshes the cell), so financial reports always reflect current truth.
- The weekly rollup double-counts nothing across positions: a work order has one
  position; transfers create new work orders (ADR-0019).
- Subscriptions/scheduled emails (roadmap "optional") are deferred; the rollup
  layer is the prerequisite and ships now.
