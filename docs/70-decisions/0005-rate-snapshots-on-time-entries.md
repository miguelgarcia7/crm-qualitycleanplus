# ADR-0005: Rate Snapshots on Time Entries

| Field | Value |
|---|---|
| Status | Accepted |
| Decided | 2026-05-21 |
| Owner | Engineering + Product |
| Supersedes | — |
| Superseded by | — |

## Context

Pay rates and bill rates change over time. A contractor at a property may earn $18/hr in February and $20/hr in March. When generating a March 1 report, we need to know:

- Hours worked in February → billed/paid at February's rate
- Hours worked in March → billed/paid at March's rate

In the legacy QC Minute system, rates live on the `work_orders` table. A time record (WTR) joins to the work order to compute its dollar value. If the work order's rate is updated *after* the WTR was logged, the WTR's historical dollar value silently changes — meaning last quarter's revenue number is no longer the same number as when the quarter closed.

This breaks historical reporting and creates audit problems.

## Decision

**Every time entry carries its own pay rate and bill rate as snapshot columns, copied from the work order at the moment the time entry is created.**

```
time_entries
  - pay_rate_snapshot (cents)
  - bill_rate_snapshot (cents)
  - ot_pay_rate_snapshot (cents)
  - ot_bill_rate_snapshot (cents)
  - ... (other dimensions that affect billing/payout)
```

When a work order's rate changes (whether through edit, new contract, or rate increase workflow):

- Existing time entries are NOT touched
- New time entries from the moment of change forward use the new rate
- Reports stay historically stable: last quarter's number is the same number forever

This extends an existing principle in the system (invoice snapshots) down one level deeper.

## Consequences

### Positive

- Reports are historically stable — past numbers never change retroactively
- Audit trail is clean — every billable minute has its rate documented at the point of work
- Tax/dispute scenarios are easy — "what rate was applied to John's hours on March 14?" is a column lookup
- Rate changes don't require complex "what about already-logged-hours?" logic
- Aligns with the invoice freeze pattern (ADR-0006)

### Negative

- Schema is wider — 4 extra cents columns on `time_entries`
- Slightly more data per row (negligible at any reasonable scale)
- Devs must remember to read `pay_rate_snapshot`, not join through to work order, in any code path that computes money

### Implementation requirements

- All money fields are integer cents (project convention, see `30-schema/conventions.md`)
- `time_entries` model has `pay_rate_snapshot`, `bill_rate_snapshot`, `ot_pay_rate_snapshot`, `ot_bill_rate_snapshot` (all NOT NULL)
- Time entry insert path is responsible for populating snapshots from the work order
- `time_summaries` materialized rollup uses snapshots, not live rates
- Reports query `time_summaries` (which already has snapshots baked in)
- A linter/test verifies no code computes dollars from a work order's *current* rate when computing historical values

## Alternatives considered

### A. Leave rates on the work order, compute money live every time

Rejected — the legacy approach. Causes the "last quarter's revenue changed" problem.

### B. Append-only rate history on work orders, time entries reference a specific historical row

Rejected as overengineering. A `work_order_rates` table with `effective_from` / `effective_to` would work, but reading it requires joining and filtering by date — slower and more error-prone than denormalized snapshots. The snapshot approach is what the database itself wants.

### C. Snapshot only on `time_summaries`, not on `time_entries`

Rejected. The summary is downstream of the entry. If we ever need to recompute a summary, the source of truth must include the rate; otherwise we're re-reading from the (possibly changed) work order, which defeats the purpose.

## Related

- ADR-0006 — Invoice freeze + void/reissue (parallel principle for invoices)
- ADR-0008 — Time entries + summaries (this snapshot lives on the entry layer)
- `20-domain/work-orders.md` — rate authority and change rules
- `20-domain/time-tracking.md` — entry creation flow
- `30-schema/time-tables.md` — column-level detail
