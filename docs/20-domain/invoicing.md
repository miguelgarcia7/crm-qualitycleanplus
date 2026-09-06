# Invoicing

| Field | Value |
|---|---|
| Status | Accepted |
| Last updated | 2026-05-21 |
| Owner | Product (David) + Engineering |

Invoices are billed to **properties** (the hotel client). Generated automatically when a timesheet is approved. Frozen at creation. Corrections via void + reissue.

See ADR-0006 for the freeze + void/reissue rationale.

## Shape

```
invoices
  - id
  - property_id (FK)
  - payroll_period_id (FK)
  - timesheet_id (FK, unique — one invoice per timesheet)
  - invoice_number (unique, sequential, generated)
  - issue_date, due_date
  
  ── Snapshotted at freeze:
  - property_snapshot (jsonb — name, address, contacts at issue time)
  - invoicer_snapshot (jsonb — QCP details at issue time)
  - tax_rate (decimal)
  
  ── Totals (cents):
  - work_subtotal       (sum of invoice_items)
  - adjustment_total    (sum of invoice_adjustments)
  - subtotal            (work + adjustments)
  - tax_amount          (subtotal × tax_rate)
  - total               (subtotal + tax)
  
  ── Hour totals (denormalized for fast display):
  - total_regular_minutes, total_overtime_minutes,
    total_holiday_minutes, total_training_minutes
  
  ── Lifecycle:
  - status: enum (draft | invoiced | invoice_sent | voided)
  - frozen_at, frozen_by
  - notification_sent_at, notification_sent_by, notification_recipient
  - voided_at, voided_by, void_reason
  - replaces_invoice_id (nullable FK; when this invoice is a reissue)
  - replaced_by_invoice_id (nullable FK; populated when original is replaced)
  
  - created_at, updated_at, deleted_at
```

## `invoice_items` (line items per work order)

```
invoice_items
  - id
  - invoice_id (FK)
  - work_order_id (NOT an FK — stored as ID for historical reference; WO may be closed/deleted)
  - contractor_name (snapshot)
  - position_name (snapshot)
  - job_coding (snapshot, nullable)
  - pay_rate, ot_pay_rate, bill_rate, ot_bill_rate (snapshots, cents)
  - regular_minutes, overtime_minutes, holiday_minutes, training_minutes
  - regular_amount_bill, overtime_amount_bill, holiday_amount_bill, training_amount_bill (cents)
  - total_bill (cents)
  - total_payout (cents — what QCP pays the contractor, for internal margin reports)
  - created_at, updated_at
  - UNIQUE(invoice_id, work_order_id)
```

**For import-only invoices**, one line per contractor with the weekly total (no daily breakdown). Per ADR-0006 alternative consideration.

## `invoice_adjustments` (line items for adjustments)

```
invoice_adjustments
  - id
  - invoice_id (FK)
  - work_order_id (snapshot)
  - contractor_name (snapshot)
  - position_name (snapshot)
  - adjustment_item_name (snapshot)
  - type: enum (incentive | deduction — but only incentives flow to billable adjustments)
  - amount (cents)
  - created_at, updated_at
```

Only **billable** adjustments appear here. Deductions are never billable (see `20-domain/adjustments.md`).

## Generation flow

Invoice generation is **automatic** on timesheet approval. The full pipeline:

```
Timesheet status: pending_approval → approved (PM clicked Approve)
  │
  │ Listener: GenerateInvoice
  │ (queued, with DB transaction)
  ▼
1. Idempotency check: does timesheet.invoice_id already exist? Bail if so.
2. Begin transaction
3. Lock timesheet row (SELECT FOR UPDATE)
4. Create invoice row (status=draft, no snapshots yet)
5. For each work order with time entries in this period:
   - Read time_summary for (work_order, week)
   - Create invoice_item with frozen snapshots
6. For each billable adjustment in this period:
   - Create invoice_adjustment with frozen snapshots
7. Compute totals (work_subtotal, adjustment_total, subtotal, tax, total)
8. Capture property_snapshot (current property data as jsonb)
9. Capture invoicer_snapshot (current QCP data from config as jsonb)
10. Assign invoice_number (next in sequence)
11. Set status=invoiced, frozen_at=now, frozen_by=system
12. Update timesheet.invoice_id, timesheet.status=invoiced
13. Update payroll_period.status=invoiced
14. Commit transaction
15. Dispatch InvoiceGenerated event (notifies recruiter dashboard)
```

If the job fails, the transaction rolls back; the timesheet stays in `approved` and the job retries.

## Send Invoice to Property

Decoupled from generation. The recruiter clicks "Send Invoice to Property" on the invoice detail page:

1. Modal confirms recipient email, defaulted from the invoice's frozen
   `property_snapshot.billing_email` (set on the Property Bible profile). It stays
   editable — a one-off AP address is a legitimate need — but it is no longer typed
   from memory into an empty field.
2. Optional cover message
3. On confirm:
   - Email sent via Postmark carrying a **link**, not a PDF attachment — the
     recipient views the invoice on QC Minute and downloads the PDF from there,
     which keeps the mail small and the document behind a login
   - Delivery is **synchronous**, and the status only moves once it succeeds:
     `SendInvoice` marks an invoice sent after Postmark accepts it, so a failed
     send leaves the invoice unsent rather than claiming otherwise. (The timesheet
     emails in phase 09g are the opposite — queued — because their state changed
     before the mail was attempted.)
   - `invoice.notification_sent_at`, `notification_sent_by`, `notification_recipient` populated
   - `invoice.status = invoice_sent`
   - `timesheet.status = invoice_sent`
   - Activity log entry

PM can also access invoices by logging into QC Minute — `notification_sent_at` is informational about whether they were emailed, not whether they've seen it.

## Voiding

Per ADR-0006. Allowed for super_admin and payroll only.

```
User on invoice detail page
  → clicks "Void Invoice"
  → modal requires reason (text, required) and reissue intent
On confirm:
  → invoice.status = voided
  → invoice.voided_at = now
  → invoice.voided_by = user.id
  → invoice.void_reason = text
  → timesheet.status = voided
  → payroll_period.status returns to... TBD (probably stays at invoiced but with flag)
  → Activity log
```

The original is NOT deleted. Reports filter out voided invoices for financial aggregations.

## Reissue

After voiding, the user creates a corrected invoice. There are two patterns:

**A. Reissue from voided invoice (preferred):**
- "Create Replacement" button on the voided invoice
- Opens a wizard pre-populated with the voided invoice's lines
- User edits as needed (fixing the original error)
- On save: new invoice created with `replaces_invoice_id` set to the voided original
- New invoice gets a new invoice_number, normal status=invoiced flow

**B. Re-approve timesheet:**
- Recruiter unlocks the period (super_admin override required)
- Edits time entries to correct the underlying data
- Sends timesheet for approval again
- PM re-approves
- New invoice generated normally; no `replaces_invoice_id` set, but audit log shows the sequence

Pattern A is for "we computed wrong" cases. Pattern B is for "the underlying hours were wrong" cases. Both are supported.

## Invoice numbering

Sequential per environment (production has its own sequence, staging its own). Generated via a dedicated `invoice_number_seq` table or DB sequence to ensure uniqueness even under concurrent generation.

Format: `INV-YYYY-NNNNNN` (e.g. `INV-2026-001234`).

## PDF generation

A Blade view (`emails.invoice.pdf`) renders the invoice with all snapshotted data. Rendered to PDF via a service (TBD — `dompdf`, `barryvdh/laravel-dompdf`, or `wkhtmltopdf`).

The PDF includes:

- Invoicer header (QCP info from snapshot)
- Property bill-to block (from snapshot)
- Invoice number, dates
- Line items table with hours and amounts per bucket
- Adjustments table
- Totals box
- Notes / terms

PDFs are not stored permanently — regenerated on demand from the frozen invoice data. (The data is the truth; PDF is a presentation layer.)

## Excel export

Recruiters and PMs can download an Excel version of the invoice (e.g. for property accounting departments that prefer Excel). Same data, different format. Carried forward from QC Minute's `PropertyTimeSheetExport`.

## Permissions

| Action | Roles |
|---|---|
| View invoices (own) | recruiter, property_manager, super_admin, office_manager, payroll |
| View all invoices | super_admin, office_manager, payroll |
| Send notification | recruiter (own), office_manager, super_admin |
| Void | super_admin, payroll |
| Reissue (create replacement) | super_admin, payroll |
| Export Excel | viewers of the invoice |

## Edge cases

| Case | Behavior |
|---|---|
| Generation fails mid-transaction | Transaction rolls back; job retries up to N times; alert on persistent failure |
| Timesheet approved twice (race) | First wins; second sees existing invoice_id and bails (idempotency check) |
| Property's tax rate changes between week and approval | Invoice uses tax rate at *approval* time (frozen on snapshot) |
| Property data changes between week and approval | Same — frozen on snapshot at generation |
| Negative invoice (refund?) | Not supported as standalone — refunds are void + reissue at lower total |
| Zero-total invoice | Allowed; generated normally if all adjustments cancel out |

## Related

- ADR-0006 — Invoice freeze + void/reissue
- ADR-0007 — Staged timesheet approval (triggers generation)
- `20-domain/timesheets.md` — upstream of invoicing
- `20-domain/adjustments.md` — what flows into invoice_adjustments
- `40-flows/invoice-generation.md` — full step-by-step
- `30-schema/invoice-tables.md` (TBD)
