# Flow: Supply Request

| Field | Value |
|---|---|
| Status | Accepted |
| Last updated | 2026-05-21 |
| Owner | Product + Engineering |

End-to-end flow for **any item request** — office supplies, uniforms, equipment. Replaces the legacy uniform-request flow with a category-aware unified workflow.

Two paths: **existing item** (most common) and **new item** (item doesn't exist in inventory yet).

Related: ADR-0012, `20-domain/inventory.md`, `20-domain/workflows.md`.

## Actors

| Actor | Role |
|---|---|
| Requester | Anyone with `inventory.requests.submit` — recruiter, hr, w2_employee, payroll, office_manager themselves, super_admin |
| Front Desk | Fulfills existing-item requests (primary); places POs after Admin approval; receives stock; marks completed |
| Office Manager | Fallback for fulfillment; manages catalog, categories, items, stock corrections; oversight |
| Admin | Approves all new-item requests (no $50 threshold per ADR-0013) |
| Super Admin | All-access; can approve new-item requests; backstop everywhere |
| System | Auto-creates assignments (equipment), deduction schedules (uniforms), stock movements |

## Pre-conditions

- Requester has appropriate role and `inventory.requests.submit` permission
- The item (for existing-item path) exists and is active in inventory

## Path 1: Existing Item

```
Requester (sidebar → Requests → Create Request)
  │
  ▼
Form (Step 1 in the screenshot):
  - Category card selector: Office Supplies / Uniforms / Equipment / [custom]
  ▼
  Form (Step 1A in the screenshot — dynamic by category):
    - Beneficiary type (self / contractor / general office)
    - Beneficiary person (if contractor — searchable dropdown)
    - Item dropdown (active items in this category)
    - Variant dropdown (if has_variants — size + color)
    - Quantity
    - Purpose / Reason
    - Needed By (date, optional)
    - Notes (optional)
    
    If category=Uniforms AND beneficiary_type=contractor:
      Additional:
        - Total charge amount (cents)
        - Split into payments (1, 2, 3, etc.)
    
  Submit
  │
  ▼
Server:
  supply_requests.status = pending
  Notification → office manager: "New supply request from {requester}"
  ▼
Requester sees confirmation: "Request submitted (REQ-####)"
```

### Front Desk fulfills (primary)

```
Front Desk (sidebar → Inventory → Pending Requests)
  │
  ▼
Sees queue:
  | Request ID | Item | Requested By | Beneficiary | Qty | Status |
  
  Clicks a row → detail view
  │
  ▼
Detail view shows:
  - Full request info
  - Current stock for the variant ("3-Ring Binder (1 in.): 18 in stock, threshold 5")
  - If category=uniforms: deduction schedule preview
  
  Two action options:
  
  Option A — Mark as Completed:
    Server:
      stock_movement created (type=issuance, qty=−request.quantity)
      Current stock decrements
      Activity log entry
      
      If category=equipment:
        equipment_assignment row created
          (assigned_to = beneficiary_person_id ?? requester_id, status=assigned)
      
      If category=uniforms AND beneficiary_type=contractor AND charge_amount > 0:
        contractor_charge_schedule created
        N schedule_entries created (one per upcoming payroll period; N is 1-4 per ADR-0014)
        First entry will auto-apply at next payroll period start
      
      supply_request.status = completed
      Notification → requester: "Your request has been completed"
  
  Option B — Cannot Fulfill (e.g. not enough stock):
    Modal asks for reason
    Server:
      supply_request.status = cannot_fulfill
      Notification → requester with reason
```

Office Manager has identical capabilities and can step in as fallback when Front Desk is unavailable.

## Path 2: New Item

```
Requester (sidebar → Requests → Create Request)
  │
  ▼
Selects category
  Sees "Item not in inventory? Request a new item"
  │
  ▼
Form (Step 1B in the screenshot):
  - Item name / description
  - Detailed description
  - Estimated cost (cents)
  - Reason / purpose
  - Attachments (optional — photos, vendor links)
  - Category (pre-selected from prior step)
  
  Yellow info box: "This item is not in our inventory. This request will be sent to
  the Admin for approval."
  
  Submit
  │
  ▼
Server:
  supply_requests.status = pending_approval
  
  Routing (per ADR-0013 — no $50 threshold):
    All new-item requests route to admin (and super_admin since they have all permissions).
    Notification → admin + super_admin: "Approval needed for new item request"
    Office Manager does NOT receive these (cannot approve new items).
  ▼
Requester sees confirmation: "Approval request submitted"
```

### Approver reviews

```
Admin / Super Admin (sidebar → Inventory → Approvals)
  │
  ▼
Sees approval queue:
  | Request ID | Item | Requested By | Est. Cost | Status |
  
  Click row → detail view
  │
  ▼
Detail shows full request + attachments
  
  Two actions:
  
  Option A — Approve:
    Server:
      supply_requests.status = approved
      Activity log
      Notification → requester: "Your new-item request was approved"
      Item appears on office manager's "Ready to Purchase" list
  
  Option B — Deny:
    Modal asks for reason
    Server:
      supply_requests.status = denied
      Workflow closed (terminal state)
      Notification → requester with denial reason
```

### Front Desk creates PO (primary; Office Manager fallback)

```
Front Desk (sidebar → Inventory → Purchase Orders → "Ready to Purchase" tab)
  │
  ▼
Sees approved requests not yet POed:
  | Request ID | Item | Est. Cost | Approved By | Approved At |
  
  Two options:
  
  Option 1 — "Create PO from this request":
    Form:
      - PO items (pre-populated from the request; can edit quantity)
      - Optional notes
    Submit
    Server:
      purchase_orders row created (status=draft, source_request_id set)
      purchase_order_items rows created
  
  Option 2 — "Add to existing PO":
    Selects an existing draft PO
    The request's items are added to it
    
After PO is created, Front Desk goes off-system to actually order (call vendor,
email, walk to Office Depot — whatever). Returns to mark the PO as ordered.

  Front Desk → PO detail → "Mark Ordered"
    purchase_orders.status = ordered, ordered_at = now
```

### Creating a PO directly (no request)

```
Front Desk (sidebar → Inventory → Purchase Orders → "Create PO")
  │
  ▼
Form:
  - Add items to PO (existing items from inventory, with quantity)
  - Notes
  
  Submit → PO created in draft state
  Front Desk can edit items, then click "Mark Ordered" to commit
```

Office Manager has the same capability as fallback.

### Receiving the PO

```
Items physically arrive at the office
  │
  ▼
Front Desk (sidebar → Inventory → Purchase Orders → [PO Detail])
  Clicks "Mark Received"
  │
  ▼
Confirmation: "Confirm all items received? This adds them to inventory."
  
  Server:
    For each purchase_order_item:
      stock_movement created (type=purchase_order_receipt, qty positive)
      item_variant.current_stock += qty
    purchase_orders.status = received
    purchase_orders.received_at, received_by populated
    
  If this PO was source for a supply_request:
    Notification → original requester: "Item is now available! Your request can be fulfilled."
    Front Desk goes to the request (now in approved state) and runs the existing-item flow
    (Path 1 fulfillment) to actually issue it
```

## Stock-only operations (no request involved)

### Receive Stock (direct, no PO)

```
Front Desk (Browse Inventory → item → "Receive Stock") — Office Manager can also do this
  Form:
    - Variant (if has_variants)
    - Quantity
    - Reason (required free text — donation, transfer, etc.)
  Submit
  
  Server:
    stock_movement created (type=direct_receipt, qty positive, reason captured)
    current_stock += qty
    Activity log
```

### Adjust Stock (corrections)

```
Front Desk (Browse Inventory → item → "Adjust Stock") — Office Manager can also do this
  Form:
    - Variant
    - Adjustment (+ or − amount; e.g. -2)
    - Reason (required — "Physical count revealed shortage", "Items damaged in storage", etc.)
  Submit
  
  Server:
    stock_movement created (type=count_correction, qty positive or negative, reason captured)
    current_stock adjusted
    Activity log
```

## Equipment-specific paths

### Equipment assignment on completion

Already covered in Path 1 — when an equipment supply_request is completed, an `equipment_assignment` row is auto-created linking the variant to the beneficiary person (or requester if beneficiary=self).

### Ad-hoc equipment return

```
Front Desk (Browse Inventory → "Assigned Items" tab) — Office Manager can also do this
  │
  ▼
Filter by person OR by item
  Each row: variant, assigned_to, assigned_at, [Mark Returned] button
  
  Click "Mark Returned":
    Modal asks for return notes (optional — "Good condition", "Damaged screen", etc.)
    
    Server:
      stock_movement created (type=return, qty positive, notes captured)
      current_stock += qty
      equipment_assignment.status = returned
      equipment_assignment.returned_at, returned_by populated
      Activity log
```

### Equipment recovery on termination

```
HR or Recruiter initiates termination workflow
  │
  ▼
Termination workflow step: "Recover Equipment"
  
  Step auto-populates with all assignments where:
    assigned_to_person_id = person being terminated
    status = assigned
  
  Front Desk goes through each:
    For each row:
      Choose: Returned / Not Returned
      Optional: notes
      
    On Returned:
      stock_movement (type=return), current_stock += qty
      equipment_assignment.status = returned
    
    On Not Returned:
      equipment_assignment.status = lost
      Expense logged against QCP (per policy)
  
  Front Desk marks step complete
  Termination workflow continues
```

## Notifications summary

| Event | Recipient(s) | Channels |
|---|---|---|
| Supply request submitted (existing item) | Front Desk (primary), Office Manager (oversight) | Mail + in-app |
| Supply request submitted (new item, any cost) | Admin + Super Admin | Mail + in-app |
| Request fulfilled | Requester | In-app |
| Request denied | Requester (with reason) | Mail + in-app |
| Request cannot be fulfilled | Requester (with reason) | Mail + in-app |
| PO received (had source request) | Original requester | In-app |
| Low stock threshold hit | (none — dashboard widget only) | — |

## Audit trail

Each row in `activity_log` for a complete supply_request lifecycle:

```
[09:14] Submitted          - Recruiter Jane → Request REQ-1052 (3-Ring Binder, qty 2)
[09:42] Marked as Complete - OM David → Request REQ-1052 (stock: 18 → 16)
```

For new-item with approval:

```
[10:21] Submitted          - Recruiter Jane → Request REQ-1056 (Label Printer, est. $120)
[10:24] Sent for Approval  - System → Approver queue (≥$50)
[14:50] Approved           - OM David → Request REQ-1056
[15:01] PO Created         - OM David → PO-1008 (from REQ-1056)
[15:15] Marked Ordered     - OM David → PO-1008
[2 days later]
[09:30] Marked Received    - OM David → PO-1008 (stock: 0 → 1)
[09:31] Request Fulfilled  - OM David → REQ-1056 (stock: 1 → 0; Label Printer issued to Jane)
```

## Edge cases

| Case | Handling |
|---|---|
| Requester submits but item goes out of stock before fulfillment | OM marks as "Cannot Fulfill", requester notified; can resubmit |
| Multiple approvers both view a $50+ request | Both see it in queue; first to approve/deny wins; the other sees it disappear |
| Approver wants to modify the request (different quantity, etc.) | Not supported v1. Approver denies, requester resubmits with corrected details |
| Variant out of stock but another variant in stock | The form requires specific variant; if needed variant not in stock, OM can't fulfill |
| PO received but only some items arrived | Mark received anyway; create a follow-up PO for the rest (no partial receipts in v1) |
| Equipment lost between assignment and termination | Mark as lost via the ad-hoc return path with a "lost" reason; assignment status updated |

## Permissions

| Action | Roles |
|---|---|
| Submit a request | All authenticated back-office roles |
| Approve new-item request | **admin, super_admin only** (no $50 threshold per ADR-0013) |
| Fulfill (mark complete) | admin, super_admin, office_manager, **front_desk (primary)** |
| Deny request | admin, super_admin, office_manager |
| Create PO | admin, super_admin, office_manager, **front_desk (primary)** |
| Receive PO | admin, super_admin, office_manager, **front_desk (primary)** |
| Adjust stock | admin, super_admin, office_manager, front_desk |
| Return equipment | admin, super_admin, office_manager, front_desk |
| Manual Stock Out (per ADR-0015) | admin, super_admin, office_manager, front_desk |
| Return to Stock | admin, super_admin, office_manager, front_desk |

> **Note: Manual Stock Out is a separate flow** from supply_request. For items leaving storage without a charge or formal request (e.g. Admin grabbing shirts informally), see ADR-0015 and `20-domain/inventory.md`. Not covered in this document.

See `10-architecture/permissions-matrix.md` for the full matrix.

## Related

- ADR-0012 — Unified inventory with three categories
- `20-domain/inventory.md` — full domain model
- `20-domain/workflows.md` — supply_request workflow definition
- `20-domain/adjustments.md` — source_type discriminator (uniforms generate adjustments here)
- `40-flows/termination.md` — recovers equipment as a step (TBD)
- `10-architecture/permissions-matrix.md` — full permissions
