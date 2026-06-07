# ADR-0015: Manual Stock Out as Alternative Outflow Path

| Field | Value |
|---|---|
| Status | Accepted |
| Decided | 2026-05-21 |
| Owner | Product (David) + Engineering |
| Supersedes | — (extends ADR-0012's stock_movement types) |
| Superseded by | — |

## Context

ADR-0012 modeled all stock outflows through `supply_request` fulfillment — every item leaving storage required a supply_request → fulfillment → stock_movement of type `issuance`.

Real-world operations have a common path that doesn't fit this model:

> Admin (usually the owner) walks into the supply room, takes 3 shirts (often to give to a contractor), tells Front Desk later. No charge to anyone. No formal request was ever submitted.

The supply_request workflow is overhead for this case. Manual Stock Out is the lighter path that just records the outflow.

## Decision

**Add a new stock_movement type: `manual_issuance`.** Accessible via a "Manual Stock Out" action on the Browse Inventory page and item detail views.

### The action

Form fields:

```
Item / Variant       (required)
Quantity             (required, positive int)
Recipient            (optional — searchable people picker)
Recipient type       (optional — contractor / staff / external / unspecified)
Reason               (required — free text describing purpose)
Notes                (optional)
```

On submit:

1. `stock_movement` row created (`movement_type = manual_issuance`, qty negative)
2. `current_stock` decrements
3. Activity log entry with all fields including recipient (if given)
4. **No `supply_request` created**
5. **No `contractor_charge_schedule` created**
6. **No payroll deduction**

It's a pure inventory event. No money flows.

### Recipient logging

Recipient is **optional but encouraged**. When recipient_type = `contractor` and recipient_person_id is set, the Manual Stock Out also appears on the contractor's profile under a separate section called "Items Received — No Charge", distinct from their Outstanding Charges (which are supply_request-driven).

### When to use Manual Stock Out vs supply_request

| Situation | Use |
|---|---|
| Recruiter requests pens for self | supply_request (no charge, beneficiary = self) |
| Recruiter requests uniform for Maria, $30 charge | supply_request (deduction schedule created) |
| Admin grabs 3 shirts informally, no charge | **Manual Stock Out** |
| Admin gives shirts to a contractor as a courtesy, no charge | **Manual Stock Out** with recipient = the contractor |
| Office Manager grabs supplies for a meeting prep | **Manual Stock Out** |
| Items went missing in storage | `Adjust Stock` (count correction — different concept) |

The mental rule: **If you're charging someone, use supply_request. If you're just tracking the outflow, use Manual Stock Out.**

### Return path

A "Return to Stock" action handles the rare case where items come back from a Manual Stock Out:

```
Item / Variant       (required)
Quantity             (required, positive int)
Returned by          (optional — searchable people picker)
Condition / reason   (required — free text)
Original outflow     (optional — link to a prior Manual Stock Out movement)
Notes                (optional)
```

On submit:

1. `stock_movement` row created (type = `return`, qty positive)
2. `current_stock` increments
3. If "Original outflow" was linked: stock_movement carries `related_movement_id` for traceability
4. Activity log entry

### No "still out" tracking

Manual Stock Out items typically aren't expected back. The system does NOT compute or display a "still out" balance for Manual Stock Out records. Returns, when they happen, appear as factual history events linked to the original outflow (when known), but no "outstanding" implication.

If true loan/checkout tracking is needed for a specific case (e.g. expensive equipment), use the equipment_assignment pattern instead — that's designed for assigned-and-expected-back items.

### Returns above the outflow quantity

If a Return to Stock has qty greater than what was originally taken (e.g. taken 3, returned 4), the system **allows it**. The Reason field captures context ("found extras we didn't know were missing"). It's an inventory event; the accounting framing doesn't apply.

### Permissions

Manual Stock Out and Return to Stock: `front_desk`, `office_manager`, `admin`, `super_admin`.

The same roles that can fulfill supply requests can do these too.

## Consequences

### Positive

- Operational reality is supported — Admin doesn't have to submit a request to themselves
- Audit trail preserved (who took what, when, why, recipient if known)
- No fake supply_request records polluting the workflow history
- Contractor-recipient case still shows on the contractor's profile (transparency)
- Returns are supported but not over-engineered

### Negative

- One more stock_movement type (5 → 6) to understand
- Risk of "wrong path chosen" — someone uses Manual Stock Out when they should have charged via supply_request. Mitigated by training and the clear mental rule.

### Implementation requirements

Schema additions:

- `stock_movements.movement_type` enum gains `manual_issuance`
- `stock_movements.recipient_person_id` (nullable FK to people)
- `stock_movements.recipient_type` (nullable enum: contractor / staff / external / unspecified)
- `stock_movements.related_movement_id` (nullable FK to stock_movements — for return linkage)

UI:

- "Manual Stock Out" button on Browse Inventory page and item detail
- "Return to Stock" button on item detail and on a Manual Stock Out detail record
- Contractor profile: new section "Items Received — No Charge" listing manual_issuance movements where recipient = this contractor

Permissions:

- New `inventory.stock.manual_out` permission
- Same role grants as `inventory.stock.receive_direct`

## Alternatives considered

### A. Use `count_correction` with negative quantity

Rejected. "Adjust Stock" implies a discrepancy was found, not an intentional outflow. Mixes corrections with intentional movements in reports.

### B. Force everything through supply_request (Admin self-submits and immediately approves)

Rejected. Bureaucratic. Creates artifacts that don't reflect reality ("Admin requested shirts from themselves").

### C. Treat Manual Stock Out as a "loan" with expected return tracking

Rejected. Most Manual Stock Out items aren't expected back. Treating them as loans creates false expectations. Items that ARE expected back belong in the equipment_assignment pattern.

### D. Reuse `issuance` movement type with a flag

Rejected. Loses the distinction between "this went through a supply request workflow" vs "this just left storage." Reports that try to reconcile supply_request fulfillments with stock movements would fail.

## Related

- ADR-0012 — Unified inventory with three categories (defines the original supply_request flow)
- ADR-0014 — Contractor charge schedule (the charging mechanism, not used here)
- `20-domain/inventory.md` — full domain model with Manual Stock Out section
- `40-flows/supply-request.md` — distinct from this flow
- `10-architecture/permissions-matrix.md` — `inventory.stock.manual_out` permission
