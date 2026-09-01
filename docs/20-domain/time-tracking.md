# Time Tracking

| Field | Value |
|---|---|
| Status | Accepted |
| Last updated | 2026-05-21 |
| Owner | Engineering + Product |

Time tracking is the core engine: every billable hour, every dollar paid or charged, ultimately resolves to a row in `time_entries`. Reports run against pre-aggregated `time_summaries`.

The model is documented in ADR-0008 (split schema rationale) and ADR-0005 (rate snapshots) and ADR-0009 (payroll periods).

## The three tables

### `payroll_periods`

A property's pay week, materialized as a row. Created in advance by a scheduled job.

```
payroll_periods
  - id
  - property_id
  - week_start (date, property's timezone)
  - week_end (date, property's timezone)
  - status: enum (open | locked | invoiced | closed)
  - locked_at, locked_by (set when timesheet submitted for approval)
  - invoiced_at (set when invoice generated)
  - closed_at, closed_by
  - UNIQUE(property_id, week_start)
```

See ADR-0009 for full lifecycle and rationale.

### `time_entries` (raw events)

```
time_entries
  - id
  - person_id (contractor)
  - work_order_id
  - property_id
  - payroll_period_id
  - source: enum (clock_event | manual_entry | imported | adjustment_credit)
  - clock_method: enum (tablet | qr | manual) — only relevant for source=clock_event
  - entry_type: enum (work | training)
  - start_at_utc, end_at_utc (nullable for imported totals)
  - duration_minutes
  - timezone (string)
  - pay_rate_snapshot (cents)        ← copied from WO at creation
  - bill_rate_snapshot (cents)
  - ot_pay_rate_snapshot (cents)
  - ot_bill_rate_snapshot (cents)
  - source_metadata (jsonb — device_id, import_batch_id + row_number, etc.)
  
  -- GPS + selfie capture (for source=clock_event; nullable otherwise)
  - clock_in_gps_lat, clock_in_gps_lng (decimal, nullable)
  - clock_in_gps_accuracy_meters (int, nullable)
  - clock_in_selfie_file_id (FK to files, nullable)
  - clock_out_gps_lat, clock_out_gps_lng (decimal, nullable)
  - clock_out_gps_accuracy_meters (int, nullable)
  - clock_out_selfie_file_id (FK to files, nullable)
  
  - was_updated (bool — true if a non-contractor edited)
  - created_by (FK to people)
  - created_at, updated_at, deleted_at
```

One row per atomic event. Sources:

| Source | When created | Has start/end? | clock_method |
|---|---|---|---|
| `clock_event` | Contractor clocks in/out (tablet OR QR) | Yes — both UTC and local | tablet OR qr |
| `manual_entry` | Recruiter enters a missed punch | Yes — both UTC and local | manual (or null) |
| `imported` | Import wizard commits | No — only `duration_minutes` | n/a |
| `adjustment_credit` | A negative entry created to correct a prior error | Yes (or no, depending) | n/a |

### Clock methods

For `source = clock_event`, the `clock_method` field captures HOW the contractor clocked in:

| clock_method | Description | GPS + selfie? |
|---|---|---|
| `tablet` | Property-locked Sanctum-authenticated tablet (legacy flow; still supported as backup) | No GPS/selfie capture; the tablet's property association is the verification |
| `qr` | Contractor scanned property QR code on their own phone, entered phone number, picked work order (per ADR-0017) | Yes — GPS validated against property geofence; selfie required |
| `manual` | Recruiter or admin entered the time on contractor's behalf | No; created_by captures who did it |

QR is the primary modern flow. Tablets continue to function as backup for contractors without smartphones. See ADR-0017 and `40-flows/contractor-clock-in.md`.

### `time_summaries` (materialized rollups)

```
time_summaries
  - id
  - person_id (contractor)
  - work_order_id
  - property_id
  - payroll_period_id
  - week_start, week_end
  - regular_minutes, overtime_minutes, holiday_minutes, training_minutes
  - regular_amount_pay, overtime_amount_pay, holiday_amount_pay, training_amount_pay (cents)
  - regular_amount_bill, overtime_amount_bill, holiday_amount_bill, training_amount_bill (cents)
  - total_pay, total_bill (cents)
  - last_recomputed_at
  - UNIQUE(work_order_id, week_start)
```

Recomputed by a background job after any time_entry insert/update/delete/restore. Reports query this; never compute buckets at report time.

## Time entry lifecycle

### Clock-in

```
Contractor on device
  → presses "Clock In", selects work order from active list
  → POST /device/clock-in
     time_entry created:
       source = clock_event
       start_at_utc = now
       start_at = now in property timezone
       end_at_utc = NULL
       end_at = NULL
       duration_minutes = NULL
       rate snapshots copied from work_order
       timezone = property's timezone
  → ClockedIn event fires
     → Recompute summary job dispatched (no-op while still open)
     → PM and recruiter broadcasts notified (live grid update)
```

### Clock-out

```
Contractor on device
  → presses "Clock Out"
  → POST /device/clock-out
     in-progress time_entry updated:
       end_at_utc = now
       end_at = now in property timezone
       duration_minutes = diff(end_at_utc, start_at_utc)
  → ClockedOut event fires
     → Recompute summary job dispatched (now actually computes)
     → PM and recruiter broadcasts notified
```

### Manual entry

```
Recruiter on back office
  → time-entries-table view for a property's week
  → fills in (or edits) in/out times for a contractor's day
  → POST .../time-entries/manual
     time_entry created:
       source = manual_entry
       start_at, end_at, timezone, duration calculated
       rate snapshots from work_order
  → Recompute summary job dispatched
```

### Import

See `40-flows/import-hours.md` for the full wizard flow. On commit:

```
For each resolved row in import_batch:
  time_entry created:
    source = imported
    start_at, end_at = NULL
    duration_minutes = total from file (converted to minutes)
    rate snapshots from file (file is authoritative for imports)
    source_metadata = { import_batch_id, row_number, original_external_id }
  
  Each entry assigned to the import's payroll_period and matched work_order
  Adjustments (if added during wizard) created as separate time_entries with source=adjustment_credit

Recompute summary job dispatched for all affected (work_order, week) pairs
```

### Adjustment credits

An adjustment credit is a *new* time entry with source = `adjustment_credit`, for time that was never recorded at all. Example: "add 0.5h to John on Tuesday because the time clock missed the entry." It appears as a separate row because there is no original punch to correct.

This is distinct from correcting a punch that exists but has the wrong times — see **Correcting a punch** below. Use an adjustment credit when time is missing; correct the entry when the time is recorded but wrong.

Adjustment items (incentives, deductions) are *not* time entries — they live in their own table. See `20-domain/adjustments.md`.

## Hourly bucketing

When `RecomputeTimeSummary` runs for a (work_order, week), it executes the bucketing algorithm:

```
1. Load all time_entries for this (work_order, week), oldest first
2. Load the property's ATTACHED holidays, resolved in the property's timezone
   for every year the week touches (weeks can span New Year)
3. Walk day-by-day (keyed by the entry's property-local start date):
   - For each day, sum duration_minutes
   - Subtract training_minutes (entry_type = training) → training bucket
   - If date is a property holiday → holiday bucket (never overtime, but the
     minutes still advance the weekly 40h counter, so holiday hours push
     later days into OT)
   - Else: track running weekly total
       - If adding this day pushes weekly past 40h:
           - Split: some goes to regular bucket (up to 40h cap)
           - Remainder goes to overtime bucket
       - Else: add to regular bucket
4. Compute amounts:
   - regular_amount_pay = regular_minutes / 60 * pay_rate (WO rate)
   - overtime_amount_pay = overtime_minutes / 60 * ot_pay_rate
   - holiday_amount_pay = holiday_minutes / 60 * (pay_rate × qcp.time.holiday_multiplier, default 1.5)
     — deliberately derived from the BASE rate, not the OT rate, so an
     overridden OT rate never changes holiday pay
   - training_amount_pay = training_minutes / 60 * pay_rate (not billed)
   - same for bill
5. Sum to total_pay, total_bill
6. Write/update time_summaries row
```

Bucketing rules are configurable (overtime threshold, holiday multiplier) but stable for v1.

## Live PM view

PMs at clock-in properties see a **live read-only grid** of the current week:

- Rows: contractors with active WOs at the property
- Columns: days of the week
- Cells: clock-in/out times for that day, or running duration if still clocked in
- Status: live updates via broadcast on every clock event
- Bottom totals row: hours summed per day, per contractor

The grid is read-only — PMs can't edit. Their only action is on the timesheet (submitted by recruiter), not the live grid.

Recruiters see the same grid with **edit affordance** (add manual entry, edit a punch with permission). Recruiter edits create or modify time_entries directly.

### Correcting a punch

Clicking the times on a punch opens a correction form (`PATCH /admin/time-entries/{id}`, gated on `time_entries.edit`). Three constraints are deliberate:

- **The week must still be open.** Same guard the manual-entry path uses; once the period is locked the times are part of an approved timesheet.
- **The date cannot move.** Shifting a punch to another day could land it in a different payroll period, which is a delete-and-re-add, not an edit. An end time earlier than the start is read as spanning midnight.
- **Rate snapshots are not re-taken.** The entry keeps the rates that applied when the work was done (ADR-0005) — correcting a time must never silently re-price historical work at today's rates.

The correction sets `was_updated` and writes one `updated` event to the `payroll` activity log carrying both the old and the new times, rather than the delete/create pair a correction used to leave behind. An open punch (no clock-out) opens with an empty end time, so this is also the manual way to close one.

## Long-running clock-in detection

**Status: not built.** No job, no notification, and no `last_notified_at` column exist yet. The back-office dashboard's "On the clock now" panel is the current stand-in — it lists open punches longest-first and flags anything past the same 10-hour threshold. Spec below is the intended behaviour.

A scheduled job (`LookForLongTimeEntries`) runs every 10 minutes:

- Finds open `clock_event` time_entries with `start_at_utc > 10 hours ago`
- Sets `last_notified_at` to now (8-hour cooldown to prevent spam)
- Dispatches a notification to the property's employee managers
- Contractor may have forgotten to clock out; manager investigates

Already a pattern in legacy QC Minute; carrying forward.

## Long-shift handling

A clock event that spans midnight (start 8pm Monday, end 4am Tuesday) is **one time entry** with start on Monday and end on Tuesday. Bucketing splits it appropriately — Monday's portion counts toward Monday's day-total, Tuesday's portion toward Tuesday's. This is computed when the entry is closed (clock-out).

## Permissions

| Action | Roles |
|---|---|
| View live time data (own properties) | recruiter, property_manager, office_manager, super_admin |
| Create manual entries | recruiter (own, period open), office_manager, super_admin |
| Edit entries | recruiter (own, period open), super_admin |
| Delete entries | recruiter (own, period open), super_admin |
| Approve timesheet | property_manager (own), super_admin |
| Override locked period | super_admin only |

Full details in `10-architecture/permissions-matrix.md`.

## Related

- ADR-0005 — Rate snapshots
- ADR-0008 — Time entries + summaries split
- ADR-0009 — Payroll periods as first-class
- `20-domain/work-orders.md` — entry source
- `20-domain/timesheets.md` — entries roll up here
- `20-domain/adjustments.md` — incentives/deductions
- `40-flows/clock-in-out.md`
- `40-flows/import-hours.md`
- `30-schema/time-tables.md` — column-level (TBD)
