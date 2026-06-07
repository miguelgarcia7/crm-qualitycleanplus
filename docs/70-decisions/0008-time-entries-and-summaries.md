# ADR-0008: Time Entries + Time Summaries (Split Schema)

| Field | Value |
|---|---|
| Status | Accepted |
| Decided | 2026-05-21 |
| Owner | Engineering |
| Supersedes | — |
| Superseded by | — |

## Context

The legacy QC Minute system uses a single `work_time_records` (WTR) table for everything time-related:

- Raw clock-in/out events
- Computed dollar amounts for billing
- Source for bucketing (regular / OT / holiday / training)
- Source for reports

This causes three problems:

1. **Reports are slow.** Every dashboard query joins WTR → work_order → property → job_type and recomputes buckets on the fly. Acceptable today; won't scale.
2. **Imports are awkward.** A weekly total imported from a hotel's payroll system doesn't have clock-in/out times — it gets shoehorned into a separate `manual_invoice_data` table that bypasses the standard flow.
3. **Adjustments and adjustments-to-history are confused.** When a manager edits an old time record, it's hard to tell what the *original* events were vs the corrections.

We need a schema that handles all sources of "billable time" uniformly while keeping reports fast.

## Decision

**Split into two tables: `time_entries` for raw events, `time_summaries` for pre-aggregated weekly rollups.**

### `time_entries` (raw events, source of truth for what happened)

```
time_entries
  - id, person_id (contractor), work_order_id, property_id
  - source: enum (clock_event | manual_entry | imported | adjustment_credit)
  - entry_type: enum (work | training)
  - start_at_utc, end_at_utc (nullable; null for imported totals)
  - duration_minutes (computed from start/end for events; given for imports)
  - week_start, week_end (denormalized for index)
  - payroll_period_id (FK)
  - pay_rate_snapshot, bill_rate_snapshot, ot_pay_rate_snapshot, ot_bill_rate_snapshot
  - timezone (string, for clock events)
  - source_metadata (jsonb — import batch ID, device ID, original file row, etc.)
  - was_updated (bool, marked when non-contractor edits)
  - created_by, created_at, updated_at
  - deleted_at (soft delete)
```

One row per atomic event. Clock-in/out creates one entry per shift. Import creates one entry per (contractor, work order) with the weekly total.

### `time_summaries` (materialized weekly rollups, source of truth for what billed)

```
time_summaries
  - id, person_id (contractor), work_order_id, property_id
  - payroll_period_id
  - week_start, week_end
  - regular_minutes, overtime_minutes, holiday_minutes, training_minutes
  - regular_amount_pay, overtime_amount_pay, holiday_amount_pay, training_amount_pay
  - regular_amount_bill, overtime_amount_bill, holiday_amount_bill, training_amount_bill
  - total_pay, total_bill
  - last_recomputed_at
  - UNIQUE(work_order_id, week_start)
```

One row per (work order, week). Computed from time_entries via a background job (`RecomputeTimeSummary`) triggered after any time_entry insert/update/delete.

### Reports always read from summaries

```
✅ SELECT SUM(total_bill) FROM time_summaries WHERE week_start BETWEEN ? AND ?
❌ SELECT (computed_bucket_math) FROM time_entries JOIN work_orders ...
```

Reports never compute bucketing live. Bucketing is done once, when the summary is recomputed.

## Consequences

### Positive

- Reports become fast and trivially cacheable
- Imports are first-class: one source flag handles them
- Bucketing is computed once, not on every query
- Time entries become an immutable-ish event log (still editable but treated as the truth of what happened)
- Adjustments-as-credits (a negative time entry) fit cleanly
- New report types are easy: add a column to summaries, recompute, query
- Replaying / rebuilding summaries is straightforward (delete + recompute job)

### Negative

- Two tables to maintain, with the rule that summaries must always be in sync with entries
- Background job complexity — a missed recomputation could cause a report to be wrong
- Storage cost slightly higher (summaries duplicate denormalized data)
- Order-of-operations sensitivity (insert → trigger → job → recompute must complete before report runs against the new data; eventual consistency window)

### Implementation requirements

- `time_entries` and `time_summaries` tables created together in foundation migrations
- `RecomputeTimeSummary` job dispatched on every time_entry model event (created/updated/deleted/restored)
- Job idempotent — running it twice for the same (work_order_id, week_start) produces the same result
- Bucketing logic lives in one place (a service class) and is called only by `RecomputeTimeSummary`
- Reconciliation job runs weekly: validates that every (work_order_id, week_start) with time_entries has a corresponding time_summary; flags drift
- Manual "rebuild all summaries" artisan command for emergencies
- Tests cover: import → entry → summary, clock event → summary, adjustment → summary, edit → summary, delete → summary

## Alternatives considered

### A. Single WTR table (legacy approach)

Rejected. Reports get slower over time; imports remain awkward.

### B. Materialized view at the database level

Rejected. MySQL doesn't have first-class materialized views (only emulated via tables + triggers). Application-layer recomputation gives us more control (we can fix bugs, replay, throttle) and works the same in dev and prod.

### C. Read replicas for reports, but keep single table

Rejected. Doesn't solve the bucketing-on-every-query problem; just moves it to a different DB instance.

### D. Event sourcing (event store + projection)

Rejected as overengineering for this scale. The `time_entries` + `time_summaries` split is event-sourcing-light — the entries are the events, the summaries are the projection. Full event sourcing would add infrastructure complexity we don't need.

## Related

- ADR-0005 — Rate snapshots on time entries (the snapshot columns live on entries)
- ADR-0009 — Payroll periods as first-class (entries reference a payroll_period)
- `20-domain/time-tracking.md` — full time tracking model
- `30-schema/time-tables.md` — column-level detail
- `40-flows/clock-in-out.md` — clock-in path that creates entries
- `40-flows/import-hours.md` — import path that creates entries
