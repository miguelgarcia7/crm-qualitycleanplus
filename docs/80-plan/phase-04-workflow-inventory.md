# Phase 04a — Workflow engine + Unified Inventory + Supply Request + Contractor-Charge Money Chain

| Field | Value |
|---|---|
| Status | ✅ Done (04a slice; 68 Pest tests green, Pint + Larastan clean, build + types clean). Phase 04b deferrals listed below. |
| Last updated | 2026-06-07 |
| Owner | Engineering |

## As built (current state)

- **Workflow engine (`app/Domain/Workflows`, ADR-0026):** `workflows` + `workflow_steps`
  tables; concrete `WorkflowDefinition` classes resolved via a `WorkflowRegistry`
  (bound in `AppServiceProvider`); engine Actions `StartWorkflow` / `AdvanceWorkflow`
  (auto-runs `system` steps) / `CompleteStep` / `RejectStep` / `CancelWorkflow`.
  Shared **My Tasks** inbox (`WorkflowTaskController`, `/admin/tasks`) querying
  `WorkflowStep::openForPerson()` (by person or role), with complete/reject;
  `WorkflowPolicy`; dashboard "pending tasks" widget.
- **Adjustments (`app/Domain/Adjustments`):** `adjustment_items` + `time_entry_adjustments`
  (deductions forced non-billable on the model); `CreateManualAdjustment` /
  `DeleteAdjustment`; manual add/remove on the timesheet grid; `GenerateInvoice`
  folds **billable** adjustments into `adjustment_total` (deductions stay payroll-only).
- **Unified inventory (`app/Domain/Inventory`, ADR-0012):** categories / items /
  item_variants / stock_movements / purchase_orders / purchase_order_items /
  equipment_assignments. Single transactional `RecordStockMovement` chokepoint
  (row-locked, no negative stock); `CreateItem`, `ReceiveStock`, `CreatePurchaseOrder`,
  `ReceivePurchaseOrder`, `ManualStockOut` (ADR-0015), `ReturnToStock`, `ReturnEquipment`.
  Browse Items + Purchase Orders + Equipment UI; low/out-of-stock dashboard widget;
  `InventorySeeder` (3 categories) + sample uniform/equipment in `SampleDataSeeder`.
- **Supply request + charges (ADR-0012/0014):** `supply_requests`,
  `contractor_charge_schedules`, `contractor_charge_schedule_entries`.
  `SupplyRequestDefinition` (existing-item → Front Desk fulfill; new-item → Admin
  approve → fulfill). `FulfillSupplyRequest` issues stock, creates an equipment
  assignment (equipment) or a uniform charge schedule split across the next N open
  payroll periods. `ApplyScheduledContractorCharges` job (scheduled daily) turns
  each scheduled entry on an open period into a **non-billable deduction**;
  `Person::outstandingChargeBalance()` rollup. Requester + Front-Desk + Admin UI.
- **Verified end-to-end:** fulfilling a $40/2-split uniform request → 2 schedule
  entries → job emits two non-billable deductions; equipment fulfillment creates an
  assignment; billable incentive folds into the invoice while the deduction does not;
  manual stock-out creates no charge.

Just-in-time plan for the **first slice** of Phase 04 (`80-plan/roadmap.md`). Phase
04 is the roadmap's largest phase — a generalized workflow engine, a unified
inventory subsystem, the contractor-charge → adjustment → payroll chain, and eight
concrete workflows. Per the owner it ships in two slices; **04a is the engine
foundation + the inventory & money chain** (the operationally critical,
self-contained half), proving the engine end-to-end on the **supply-request**
workflow.

## Scope decisions (locked with owner)

- **Engine = hybrid** (ADR-0026): shared `workflows`/`workflow_steps` tables + a
  shared *My Tasks* inbox, but each workflow is a concrete PHP `WorkflowDefinition`
  class in a code registry — **no** DB-stored JSON interpreter.
- **First slice = inventory + money chain.** The people/WO workflows move to 04b.
- **PTO → Phase 08** (full tier-based accrual system is scoped there).

## Acceptance (this slice)

Office manager sets up inventory (3 categories seeded; creates items/variants,
receives stock). A requester submits a supply request → Front Desk fulfills it →
stock decrements; equipment items create an `equipment_assignment`; a uniform with
a contractor charge creates a `contractor_charge_schedule` + N entries. At each
upcoming payroll period `ApplyScheduledContractorCharges` creates a **non-billable**
`time_entry_adjustment` (payroll-only — invoices unaffected). New-item path:
requester → Admin approval → PO → receive → item in stock. Manual stock-out +
return-to-stock work outside the request flow. The *My Tasks* inbox shows each role
its pending workflow steps.

## Anchored ADRs

ADR-0026 (hybrid workflow engine) · ADR-0012 (unified inventory, 3 categories) ·
ADR-0014 (contractor charge schedule) · ADR-0015 (manual stock out) · ADR-0005/0006
(rate/invoice snapshots — adjustments respect freeze) · ADR-0025 (`app/Domain`).

## New contexts (ADR-0025)

`app/Domain/Workflows`, `app/Domain/Inventory`, `app/Domain/Adjustments`.

## Increments

0. **Doc + ADR-0026** — this doc; the hybrid-engine ADR. No new deps (Reverb,
   dompdf, notifications already present).
1. **Workflow engine** (`app/Domain/Workflows`) — `workflows` + `workflow_steps`
   tables; models/enums; abstract `WorkflowDefinition` + registry; engine Actions
   (Start / CompleteStep / RejectStep / Advance / Cancel); `WorkflowTaskController`
   *My Tasks* inbox; `WorkflowPolicy`; dashboard widget.
2. **Adjustments** (`app/Domain/Adjustments`) — `adjustment_items` +
   `time_entry_adjustments` (deductions forced non-billable); manual-adjustment
   Actions; `GenerateInvoice` folds **billable** adjustments into `adjustment_total`;
   `netPay` rollup helper; controller + UI.
3. **Unified inventory** (`app/Domain/Inventory`) — categories / items /
   item_variants / stock_movements / purchase_orders / purchase_order_items /
   equipment_assignments; single transactional `RecordStockMovement` chokepoint;
   Create/Receive/PO/ManualStockOut/Return/ReturnEquipment Actions; `InventorySeeder`
   (3 categories); Browse UI + low-stock dashboard widget; `InventoryPolicy`.
4. **Supply request + charges** — `supply_requests` +
   `contractor_charge_schedules` + entries; `SupplyRequestDefinition` (existing-item
   + new-item paths); `FulfillSupplyRequest` (issuance + equipment assignment +
   uniform charge schedule); `ApplyScheduledContractorCharges` job; Outstanding
   Charges rollup; requester + front-desk + admin UI; `SupplyRequestPolicy`.
5. **Tests + docs** — Pest across engine / inventory / supply-charge; pint / stan /
   test / build / types green; update this doc, roadmap, `app/Domain/README.md`.

## v1 simplifications

- Workflow definitions are code, not data (ADR-0026). Multi-step approvals + SLA
  auto-escalation modelled but unused.
- Inventory: no vendor tracking, expected-delivery dates, partial receipts, reorder
  auto-trigger, serials/asset tags, item images, or bulk requests.
- Uniform deductions are **payroll-only** (non-billable) — they never touch
  invoices. Billable incentives fold into `invoices.adjustment_total`.
- Charge schedules spread across the next N open/future payroll periods; default
  split by amount (≤$10→1, ≤$50→2, ≤$150→3, else→4), overridable on the request.

## Out of scope (Phase 04b / later)

- **Workflows:** termination (+ equipment recovery, final-paycheck consolidation,
  scheduled jobs, `termination_records`), transfer, temporary assignment,
  pay-increase, more-staff request, change-personal-info, recruiter-transfer.
- **Inventory advanced:** vendors, delivery dates, partial receipts, reorder
  auto-trigger, serials, images, bulk request.
- **PTO** → Phase 08.

## Verification

`migrate:fresh --seed` (3 categories + sample items) → create uniform item +
variants → receive stock → submit supply request (uniform, contractor beneficiary,
charge split) → appears in Front Desk *My Tasks* → fulfill (stock down + schedule +
entries) → `ApplyScheduledContractorCharges` emits a non-billable deduction; invoice
unchanged → new-item path (Admin approve → PO → receive → fulfill) → manual
stock-out + return adjust stock with no charge → `composer test` + `composer stan` +
`npm run build`/`types` green.

## Related

- ADR-0026, ADR-0012/0014/0015, ADR-0005/0006, ADR-0025
- `20-domain/{workflows,inventory,adjustments}.md`
- `80-plan/roadmap.md`, `80-plan/phase-03-work-orders-time-invoicing.md`
