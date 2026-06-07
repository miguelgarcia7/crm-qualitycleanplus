# ADR-0012: Unified Inventory with Three Categories

| Field | Value |
|---|---|
| Status | Accepted |
| Decided | 2026-05-21 |
| Owner | Product (David) + Engineering |
| Supersedes | — |
| Superseded by | — |

## Context

Earlier docs (and ADR-0011) scoped the inventory module around **uniforms only**, with a specific contractor-paying-via-deduction flow and a `uniform_request` workflow.

Operational reality is broader. QCP needs to track:

- **Office supplies** (pens, notebooks, paper, label printer, etc.) consumed by staff
- **Uniforms** (polos, pants, jackets) issued to contractors with payroll deduction
- **Equipment** (laptops, monitors) assigned to staff with return-when-leaving expectation

A single unified inventory system, with category-driven behavior, would replace the original uniform-only design.

## Decision

**One unified inventory system with three default categories: Office Supplies, Uniforms, Equipment.**

- Categories are user-creatable (super_admin + office_manager). Categories can be renamed. Categories cannot be deleted once items are assigned to them.
- All items, regardless of category, live in the same tables (`items` + `item_variants`).
- The form to create or edit an item is **dynamic by category** — uniforms gather size + color; everything else just gathers a name and basic details.
- All issuances and receipts use a unified `stock_movements` table with type discriminators.
- One workflow handles all request types: `supply_request`. The category and beneficiary fields drive conditional behavior on completion.

### Naming change

The legacy `uniform_request` workflow is renamed `supply_request`. The workflow definition now handles:

- **Office Supplies for self** (recruiter wants pens) — no deduction
- **Office Supplies for general office** (printer paper) — no deduction
- **Uniforms for a contractor** (Maria needs a polo) — triggers deduction schedule
- **Equipment for self** (recruiter needs a laptop) — creates assignment record, no deduction

### Two request paths

The screenshot from Email 4 (the inventory expansion) introduces a second path:

1. **Existing item path** — submit request → office manager fulfills from stock → inventory decrements → done
2. **New item path** — submit "Request New Item" with description, estimated cost, justification → office manager approves (or super_admin if ≥ $50) → Purchase Order created → item ordered → received → added to inventory

### Adjustment relationship (important)

Uniform deductions are NOT manually-added adjustments. The `adjustment_items` catalog (templates the recruiter picks from when adding ad-hoc adjustments like "Pickup Fee Gas") contains **no uniform entries**.

Uniform deductions enter the system exclusively through a completed `supply_request` workflow with `category=uniforms` and `beneficiary_type=contractor`. The resulting `time_entry_adjustments` rows carry `source_type=supply_request` and `source_id=the request's ID`, with `adjustment_item_id=null`.

See `20-domain/adjustments.md` for the source_type discriminator pattern.

## Consequences

### Positive

- One inventory system, one set of tables, one rendering path for the contractor "Deduction Ledger"
- New future categories (e.g. "Tools," "Cleaning Chemicals") plug in cleanly
- The supply_request workflow is one definition, with conditional logic, not five copies
- Office managers learn one fulfillment screen, not three
- Reporting across categories is uniform
- The deduction-vs-adjustment confusion is solved at the catalog level (uniforms aren't in the manual adjustment dropdown)

### Negative

- More fields per item to support variant/no-variant divergence (handled by category-aware UI)
- The supply_request workflow becomes the largest single workflow in the engine
- Office managers' "Pending Requests" queue may grow long during busy weeks (mitigated by filtering and bulk actions)

### Implementation requirements

Tables (rough):

- `categories` (Office Supplies, Uniforms, Equipment seeded; user-creatable; rename-able; cannot delete when items exist)
- `items` (name, category_id, has_variants, active, reorder threshold defaults — but threshold is per-variant)
- `item_variants` (item_id, size, color, sku optional, current_stock, reorder_threshold)
- `stock_movements` (item_variant_id, movement_type, quantity, reason, related_purchase_order_id?, related_request_id?, created_by)
- `supply_requests` (the workflow instance; carries category, beneficiary, beneficiary_person_id, item_variant_id, quantity, charge details, status)
- `equipment_assignments` (item_variant_id, assigned_to_person_id, assigned_at, returned_at)
- `purchase_orders` + `purchase_order_items` (minimal v1: no vendor, no expected date, no receipt detail tracking — just a list of items to order)
- `contractor_charge_schedules` + `contractor_charge_schedule_entries` (renamed and broadened in ADR-0014)

Workflow:

- `supply_request` workflow definition with two paths (existing item / new item)
- On completion of supply_request with category=uniforms: create deduction schedule
- On completion of supply_request with category=equipment: create equipment_assignment row
- On completion of supply_request with category=office_supplies: nothing beyond stock decrement

Permissions: see updated `10-architecture/permissions-matrix.md`.

## Alternatives considered

### A. Two separate workflows: `supply_request` + `uniform_issuance`

Rejected. The downstream behavior (decrement inventory, mark completed, audit) is identical. Differences (deduction schedule) are conditional on category, not a different process. One workflow with conditional logic is cleaner than two parallel workflows.

### B. Two separate inventory systems (office supplies separate from uniforms)

Rejected. Same data structure, same stock movement logic, same browse UI. Splitting creates duplicated code and confused mental models.

### C. Keep uniforms as their own deduction table, separate from `time_entry_adjustments`

Rejected. Discussed in K of the design conversation. Splitting forces every contractor-ledger and timesheet-totaling query to UNION two tables. Same-table with `source_type` discriminator gives the same conceptual cleanliness at the catalog level without paying that cost.

### D. Auto-generate POs when items hit reorder threshold

Rejected for v1. Operationally, the office manager wants visibility before commitment. Low stock surfaces as a dashboard alert; PO creation is a deliberate click.

## Related

- `20-domain/inventory.md` — full domain model
- `20-domain/workflows.md` — supply_request workflow definition
- `20-domain/adjustments.md` — source_type discriminator
- `40-flows/supply-request.md` — full step-by-step flow
- `10-architecture/permissions-matrix.md` — updated permissions
- ADR-0011 — Earlier (now superseded for inventory scope) more-staff request decision; uniforms-only context replaced by this ADR
