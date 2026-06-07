# ADR-0006: Invoice Freeze + Void/Reissue

| Field | Value |
|---|---|
| Status | Accepted |
| Decided | 2026-05-21 |
| Owner | Product (David) + Engineering |
| Supersedes | — |
| Superseded by | — |

## Context

Invoices need to be correctable — errors happen, rates were wrong, hours were misallocated. But invoices are also legal/financial documents that customers rely on; editing them silently undermines trust and breaks audit trails.

The legacy QC Minute system already added invoice snapshotting in March 2026 (per-line snapshots of contractor names, job types, rates). What's still needed is a coherent answer to: "what's the actual workflow when an invoice turns out to be wrong?"

## Decision

**Invoices are immutable once frozen. Corrections are made by voiding the original and issuing a new invoice that references it.**

### Freeze

When a timesheet is approved → invoice is auto-generated → invoice is immediately frozen. Freezing means:

- Line items are written to `invoice_items` with full snapshots (contractor name, position, job coding, rates per bucket, minutes per bucket, amounts per bucket, totals)
- Adjustments are written to `invoice_adjustments` with full snapshots
- Property info is snapshotted to `invoice.property_snapshot` (jsonb)
- Invoicer (QCP) info is snapshotted to `invoice.invoicer_snapshot` (jsonb)
- Totals (work_subtotal, adjustment_total, subtotal, tax_amount, total) are computed and frozen

After freeze:

- No fields on the invoice are editable
- No new line items can be added
- No existing line items can be modified
- Only state transitions: `invoiced → invoice_sent`, `invoiced → voided`

### Void

If an invoice is wrong:

1. Authorized user (super_admin or payroll only) marks the invoice as `voided` with a required reason
2. `voided_at`, `voided_by`, `void_reason` populated
3. The invoice is excluded from financial reports (treated as $0)
4. The invoice is NOT deleted — it stays in the system with `status = voided`
5. The original notification (if sent) is NOT recalled — that's an out-of-system conversation

### Reissue

After voiding, create a new invoice:

1. Standard invoice generation path
2. The new invoice has a `replaces_invoice_id` field pointing at the voided original
3. The new invoice shows in the UI as "Invoice #1235 (replacement for #1234)"
4. The new invoice goes through the normal `invoiced → invoice_sent` flow

Both invoices remain in the audit trail. Reports sum non-voided invoices only.

## Consequences

### Positive

- Audit trail is preserved through every correction
- Customers can be told: "Disregard invoice #1234; here is corrected invoice #1235"
- Financial reports always reconcile (sum of non-voided invoices = revenue)
- No "phantom edits" that change history
- Tax/legal/dispute scenarios have clean documentation
- Aligns with industry standard practice

### Negative

- Users can't quickly "fix a typo" on an invoice — every correction is a void + reissue
- Two invoices in the system for one logical billing event
- UI must clearly distinguish voided from active invoices to avoid confusion

### Implementation requirements

Schema:

- `invoices.status` enum: `draft | invoiced | invoice_sent | voided`
- `invoices.frozen_at`, `frozen_by`
- `invoices.voided_at`, `voided_by`, `void_reason` (text, required when voided)
- `invoices.replaces_invoice_id` (nullable FK to invoices)
- `invoices.replaced_by_invoice_id` (nullable FK, populated when the original is voided AND a replacement is created)

Permissions:

- `invoices.void` granted to: super_admin, payroll only (see permissions matrix)
- `invoices.send` granted to: super_admin, office_manager, recruiter (own)

UI:

- Voided invoices show with strikethrough or warning banner
- Replacement invoices show a link back to the voided original
- Standard invoice list filters voided by default; toggle to include them

Reports:

- All financial aggregations have an implicit `WHERE status != 'voided'` filter
- Audit report can explicitly include voided invoices

## Alternatives considered

### A. Allow editing of unsent invoices, freeze only after send

Rejected. The "is this invoice final?" line becomes blurry. Easier to make freezing immediate on generation.

### B. Soft delete instead of void

Rejected. Voiding is a real business action with a real reason ("contractor's hours were entered wrong, refund needed"). It's not the same as "this row shouldn't have existed." Use the right concept.

### C. Editable invoices with a full history table

Rejected as overengineering. The void/reissue pattern provides full history through the two-invoice record. A separate history table would be heavier and still need void/reissue for customer-facing correction.

## Related

- ADR-0005 — Rate snapshots on time entries (parallel principle)
- ADR-0007 — Staged timesheet approval (invoice generation happens at approval)
- ADR-0008 — Time entries + summaries (the data sources for invoice freezing)
- `20-domain/invoicing.md` — full invoice lifecycle
- `40-flows/invoice-generation.md` — what happens at the approval → invoiced transition
