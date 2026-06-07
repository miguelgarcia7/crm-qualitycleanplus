# ADR-0014: Contractor Charge Schedule (rename + broaden from uniform-only)

| Field | Value |
|---|---|
| Status | Accepted |
| Decided | 2026-05-21 |
| Owner | Product (David) + Engineering |
| Supersedes | — (refines ADR-0012's uniform_deduction_schedule design) |
| Superseded by | — |

## Context

ADR-0012 introduced `uniform_deduction_schedules` and `uniform_deduction_schedule_entries` to handle uniform charges paid via payroll deduction over N periods. During design review, two refinements surfaced:

1. **Other contractor-charged items exist** beyond uniforms — name tags ($5), and potentially other small accessories. They should follow the same mechanism (a charge against the contractor's paycheck), not a separate one.
2. **Contractors should see a unified "Outstanding Charges" view** on their profile that aggregates everything they owe, regardless of which supply request originated it.

Plus a related decision on split bounds and a small naming cleanup.

## Decision

### 1. Rename the tables

- `uniform_deduction_schedules` → **`contractor_charge_schedules`**
- `uniform_deduction_schedule_entries` → **`contractor_charge_schedule_entries`**

The mechanism is unchanged; the name reflects that it covers any contractor charge, not just uniforms.

### 2. Broaden the trigger

The schedule is created whenever a `supply_request` is fulfilled with:

- `beneficiary_type = contractor`
- `charge_amount > 0`
- Regardless of category (Uniforms, custom future categories, etc.)

Practically: works for uniforms, name tags, and any future contractor-charged items.

### 3. Split bounds: 1 to 4 payments

The `split_payments` field on a supply_request can be 1, 2, 3, or 4.

- **1** = single deduction in the next pay period (e.g. name tags, small items)
- **2-4** = split across consecutive pay periods (e.g. uniforms, larger items)

The UI defaults intelligently by amount:

| Amount | Default split |
|---|---|
| ≤ $10 | 1 |
| $11-$50 | 2 |
| $51-$150 | 3 |
| > $150 | 4 |

Recruiter can override within 1-4. UI shows the per-period amount live as they choose.

Schemas enforce: `split_payments BETWEEN 1 AND 4`.

If we ever see contractor-charged items above ~$200 that legitimately need more splits, revisit this with a new ADR.

### 4. Unified "Outstanding Charges" view on contractor profile

In QC Minute, the contractor's profile shows an aggregated view of all active `contractor_charge_schedules` for that person:

```
Outstanding Charges: $35
  ── Housekeeping Polo (May 14)        Paid 2 of 3   $10 remaining
  ── Name Tag (May 18)                 Scheduled     $5 next period
  ── Black Pants (May 21)              Paid 1 of 4   $20 remaining

Upcoming pay period deduction: $15
```

Data model is unchanged (multiple schedules per contractor); the UI aggregates them.

This same view appears on the back-office side under the contractor's profile for recruiters and HR.

## Consequences

### Positive

- One mechanism handles all contractor-paid items (uniforms, name tags, future items)
- Contractor sees one "balance" mental model while we keep per-item audit trail
- Cancelling a return-cleared item only affects that schedule, not the whole balance
- Termination consolidation logic stays clean (sum all active schedules' remaining entries)
- Naming reflects actual scope; no future "uniform_deduction is misleading" tech debt

### Negative

- Table rename touches all references in `inventory.md`, `adjustments.md`, ADR-0012, supply-request.md
- The "balance" view is a UI rollup, not a stored field — must be computed on read. (Cheap query: SUM remaining entries grouped by person.)

### Implementation requirements

Schema:

```sql
contractor_charge_schedules
  - id
  - source_request_id (FK to supply_requests)
  - person_id (the contractor being charged)
  - total_amount (cents)
  - num_payments (int, CHECK BETWEEN 1 AND 4)
  - amount_per_payment (cents — total / num_payments, with rounding handled in the last entry)
  - status: enum (active | completed | cancelled | accelerated_to_final_paycheck)
  - created_at, updated_at

contractor_charge_schedule_entries
  - id
  - schedule_id (FK)
  - payroll_period_id (FK)
  - amount (cents)
  - payment_index (int, 1..num_payments)
  - status: enum (scheduled | applied | skipped)
  - applied_adjustment_id (FK to time_entry_adjustments)
  - created_at, updated_at
```

Form validation:

- `split_payments` enum: 1, 2, 3, or 4
- Default chosen by amount-based lookup (see decision section)
- Per-period amount calculated live as user changes split

Outstanding Charges view:

- Backend: query `contractor_charge_schedules` where `person_id = ? AND status IN ('active')`
- For each: count entries with `status = 'applied'` and `status = 'scheduled'`
- Display: name (from source_request item), payment progress, remaining

## Alternatives considered

### A. Single "balance" field on contractor (not multiple schedules)

Rejected. Loses per-item audit. Cancelling one returned uniform would be ambiguous against the total balance.

### B. Keep uniform_deduction_schedule name, just allow it to hold non-uniform items

Rejected. Name becomes misleading; new developers and ADR readers will be confused.

### C. Unlimited split count

Rejected. With our cost ceiling around $200 and a reasonable per-period deduction target of $50, 4 is the practical max. Unbounded would let someone create a 50-period schedule by accident.

## Related

- ADR-0012 — Unified inventory with three categories (this ADR refines the schedule design from that one)
- `20-domain/inventory.md` — full domain model
- `20-domain/adjustments.md` — source_type='supply_request' adjustments come from these schedules
- `40-flows/supply-request.md` — request flow
- `50-ui/qc-minute-contractor.md` — Outstanding Charges view (TBD spec file)
