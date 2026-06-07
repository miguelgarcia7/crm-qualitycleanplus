# Inventory

| Field | Value |
|---|---|
| Status | Accepted (v1) |
| Last updated | 2026-05-21 |
| Owner | Product + Engineering |

Inventory tracks all physical items QCP holds in stock and issues. Three default categories — **Office Supplies, Uniforms, Equipment** — with the ability to add more. One unified workflow (`supply_request`) handles all requests across all categories. Category-driven behavior on completion (deduction schedule for uniforms, assignment record for equipment, nothing extra for office supplies).

See ADR-0012 for the rationale.

## Categories

```
categories
  - id
  - name (e.g. "Office Supplies", "Uniforms", "Equipment", or custom)
  - slug (e.g. "office_supplies", "uniforms", "equipment")
  - has_variants (bool — true for uniforms by default, false otherwise)
  - active (bool)
  - created_at, updated_at
  - UNIQUE(slug)
```

- Three seeded by default: Office Supplies, Uniforms, Equipment
- super_admin + office_manager can create new categories
- Categories can be renamed
- Categories **cannot be deleted** once any item is assigned to them — deactivate instead
- `has_variants` controls the item-creation form (true = ask for size + color)

## Items + variants

```
items
  - id
  - name
  - category_id (FK)
  - description (text)
  - has_variants (bool, inherited from category but overridable per item)
  - active (bool)
  - created_by, created_at, updated_at, deleted_at

item_variants
  - id
  - item_id (FK)
  - size (varchar, nullable — only for items where has_variants=true)
  - color (varchar, nullable)
  - sku (varchar, nullable, optional internal code)
  - current_stock (int, default 0)
  - reorder_threshold (int, default 0 — 0 means "not tracked for reorder")
  - active (bool)
  - created_at, updated_at, deleted_at
  - UNIQUE(item_id, size, color)
```

**Dynamic form by category:**

- **Office Supplies** (`has_variants = false` default): item form asks for name + description. One variant created automatically with size = null, color = null. Stock and threshold tracked on that single variant.
- **Uniforms** (`has_variants = true`): item form asks for name + description + a list of size/color variants. Each variant has its own stock and threshold.
- **Equipment** (`has_variants = false` default, but overridable): same as office supplies. Stock tracked. Optional override to add variants if needed.
- **Custom categories**: inherit `has_variants` from the category, with per-item override.

**Items can be deactivated, not deleted**, once they have historical issuances. Inactive items don't show in the supply request form.

## Stock movements (unified)

All changes to `current_stock` go through `stock_movements`:

```
stock_movements
  - id
  - item_variant_id (FK)
  - movement_type: enum (purchase_order_receipt | direct_receipt | count_correction | issuance | manual_issuance | return)
  - quantity (int — positive or negative depending on movement_type)
  - reason (text — required for direct_receipt, count_correction, manual_issuance, return; optional for others)
  - related_purchase_order_id (nullable FK)
  - related_request_id (nullable FK to supply_requests)
  - related_movement_id (nullable FK to stock_movements — links a return to its original outflow)
  - recipient_person_id (nullable FK to people — for manual_issuance/return when recipient known)
  - recipient_type (nullable enum — contractor | staff | external | unspecified)
  - created_by (FK to people)
  - created_at
```

| `movement_type` | Quantity | Used for | Reason required? |
|---|---|---|---|
| `purchase_order_receipt` | + | Items arrive from a PO | No (PO link is the audit) |
| `direct_receipt` | + | Receive without a PO (donation, transfer, found stock) | Yes |
| `count_correction` | +/− | Physical count differs from system | Yes |
| `issuance` | − | Item given via supply_request completion | No (request link is the audit) |
| `manual_issuance` | − | Item left storage outside the supply_request flow (e.g. Admin grabbed shirts informally) — see ADR-0015 | Yes |
| `return` | + | Equipment, uniform, or manual_issuance item returned to stock | Yes (free text describing condition) |

Stock movements are wrapped in DB transactions with row locks on `item_variants` to prevent concurrent over-issuance.

## Manual Stock Out (informal outflows)

Per ADR-0015: a path for items that leave storage outside the supply_request workflow. Common use case: Admin walks into supply room, grabs 3 shirts (often to give to a contractor), tells Front Desk later. No charge. No formal request.

### The action

"Manual Stock Out" button available on Browse Inventory page and item detail. Form:

```
Item / Variant       (required)
Quantity             (required, positive int)
Recipient            (optional — searchable people picker)
Recipient type       (optional — contractor / staff / external / unspecified)
Reason               (required — free text)
Notes                (optional)
```

On submit:

- `stock_movement` row created (`movement_type = manual_issuance`, qty negative)
- `current_stock` decrements
- **No `supply_request` created**
- **No `contractor_charge_schedule` created**
- **No payroll deduction**
- Activity log captures everything including recipient when given

### Contractor recipient case

When `recipient_type = contractor` and `recipient_person_id` is set, the Manual Stock Out also appears on the contractor's QC Minute profile under a section called **"Items Received — No Charge"** — distinct from their Outstanding Charges (which come from supply_requests).

This gives the contractor transparency about what they've received from QCP, separated from things they're paying back.

### When to use Manual Stock Out vs. supply_request

| Situation | Use |
|---|---|
| Charging the contractor | supply_request (charge_amount + split_payments) |
| Pure outflow, no charge | Manual Stock Out |
| Found a count discrepancy | Adjust Stock (count_correction) — different concept |

Mental rule: **if there's a charge, supply_request; if not, Manual Stock Out.**

### Return to Stock (exceptional)

For the rare case where Manual Stock Out items come back, a "Return to Stock" action creates a `return` movement with positive qty. Form:

```
Item / Variant       (required)
Quantity             (required, positive int)
Returned by          (optional — searchable people picker)
Condition / reason   (required — free text)
Original outflow     (optional — link to a prior Manual Stock Out)
Notes                (optional)
```

- Stock increments
- `related_movement_id` links return to original outflow when known
- **No "still out" tracking**: Manual Stock Out items aren't loans; returns are factual events, not balance reconciliation
- If qty returned > qty originally taken (e.g. Admin brings back 4 of 3 taken): system allows it, reason field captures context

For loan-style tracking with expected returns, use the equipment_assignment pattern instead.

### Permissions

`inventory.stock.manual_out` — granted to admin, super_admin, office_manager, front_desk.

Same roles can both do Manual Stock Out and Return to Stock.

## Equipment assignment

When a supply_request with `category=equipment` completes, an assignment record is created:

```
equipment_assignments
  - id
  - item_variant_id (FK)
  - assigned_to_person_id (FK to people)
  - source_request_id (FK to supply_requests)
  - quantity (int — usually 1 for equipment, but supports multi)
  - assigned_at, assigned_by
  - returned_at, returned_by (nullable, set when returned)
  - return_notes (text, nullable)
  - status: enum (assigned | returned | lost | retired)
  - created_at, updated_at
```

When a person is terminated, the termination workflow includes a **Recover Equipment** step:

- Lists all `equipment_assignments` where `assigned_to_person_id = person` and `status = assigned`
- Office manager checks each: **Returned** / **Not Returned**
- Returned items: stock_movement of type `return` created (+1 to stock), assignment status → `returned`
- Not returned: assignment status → `lost`, logged as an expense against QCP

Ad-hoc returns outside termination: "Browse Inventory → Assigned Items" tab → filter by person → "Mark Returned" button on the row. Same effect (creates a stock_movement, updates assignment).

No serial numbers / asset tags in v1. The assignment links a variant to a person; we don't distinguish individual physical units.

## Supply request workflow

Handled by the unified `supply_request` workflow in the workflow engine. Two paths.

### Path 1: Existing item

```
Requester (recruiter, hr, w2_employee, etc.) creates request
  Form (dynamic by category):
    - category (Office Supplies | Uniforms | Equipment | custom)
    - beneficiary_type (self | contractor | general_office)
    - beneficiary_person_id (required if beneficiary_type = contractor)
    - item + variant (dropdown of active items in the category)
    - quantity
    - purpose / reason
    - needed by (date, optional)
    - notes (optional)
    
  If category = Uniforms and beneficiary_type = contractor:
    Additional fields:
      - charge_amount (cents — total to deduct from contractor)
      - split_payments (int — number of pay periods to split over, default 1)
  
  Submit → status: pending

Front Desk sees in "Pending Requests" queue (primary; Office Manager can also fulfill as fallback)
  Reviews
  Clicks "Mark Complete" (or "Cannot Fulfill" with reason)
  
  On Complete:
    - stock_movement created (type=issuance, qty negative)
    - If category=equipment: equipment_assignments row created
    - If category=uniforms + beneficiary=contractor + has charge_amount:
        contractor_charge_schedule + N schedule_entries created
        Each entry will auto-generate a time_entry_adjustment per payroll period
    - Workflow status: completed
    - Notification → requester ("Your request was completed")
```

### Path 2: New item

```
Requester creates "Request New Item"
  Form:
    - item name
    - detailed description
    - estimated cost
    - reason / purpose
    - attachments (optional photos, links)
    - category (Office Supplies | Uniforms | Equipment | custom)
  
  Submit → status: pending_approval

Routing logic:
  - ALL new-item requests → approval queue for admin (and super_admin)
  - Per ADR-0013: no $50 threshold; Office Manager does NOT approve new items

Approver (admin or super_admin):
  Reviews
  Clicks "Approve" or "Deny" (with reason)
  
  On Approve:
    - Request moves to "Approved" state
    - Item appears on the "Ready to Purchase" list on Front Desk's Purchase Orders page
  
  On Deny:
    - Status: denied
    - Workflow closed
    - Notification → requester with denial reason

Front Desk turns approved request into a PO (primary; Office Manager can also do this):
  - Goes to Purchase Orders → Ready to Purchase
  - Clicks "Create PO from this request"
  - PO is created (minimal v1 — no vendor, no expected date)
  - Front Desk goes off-system to actually order
  - When item arrives, Front Desk:
    - Goes to PO → clicks "Mark Received"
    - First-time-only: completes item setup (variants if needed, reorder threshold)
    - Stock_movement created (type=purchase_order_receipt, qty positive)
    - Item is now in inventory
    - Original request can now be fulfilled (Path 1 flow)
```

Front Desk can also create a PO directly without a request (e.g. proactively ordering more pens). Office Manager retains the same capability as fallback. Same minimal PO model.

## Purchase Orders

```
purchase_orders
  - id
  - status: enum (draft | ordered | received | cancelled)
  - created_by, created_at
  - ordered_at (set when status moves to ordered)
  - received_at, received_by (set when status moves to received)
  - notes (text)
  - source_request_id (nullable FK to supply_requests — if created from an approved request)
  - updated_at

purchase_order_items
  - id
  - purchase_order_id (FK)
  - item_variant_id (FK)
  - quantity
  - estimated_unit_cost (cents, optional)
  - notes
  - created_at
```

Minimal v1: no vendor tracking, no expected delivery date, no partial receipts (a PO is fully received or not received; if partial in real life, the office manager can mark received and create a follow-up PO for the rest).

## Reorder thresholds + dashboard alerts

`reorder_threshold` set per variant by office_manager during item setup or via "Edit" action later.

Status colors (computed, not stored):

| State | Condition |
|---|---|
| **In Stock** (green) | `current_stock > reorder_threshold` |
| **Low Stock** (yellow) | `current_stock > 0` AND `current_stock ≤ reorder_threshold` |
| **Out of Stock** (red) | `current_stock = 0` |
| **Not Tracked** (gray) | `reorder_threshold = 0` |

**Office Manager dashboard widget:**

- Counts: X items in Low Stock, Y items Out of Stock
- Click for detailed list, grouped or sorted by category
- No email alerts (per design — dashboard only to avoid notification fatigue)

## Where things appear in the UI

### Sidebar — requester view (recruiter, hr, etc.)

```
📦 Requests
   ├── Create Request          ← the form
   └── My Requests             ← my history
```

### Sidebar — office_manager / admin / super_admin view (full management)

```
📦 Inventory
   ├── Dashboard               ← low-stock alerts, pending requests count
   ├── Browse Items            ← catalog with stock per variant
   ├── Pending Requests        ← incoming fulfillment queue
   ├── Approvals               ← new-item approval queue (admin + super_admin only)
   ├── Purchase Orders         ← PO list + create
   └── Categories              ← manage categories (rename, deactivate, add new)
```

### Sidebar — front_desk view (operational subset)

```
📦 Inventory
   ├── Dashboard               ← low-stock alerts, pending requests count
   ├── Browse Items            ← catalog with stock per variant (no edit)
   ├── Pending Requests        ← incoming fulfillment queue (primary work)
   ├── Purchase Orders         ← place orders after Admin approval; receive stock
   └── (no Categories, no Approvals, no item create/edit)
```

### Dashboard widgets

- **Requester**: "Create Request" button + "My Recent Requests" (5 most recent with status)
- **Front Desk**: "Pending Requests" (their main work) + "POs Awaiting Order" (approved new items) + "Onboarding Docs Pending" (applicants with checklist items awaiting receipt)
- **Office Manager**: "Low Stock Alerts" + "Pending Requests" (oversight)
- **Admin + Super Admin**: "Approvals Needed" + general dashboard rollups

## Permissions

| Action | Roles |
|---|---|
| View items / stock (browse) | admin, super_admin, office_manager, front_desk |
| Create / edit items | admin, super_admin, office_manager |
| Receive stock (direct, no PO) | admin, super_admin, office_manager, front_desk |
| Adjust stock (corrections) | admin, super_admin, office_manager, front_desk |
| Create / manage categories | admin, super_admin, office_manager |
| Submit supply request (any category) | All back-office roles |
| Fulfill supply request | admin, super_admin, office_manager, **front_desk (primary)** |
| Approve new-item request | **admin, super_admin only** (no $50 threshold; all new items require Admin approval per ADR-0013) |
| Create / receive purchase orders | admin, super_admin, office_manager, **front_desk (primary)** |
| Return equipment | admin, super_admin, office_manager, front_desk |

See `10-architecture/permissions-matrix.md` for full detail.

## Adjustment integration (contractor-charged items)

When a `supply_request` is fulfilled with `beneficiary_type = contractor` AND `charge_amount > 0` (regardless of category — covers uniforms, name tags, and any future contractor-charged items), a charge schedule is created. See ADR-0014.

```
contractor_charge_schedules
  - id
  - source_request_id (FK to supply_requests)
  - person_id (the contractor being charged)
  - total_amount (cents)
  - num_payments (int, CHECK BETWEEN 1 AND 4)
  - amount_per_payment (cents — total / num_payments, rounding handled in the last entry)
  - status: enum (active | completed | cancelled | accelerated_to_final_paycheck)
  - created_at, updated_at

contractor_charge_schedule_entries
  - id
  - schedule_id (FK)
  - payroll_period_id (FK — when this deduction is meant to apply)
  - amount (cents)
  - payment_index (int, 1..num_payments)
  - status: enum (scheduled | applied | skipped)
  - applied_adjustment_id (FK to time_entry_adjustments, populated when applied)
  - created_at, updated_at
```

### Split payment bounds and defaults

`num_payments` must be between 1 and 4. The supply request form's UI defaults based on the charge amount:

| Charge amount | Default split |
|---|---|
| ≤ $10 | 1 payment |
| $11–$50 | 2 payments |
| $51–$150 | 3 payments |
| > $150 | 4 payments |

Recruiter can override within the 1-4 range. UI displays the per-period amount live as they change the split.

A name tag at $5 typically uses 1 payment (single deduction next pay period). A uniform set at $30 typically uses 3 payments ($10 each).

### Scheduled deduction job

Scheduled job `ApplyScheduledContractorCharges` runs at the start of each payroll period:

- Finds schedule entries due this period with status=scheduled
- Creates a `time_entry_adjustment` row with `source_type=supply_request`, `source_id=schedule.source_request_id`, `value=entry.amount`, `type=deduction`
- Updates the schedule entry status to `applied` with `applied_adjustment_id` set
- When all entries are applied → schedule status moves to `completed`

See `20-domain/adjustments.md` for the source_type discriminator pattern.

### Outstanding Charges (contractor view)

In QC Minute, the contractor's profile shows an aggregated "Outstanding Charges" view that rolls up all their active `contractor_charge_schedules`:

```
Outstanding Charges: $35
  ── Housekeeping Polo (May 14)        Paid 2 of 3   $10 remaining
  ── Name Tag (May 18)                 Scheduled     $5 next period
  ── Black Pants (May 21)              Paid 1 of 4   $20 remaining

Upcoming pay period deduction: $15
```

Same view available on the back-office side under the contractor's profile for recruiters and HR.

This is a UI rollup — computed on read from the schedule and entries tables; no separate "balance" field stored.

## Termination interaction

Three things happen during termination for a person with active inventory state:

1. **Recover Equipment** (workflow step) — for each `equipment_assignment` row in `assigned` status, Front Desk marks Returned / Not Returned
2. **Active contractor charge schedules** — default behavior: remaining balance across ALL active schedules is consolidated into a single adjustment on the **final paycheck**. Each schedule's status → `accelerated_to_final_paycheck`. See "Determining the final paycheck" below.
3. **Returned items** (if applicable): if the contractor returns the item(s), Front Desk marks the relevant schedule as cleared (status → `cancelled`); no further deductions on that schedule

If neither return nor accelerated-to-final-paycheck applies (e.g. contractor disappeared with items), each affected schedule moves to `cancelled` and the unpaid balance is logged as an expense against QCP.

### Determining the final paycheck

When termination is initiated, the system identifies which **payroll_period** will contain the contractor's final earnings — that period's paycheck is the "final paycheck."

**Two sub-cases:**

**1. Terminated mid-week** (e.g. termination effective Wednesday, current week's timesheet still open)
- Final period = the current open `payroll_period`
- The consolidated balance adjustment is added to that period's timesheet
- When that timesheet is approved and the invoice/payroll is generated, the deduction takes effect

**2. Terminated after a period closes** (e.g. last work day was Sunday, period closed; termination initiated Tuesday)
- Final period = the period that just closed
- If that period's timesheet is still in `pending_approval` or `declined` → the balance adjustment is added before resubmission
- If already `approved` or `invoiced` → super_admin briefly reopens the period to add the adjustment, OR (if void/reissue is preferable) the invoice for that period is voided and reissued with the consolidated charge added
- Operational rule: HR coordinates with payroll on the path; the system supports both

### Cap-at-zero rule

If the contractor's final paycheck cannot cover the consolidated balance (e.g. they earned $20 that final week but owe $40 on uniforms):

- **Deduction is capped at the available pay** — the contractor's check goes to zero, not negative
- **Remainder is logged as an expense against QCP** — same pattern as the "uniform not returned" path
- Each contractor_charge_schedule that contributed gets its `status = accelerated_to_final_paycheck` with the portion that was applied recorded
- Unrecovered portion is captured in the expense log with `reason = "Contractor charge balance not fully covered on final paycheck"`

The system does NOT pursue collection or generate a negative-balance debt — that's deliberate. Bad debt is the operational cost of accepting low-pay or no-pay terminations.

### Pseudo-code for the final-check job

When termination workflow completes the "Recover Equipment" step and Payroll signs off:

```
For each active contractor_charge_schedule for this person:
  Sum remaining entries with status='scheduled'
  → remaining_balance = sum

If remaining_balance > 0:
  Identify final_payroll_period (mid-week vs closed logic above)
  Compute contractor's available pay for that period (gross earnings - other deductions)
  
  consolidated_deduction = MIN(remaining_balance, available_pay)
  uncovered_remainder = MAX(0, remaining_balance - available_pay)
  
  Create a time_entry_adjustment:
    person_id = contractor
    payroll_period_id = final_payroll_period
    bucket = deduction
    value = consolidated_deduction
    source_type = supply_request_termination_consolidation
    notes = "Termination — consolidated balance from N schedules"
  
  For each contractor_charge_schedule contributing:
    Mark status = accelerated_to_final_paycheck
    Record applied_amount and unapplied_amount on the schedule
  
  If uncovered_remainder > 0:
    Log as expense against QCP
    Activity log entry: "Contractor charge balance not fully covered on final paycheck"

If remaining_balance == 0:
  All schedules complete naturally; no action needed
```

### What "final paycheck" depends on (operational dependencies)

- The termination workflow must complete BEFORE the final period's payroll runs
- HR is responsible for initiating termination promptly; otherwise the contractor receives one paycheck before consolidation kicks in
- If timing slips (termination initiated after final period invoiced), the void+reissue path handles it (per ADR-0006)

## Future scope (not v1)

- Vendor tracking on POs
- Expected delivery dates
- Partial receipts (3 of 5 items)
- Reorder triggers (auto-create draft PO when threshold hit)
- Serial number / asset tag tracking for equipment
- Office supply request from anyone in the office (broader access)
- Item images / catalog photos
- Bulk operations (request 5 items in one form, receive multiple items at once)

These are in `90-open/parking-lot.md` for future consideration.

## Related

- ADR-0012 — Unified inventory with three categories (full decision)
- `20-domain/workflows.md` — supply_request workflow engine
- `20-domain/adjustments.md` — source_type discriminator (uniforms generate adjustments here)
- `20-domain/people-lifecycle.md` — termination interactions
- `40-flows/supply-request.md` — full step-by-step flow
- `10-architecture/permissions-matrix.md` — updated permissions
- `30-schema/inventory-tables.md` (TBD with phase work)
