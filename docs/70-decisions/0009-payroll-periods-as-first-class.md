# ADR-0009: Payroll Periods as First-Class Entities

| Field | Value |
|---|---|
| Status | Accepted |
| Decided | 2026-05-21 |
| Owner | Engineering |
| Supersedes | — |
| Superseded by | — |

## Context

"What week is this?" sounds trivial but in the legacy system it's actually implicit:

- Properties have a `closing_day` (day of month) for billing cycles
- The work-week boundary is computed from `start_time` dates via query logic scattered across services
- "Can we still edit this time record?" is computed by checking "is there an approved timesheet for the week this record falls in?"
- "Should we generate an invoice yet?" depends on multiple loose checks

This is fragile. Date math gets reimplemented in every report. Bugs creep in around DST transitions, week boundaries, and timezone misalignment.

## Decision

**Make payroll periods a first-class table.** Every property has one row per pay week, created in advance by a scheduled job.

```
payroll_periods
  - id
  - property_id
  - week_start (date, inclusive, in property's timezone)
  - week_end (date, inclusive, in property's timezone)
  - status: enum (open | locked | invoiced | closed)
  - locked_at, locked_by (set when timesheet is submitted for approval)
  - invoiced_at (set when invoice is generated)
  - closed_at, closed_by (set when fully reconciled)
  - UNIQUE(property_id, week_start)
```

Every `time_entry` and `time_summary` references a `payroll_period_id`. Every timesheet and invoice references one.

### Status transitions

```
open      → time_entries can be created/edited/deleted freely
locked    → only super_admin can edit; timesheet is in approval flow
invoiced  → invoice exists; void+reissue required for changes (per ADR-0006)
closed    → final state; no further changes possible
```

A scheduled job creates `payroll_periods` for each active property ~7 days ahead of each week start. Manual creation is also possible (for new properties or backfills).

## Consequences

### Positive

- "What week is this?" is a row lookup, not a date calculation
- Edit gates are a single status check, not scattered logic
- DST and timezone bugs are confined to period creation, not report queries
- New properties get periods auto-created
- Backfilling historical periods is a one-time data migration
- Period-level audit (`locked_by`, `invoiced_at`) is automatic
- Reports group by period_id, not date math

### Negative

- One more table to think about during schema design
- Period creation job must be reliable (a missing period would block time entry creation; mitigated by alerting + auto-create-on-demand fallback)
- Migration from legacy data requires creating periods for every historical week

### Implementation requirements

- `payroll_periods` table created in foundation migrations
- Scheduled job `EnsurePayrollPeriodsCreated` runs daily; creates the next 4 weeks of periods for every active property
- Manual artisan command `payroll-periods:backfill {property_id}` for new property onboarding
- Every model that records time references `payroll_period_id` (FK, NOT NULL): `time_entries`, `time_summaries`, `timesheets`, `invoices`, `contractor_fee_deductions`
- Policies check `payroll_period.status` to gate edits
- Status transition events fire on each transition (closed → notifications, etc.)
- Timezone for period boundaries is the *property's* timezone, not UTC and not the requesting user's

## Alternatives considered

### A. Continue implicit date-based gating

Rejected. Already failing — multiple bugs in the legacy system trace back to inconsistent week-boundary computation.

### B. Single global pay period (everyone closes the same week)

Rejected. Properties have different `closing_day` values today; collapsing into one global cycle would require a business change we're not making.

### C. Period as a view, not a table

Rejected. A view computed from dates doesn't give us a stable ID to reference from other tables. The whole point is to anchor everything to a real row.

## Related

- ADR-0008 — Time entries + summaries (both reference payroll_period_id)
- ADR-0007 — Staged timesheet approval (period status drives gates)
- `20-domain/time-tracking.md` — periods in context
- `30-schema/time-tables.md`

## Amendment — 2026-06-10: per-property week anchors

Hotels do not share a week boundary: some close their work week on Sunday,
others mid-week (e.g. Wednesday → Thursday-to-Wednesday timesheets). The
Property Bible's `closing_day` is therefore the **ISO day-of-week the
property's week ends on** (1 = Mon … 7 = Sun; unset = Sunday, preserving the
original Monday-to-Sunday default) — *not* a day of month as in the legacy
billing-cycle sense.

All week math flows through one helper, `Property::weekStartFor($date)`:
`payroll:ensure-periods` materializes anchored periods, and clock-in, manual
entry, the weekly grid, and import validation resolve a date to its period via
the same anchor. Report rollup cells inherit the anchor through
`time_summaries.week_start`, so "weekly" rollups are property-local weeks.
