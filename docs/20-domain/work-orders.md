# Work Orders

| Field | Value |
|---|---|
| Status | Accepted |
| Last updated | 2026-05-21 |
| Owner | Engineering + Product |

A **work order (WO)** is the assignment that links a contractor to a property at a specific position with specific pay/bill rates. Every billable contractor hour belongs to a work order — clock-in path or import path.

## Shape

```
work_orders
  - id
  - person_id (the contractor, FK to people)
  - property_id (FK)
  - position_id (FK)
  - pay_rate (cents)
  - bill_rate (cents)
  - ot_pay_rate (cents)
  - ot_bill_rate (cents)
  - start_date (date)
  - end_date (date, nullable — null means open-ended; REQUIRED when is_temporary_assignment=true)
  - status: enum (active | closed | suspended)
  - probationary_period_minutes (default 2080 = ~1 work-year worth)
  - source: enum (recruiter_created | imported | pay_increase_from_wo_id | transfer_from_wo_id | temporary_assignment_from_wo_id)
  - parent_wo_id (nullable — set when this WO supersedes another via pay increase, transfer, or temp assignment)
  - is_temporary_assignment (bool, default false — true when this is a temp WO from a `temporary_assignment` workflow; per ADR-0019)
  - more_staff_request_id (nullable FK to more_staff_requests — set when this WO was placed in response to a PM's request; per ADR-0021)
  - notes (text)
  - created_by, created_at, updated_at, deleted_at
```

## Required for every contractor

A contractor cannot accumulate billable time without an active work order. Three paths:

1. **Recruiter creates a WO manually** when placing a contractor at a clock-in property
2. **Import flow auto-creates a WO** if no active WO exists for (contractor, property)
3. **Pay increase / transfer workflows create a new WO** that supersedes the prior one

## Lifecycle

```
created (status=active)
   │
   │ contractor works; time entries reference this WO
   │
   ├── recruiter closes (e.g. transfer, pay change, end of season)
   │   └→ status=closed; end_date set
   │
   └── recruiter suspends (e.g. extended leave)
       └→ status=suspended; can be reactivated
```

A closed WO is final. Time entries for it remain queryable forever. The contractor moves to a new WO (or none) for future work.

## Rate authority

**The work order is the authoritative source of rates for the time entries it owns.** When a `time_entry` is created, its `pay_rate_snapshot` and `bill_rate_snapshot` come from this WO's current values (see ADR-0005).

The Property Bible's rates are **reference data**:

- They auto-fill the WO creation form
- They don't enforce the WO's actual rates (recruiter can override during creation)
- Changes to Bible rates do NOT propagate to existing WOs

This handles the common case where two contractors at the same property in the same position have different rates (often based on tenure, skill, or negotiated terms).

## Rate change rules

Rates on an existing WO can be edited only under specific conditions:

| Condition | Allowed |
|---|---|
| WO has no time entries yet | Yes, freely |
| WO has time entries; payroll period is open | Yes, but change is **effective next period only**; entries already in current period keep old rates |
| WO has time entries; payroll period is locked or invoiced | No — must use pay increase workflow to create new WO |

**Effective-next-period rule:** edits made today take effect at the start of the next payroll period. The system enforces this by:

- Adding a `pending_rate_change` jsonb column to the WO (holds the new rates + effective date)
- A scheduled job applies the change when the effective date is reached
- During the transition, time entries created get rates based on entry date relative to effective date

**Or** the simpler alternative we'll prefer for v1: **don't allow rate edits at all on WOs with time entries.** Instead, the **pay increase workflow always creates a new WO**:

```
Old WO closes (status=closed, end_date=last day of current period)
New WO opens (start_date=first day of next period, new rates)
Contractor's next clock-in uses the new WO automatically
Reports can join old and new via parent_wo_id
```

This is the **decided pattern** — simpler, cleaner audit trail, no "what rate was active when?" math at query time. See the pay increase flow in `40-flows/pay-increase.md`.

## Pay increase workflow integration

When a PM submits a pay increase request (via the workflow engine):

1. Request routes to the recruiter assigned to the property
2. Recruiter reviews and approves (or declines with reason)
3. On approval:
   - Old WO is closed (status=closed, end_date=current period end)
   - New WO is created (status=active, start_date=next period start, new rates from form)
   - New WO's `parent_wo_id` = old WO's id
   - New WO's `source` = `pay_increase_from_wo_id`
4. Audit log entry on both WOs
5. Contractor's next clock-in routes to the new WO

See `40-flows/pay-increase.md` for full step-by-step.

## Transfer workflow integration

When a recruiter **permanently transfers** a contractor to a different property (or different position at the same property):

1. Old WO closes (status=closed, end_date=transfer effective date)
2. New WO is created at the new property with new rates (from new property's Bible)
3. New WO's `parent_wo_id` = old WO's id
4. New WO's `source` = `transfer_from_wo_id`
5. New WO's `is_temporary_assignment` = false
6. The transfer date does NOT split mid-day: the old property's timesheet includes through the transfer day; new property starts the next day
7. If the new recruiter differs from the old: `people.primary_recruiter_id` updates to the new recruiter
8. Active `contractor_charge_schedule` entries are remapped to new property's equivalent payroll_periods
9. Audit log on both WOs

The transfer is NOT a "loss" attributed to the original recruiter for reporting purposes (per business rule).

See `40-flows/transfer.md` and ADR-0019 for the full flow.

## Temporary assignment workflow integration

When a contractor is **temporarily assigned** to another property (per ADR-0019):

1. Home WO at primary property: NO CHANGES (stays active)
2. A new temp WO is created at the temp property with:
   - `start_date` = temp start
   - `end_date` = temp end (REQUIRED for temp)
   - `is_temporary_assignment` = true
   - `parent_wo_id` = home WO's id
   - `source` = `temporary_assignment_from_wo_id`
   - rates from new property's Bible (editable)
3. `people.primary_recruiter_id` UNCHANGED
4. `contractor_charge_schedules` NOT remapped (they belong to the person)
5. Contractor has TWO active WOs during temp period; clock-in uses GPS to match the right one
6. Daily scheduled job `ProcessTemporaryAssignmentEnds` closes the temp WO when `end_date` arrives
7. After end_date: home WO is the only active WO again

See `40-flows/temporary-assignment.md` and ADR-0019 for the full flow.

## Closure rules

A WO can only be closed when:

- The contractor has clocked out of the active clock-in session (no open time entries)
- All time entries on the WO are in a closed or invoiced payroll period (or admin override)
- Recruiter or super_admin initiates the closure

Closure sets `status=closed` and `end_date`. The WO is read-only afterward.

## Suspension

A WO can be `suspended` for temporary leave (medical, personal). Suspended WOs:

- Don't appear in active rosters
- Cannot accept new time entries
- Can be reactivated (status returns to `active`)
- Reactivation does not change rates — rates remain as they were

Suspension is rarely used; for permanent end-of-relationship, use closure.

## Probationary period

The `probationary_period_minutes` (default 2080 minutes = ~34 hours = ~1 work-week) defines the trial period during which performance reviews are heightened. Used by recruiter dashboard warnings and termination workflows. Not enforced by the system, just tracked.

## Auto-creation on first import

When an import row references a (contractor, property) with no active WO:

1. System creates a WO automatically with:
   - Rates from the file (file rates are authoritative for imports — see `40-flows/import-hours.md`)
   - start_date = first day of the import period
   - position = matched from file's position field against the property's positions
   - source = `imported`
2. Subsequent imports reuse the WO if rates match
3. If file rates differ from WO rates: import preview surfaces this as a conflict; user picks "create new WO (rate change)" or "use existing WO and accept the rate"

See `40-flows/import-hours.md` for the full preview/commit flow.

## Permissions

Standard recruiter (own property) actions. See `10-architecture/permissions-matrix.md`.

## Related

- ADR-0005 — Rate snapshots on time entries
- `20-domain/property-bible.md` — where standard rates live
- `20-domain/time-tracking.md` — how WOs anchor time entries
- `40-flows/pay-increase.md` — workflow that creates new WOs
- `40-flows/import-hours.md` — auto-creation logic
- `30-schema/work-order-tables.md` — column-level detail (TBD)
