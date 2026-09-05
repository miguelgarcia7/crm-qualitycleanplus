# Timesheets

| Field | Value |
|---|---|
| Status | Accepted |
| Last updated | 2026-05-21 |
| Owner | Product (David) |

A **timesheet** is the weekly aggregation of a property's billable activity, with an approval lifecycle that gates invoice generation. One timesheet per (property, week).

See ADR-0007 for the full state machine rationale.

## Shape

```
timesheets
  - id
  - property_id (FK)
  - payroll_period_id (FK; UNIQUE — one timesheet per period)
  - source: enum (clock_in | imported)
  - status: enum (draft | pending_approval | declined | approved | invoiced | invoice_sent | voided)
  - sent_for_approval_at, sent_for_approval_by (FK to people)
  - declined_at, declined_by, decline_reason (text), decline_category (nullable enum)
  - approved_at, approved_by
  - invoice_id (FK, nullable; populated when invoice is generated)
  - created_at, updated_at
```

## States and transitions

```
draft (auto-created when payroll period exists)
  │
  │ recruiter "Send for Approval" (manual)
  ▼
pending_approval ──── PM "Decline" + reason ───→ declined
  │                                                │
  │                       recruiter edits, ────────┘
  │                       "Send for Approval"
  │ PM "Approve"
  ▼
approved
  │
  │ (automatic) invoice generated, frozen
  ▼
invoiced
  │
  │ recruiter "Send Invoice to Property" (manual, optional)
  ▼
invoice_sent

(any state above except draft/voided)
  │
  │ super_admin or payroll voids underlying invoice
  ▼
voided
```

### State details

| State | Meaning | Who can act |
|---|---|---|
| `draft` | Week is in progress or just closed; recruiter can edit time entries freely | recruiter |
| `pending_approval` | Submitted to PM; awaiting decision | property_manager |
| `declined` | PM declined; recruiter can edit and resubmit | recruiter |
| `approved` | PM approved; invoice is being generated | (none — automatic transition to invoiced) |
| `invoiced` | Invoice exists, not yet sent to property | recruiter (send invoice) |
| `invoice_sent` | PM has been notified or invoice has been delivered | (none — terminal happy path) |
| `voided` | Underlying invoice was voided (per ADR-0006) | (none — terminal) |

## Import-only timesheets

Properties using the import path skip `pending_approval`. The timesheet is created and immediately moved to `approved` (and then `invoiced`) as part of the import commit:

```
draft (created during import) → approved (auto) → invoiced (auto) → invoice_sent (manual)
```

No PM in the system means no approval flow. The import action itself is the approval. The timesheet's `source = imported` distinguishes it.

## Recruiter "Send for Approval"

```
Recruiter on back office
  → opens the property's draft timesheet
  → reviews the grid (contractors × days, totals)
  → adds adjustments if needed
  → fixes any time entry errors
  → clicks "Send for Approval"
  → optional: adds a note to the PM
Server actions:
  → timesheet.status = pending_approval
  → timesheet.sent_for_approval_at = now
  → timesheet.sent_for_approval_by = recruiter.id
  → payroll_period.status = locked (no further edits except super_admin)
  → notification to PM (mail + in-app)
```

## PM approval or decline

```
PM in QC Minute
  → notification leads them to the timesheet
  → reviews the same grid (read-only)
  → clicks "Approve" OR "Decline"
  
On Approve:
  → timesheet.status = approved
  → timesheet.approved_at, approved_by populated
  → Generate Invoice job fires:
      - In a DB transaction:
        - Invoice row created
        - InvoiceItem rows per work order (frozen snapshots)
        - InvoiceAdjustment rows for billable adjustments
        - Totals computed and frozen
        - Property + invoicer snapshots captured
      - invoice.status = invoiced
      - timesheet.invoice_id = invoice.id
      - timesheet.status = invoiced
      - payroll_period.status = invoiced
  → Notification to recruiter (in-app + email): "Invoice ready to send",
    linking straight to the invoice
  → NO automatic notification to PM about the invoice

On Decline:
  → modal asks for required reason (text) + optional category dropdown
    (Wrong Hours | Wrong Rate | Unauthorized Work | Other)
  → timesheet.status = declined
  → timesheet.declined_at, declined_by, decline_reason, decline_category populated
  → payroll_period.status = open (recruiter needs to edit)
  → Notification to recruiter (in-app + email) carrying the reason, linking to
    the weekly grid they have to correct
```

## Decline loop

```
Recruiter receives decline notification with reason
  → opens timesheet (now in declined state)
  → edits time entries (period is open)
  → adds/removes adjustments
  → clicks "Send for Approval" again
  → timesheet.status = pending_approval (cycle repeats)
```

A timesheet can loop through decline → resubmit unlimited times. The activity log preserves every cycle.

## Send Invoice to Property

```
Recruiter on back office, viewing approved invoice
  → clicks "Send Invoice to Property"
  → modal confirms recipient email and message
  → on confirm:
    invoice.status = invoice_sent
    invoice.notification_sent_at = now
    invoice.notification_sent_by = recruiter.id
    invoice.notification_recipient = email
    Email sent via Postmark
  → timesheet.status = invoice_sent
```

This is **manual and optional**. The invoice exists in the system the moment the timesheet is approved; sending notification is an explicit action. If the PM logs into QC Minute and views the invoice without notification being sent, that's fine too.

## Whole-timesheet decline (not per-line)

Decline is at the timesheet level. The PM cannot reject specific contractor rows. If they have an issue with one row, they decline the whole timesheet with the reason ("John's hours on Tuesday are wrong") and the recruiter fixes that row before resubmission.

Rationale: simpler state machine, forces clearer communication, avoids partial-approval edge cases. See ADR-0007 alternative B.

## Live grid vs approval view

| Surface | Who | What | Actions |
|---|---|---|---|
| Live grid (during week) | recruiter, PM, OM | Read-only weekly grid of clock activity | None for PM; recruiter can edit |
| Approval view (after submission) | recruiter, PM | Same grid, with totals | Approve / Decline (PM only) |
| History view (approved+) | recruiter, PM, OM, payroll | Same grid, frozen | None — read-only |

All views share one underlying Blade component; the affordances differ.

## What's on the timesheet display

The timesheet view shows:

- Property name + week range
- Status badge
- Bucket totals (regular hours, OT hours, holiday hours, training hours)
- Per-work-order rows: contractor name, position, hours per day, weekly totals
- Adjustments list (incentives + deductions)
- Computed totals (work subtotal, adjustment total, subtotal, tax, total)
- Audit summary: submitted by, approved by, with timestamps

Same component as the PM-facing approval view (different action buttons).

## Permissions

| Action | Roles |
|---|---|
| View live timesheet | recruiter (own), property_manager (own, RO), office_manager, super_admin |
| View history | All of the above + payroll |
| Submit for approval | recruiter (own), super_admin |
| Approve | property_manager (own), super_admin |
| Decline | property_manager (own), super_admin |
| Send invoice | recruiter (own), office_manager, super_admin |
| Override (force-approve) | super_admin only |

## Edge cases

| Case | Behavior |
|---|---|
| Recruiter never submits | Timesheet stays in draft indefinitely. Dashboard widget surfaces "stale drafts." Optional auto-reminder after 7+ days. |
| PM never responds | Stays in `pending_approval`. Recruiter sees on dashboard as "awaiting PM." Notification can be resent. |
| Invoice never sent | Invoice stays in `invoiced`. Recruiter dashboard counter visible. PM can still log in and see it. |
| Time entries created after submission (e.g. backfill) | Period is locked; only super_admin can edit. Normally the recruiter would unlock and resubmit. |
| Holiday added to property mid-week | Recompute summary picks it up; current draft timesheet reflects the new calculation; no action needed |

## Related

- ADR-0007 — Staged timesheet approval (full decision document)
- ADR-0006 — Invoice freeze + void/reissue
- `20-domain/time-tracking.md` — what the timesheet aggregates
- `20-domain/invoicing.md` — what happens on approval
- `40-flows/timesheet-approval.md` — step-by-step walkthrough
- `40-flows/invoice-generation.md` — the auto-invoice path
- `30-schema/time-tables.md` (TBD)
