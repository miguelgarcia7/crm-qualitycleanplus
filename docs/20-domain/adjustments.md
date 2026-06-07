# Adjustments

| Field | Value |
|---|---|
| Status | Accepted |
| Last updated | 2026-05-21 |
| Owner | Product + Engineering |

Adjustments are **per-contractor, per-week modifications** to billable or payable amounts that exist alongside (not as) time entries. Examples: perfect-attendance bonus (incentive), equipment damage charge (deduction).

## Two-table model

### `adjustment_items` (templates)

A catalog of reusable adjustment templates managed by office managers / payroll.

```
adjustment_items
  - id
  - name (e.g. "Perfect Attendance Bonus", "Uniform Damage")
  - default_value (cents, nullable — pre-filled when applying)
  - type: enum (incentive | deduction)
  - is_billable (bool, default false — incentives only; forced false for deductions)
  - active (bool)
  - created_at, updated_at, deleted_at
```

### `time_entry_adjustments` (applied adjustments)

A single adjustment applied to a contractor's hours in a specific period.

```
time_entry_adjustments
  - id
  - person_id (the contractor receiving the adjustment, FK)
  - work_order_id (FK)
  - property_id (FK — denormalized for fast queries)
  - payroll_period_id (FK)
  - adjustment_item_id (FK to template — nullable, only for manual adjustments)
  - source_type: enum (manual | supply_request | import | other)
  - source_id (nullable — references the source entity, e.g. supply_request.id)
  - value (cents — positive number always; type determines sign)
  - type: enum (incentive | deduction)
  - is_billable (bool — enforced rule: deductions always false)
  - notes (text, nullable)
  - created_by (FK to people)
  - created_at, updated_at
  - deleted_at (soft delete)
```

This is a separate table from `time_entries`. Adjustments are not time. They're money applied to a period.

(Naming: legacy uses `work_time_record_adjustments`. New name reflects the new schema. The "linked to" entity is the (work order, period) pair, not individual time entries.)

### The source_type discriminator

Adjustments enter the system from multiple sources, but they all land in the same table. The `source_type` column tells you where the row came from:

| `source_type` | `source_id` | `adjustment_item_id` | Origin |
|---|---|---|---|
| `manual` | null | required | Recruiter manually added an adjustment (used the catalog dropdown) |
| `supply_request` | required (the request's ID) | null | Auto-created when a uniform supply_request completes (deduction schedule) |
| `import` | null or batch reference | optional | Added during the import wizard's adjustment step |
| `other` | nullable | nullable | Reserved for future workflows |

**Key rule:** contractor charges from supply requests (uniforms, name tags, etc.) never use the `adjustment_items` catalog. The dropdown a recruiter sees when manually adding an adjustment has **no entries for these items**. Contractor charges enter the system exclusively via `supply_request` workflow completion. See ADR-0012 and ADR-0014.

## Billable rule

**Deductions are never billable.** This is enforced in two places:

- Database default + check constraint: `is_billable = false WHERE type = 'deduction'`
- Model boot: setting `is_billable = true` on a deduction silently corrects to false (with audit warning)

Incentives can be billable or not:

- **Billable incentive** → flows to invoice (PM sees it; property pays QCP for it) AND to payroll (contractor gets paid)
- **Non-billable incentive** → flows only to payroll (contractor gets paid; QCP eats the cost)

| Type | is_billable | Effect on invoice | Effect on payroll |
|---|---|---|---|
| Incentive | true | Added as line item (+) | Added (+) |
| Incentive | false | Not on invoice | Added (+) |
| Deduction | false (always) | Not on invoice | Subtracted (−) |

## When adjustments are created

Four sources, all producing rows in the same table:

1. **Manual — recruiter adds during the week (clock-in path):**
   - Opens the time entries grid for a contractor at a property
   - Clicks "Add Adjustment" → modal with item dropdown (from `adjustment_items` catalog), value, notes
   - Submits → adjustment created with `source_type=manual`, `adjustment_item_id=selected template`
   - Visible on the upcoming timesheet

2. **Manual — recruiter adds during the import wizard (import path):**
   - Step 4 of the import wizard (after match resolution and rate conflicts)
   - Per contractor row, optional adjustments using the same catalog
   - Submitted with the commit; adjustments created with `source_type=import`
   - Same transaction as time entries

3. **Supply request — contractor charge deduction (automatic):**
   - When a `supply_request` workflow completes with `category=uniforms` and `beneficiary_type=contractor` and `charge_amount > 0`
   - System creates a `contractor_charge_schedule` + N entries (1-4 per ADR-0014)
   - At the start of each scheduled payroll period, an entry auto-produces a `time_entry_adjustment` row with `source_type=supply_request`, `source_id=the request's ID`, `adjustment_item_id=null`
   - See `20-domain/inventory.md` for the schedule mechanism

4. **Correction after the fact (super_admin only):**
   - On a closed/invoiced period, super_admin can unlock and add/modify adjustments
   - Triggers void/reissue on the resulting invoice
   - Edge case; rare

## When adjustments are applied

When `RecomputeTimeSummary` runs for a (work_order, week):

- Sum incentive values → `incentive_total`
- Sum deduction values → `deduction_total`
- Add both to the summary row (separate columns)
- Pay total includes both (incentives +, deductions −)
- Bill total includes only billable incentives

When invoice is generated:

- Each billable incentive becomes one `invoice_adjustment` row
- Non-billable incentives and deductions are NOT on the invoice
- All three (billable incentive, non-billable incentive, deduction) are on the payroll export

## Edit gates

Adjustments can be added/edited/deleted while the payroll period is `open`. Once `locked` (timesheet sent for approval), only super_admin can change them. Once `invoiced`, edits require void/reissue.

## Permissions

| Action | Roles |
|---|---|
| Manage adjustment_items catalog | super_admin, office_manager, payroll |
| Create adjustment on own property | recruiter (period open), super_admin |
| Edit adjustment on own property | recruiter (period open), super_admin |
| Delete adjustment on own property | recruiter (period open), super_admin |
| View adjustments on own property | recruiter, property_manager, super_admin, office_manager |

## Examples

| Scenario | Setup |
|---|---|
| Reward for 30 consecutive days no callouts | Incentive item "Perfect Attendance", billable=true, default $50. Applied weekly by recruiter when earned. |
| Uniform damage charge | Deduction item "Uniform Damage", default $25. Applied by recruiter. Not billable. |
| Holiday bonus | Incentive item "Holiday Bonus", billable=true, default per-property variable. Applied to all eligible. |
| Cash advance recovery | Deduction item "Advance Recovery", value entered case-by-case. Recurring entries until paid back (one row per week). |

## Recurring adjustments

For things like contractor charge schedules (see `20-domain/inventory.md`), the system generates one `time_entry_adjustment` row per scheduled week. This avoids special "recurring adjustment" logic — each week has its own row, auditable independently.

## Related

- `20-domain/time-tracking.md` — how adjustments fit into summaries
- `20-domain/invoicing.md` — how billable adjustments become invoice lines
- `20-domain/inventory.md` — contractor charge schedules generate adjustments (source_type=supply_request)
- ADR-0007 — Staged timesheet approval (locking gates apply)
- ADR-0012 — Unified inventory (source_type discriminator)
- ADR-0014 — Contractor charge schedule (covers all contractor-charged items)
- `30-schema/time-tables.md` (TBD)
