# Flow: Timesheet Approval

| Field | Value |
|---|---|
| Status | Accepted |
| Last updated | 2026-05-21 |
| Owner | Product + Engineering |

The end-to-end flow for **clock-in properties**: contractors clocked in throughout the week, recruiter submits the timesheet for approval, PM approves or declines, invoice generates automatically, notification is sent manually.

For **import-only properties**, the equivalent flow lives in `40-flows/import-hours.md` (no approval step — auto-approved on import commit).

Related decision: ADR-0007.

## Actors

| Actor | What they do |
|---|---|
| Contractor | Clocks in/out via tablet at the hotel |
| Recruiter | Reviews accumulated hours, submits for approval, edits if declined |
| Property Manager | Approves or declines |
| Office Manager / Super Admin | Can act in recruiter's place if needed |
| System | Auto-generates invoice on approval |

## Pre-conditions

- Property exists in Property Bible
- At least one work order exists for the period
- At least one time entry exists for the period
- Payroll period exists (auto-created by scheduled job)
- Recruiter is assigned to the property
- PM has login access to QC Minute

## Visual flow

```
Mon-Sun: contractors clock in/out
  │  payroll_period.status = open
  │  timesheets.status = draft
  │  PM sees live grid in QC Minute (read-only)
  │  Recruiter sees same grid in back office (editable)
  ▼
Sunday (or whenever recruiter chooses):
  Recruiter reviews timesheet
    - Fixes manual entries / corrections
    - Adds adjustments (incentives/deductions) if needed
  Recruiter clicks "Send for Approval"
  ┌────────────────────────────────────────┐
  │ timesheet.status = pending_approval    │
  │ payroll_period.status = locked         │
  │ Notification → PM (mail + in-app)      │
  └────────────────────────────────────────┘
  ▼
PM logs into QC Minute
  Reviews timesheet
  Two choices:
    
  ┌─── Approve ───┐         ┌─── Decline ───┐
  │               │         │               │
  ▼               │         │               ▼
  approved        │         │            declined
  │               │         │               │
  │               │         │   Reason required (text + optional category)
  │               │         │   Notification → Recruiter (in-app + email)
  │               │         │               │
  │               │         │               ▼
  │               │         │   Recruiter edits timesheet
  │               │         │   (payroll_period.status = open again)
  │               │         │   Re-clicks "Send for Approval"
  │               │         │   (loop back to pending_approval)
  │               │         └───────────────┘
  ▼
[automatic] Invoice generation
  ┌────────────────────────────────────────┐
  │ Invoice created (frozen, snapshotted)  │
  │ invoice.status = invoiced              │
  │ timesheet.status = invoiced            │
  │ payroll_period.status = invoiced       │
  │ Notification → Recruiter (in-app+mail) │
  │ NO automatic notification → PM         │
  └────────────────────────────────────────┘
  ▼
Recruiter sees "Invoice ready to send" on dashboard
  Clicks "Send Invoice to Property"
  ┌────────────────────────────────────────┐
  │ Email via Postmark — link, no PDF      │
  │ invoice.notification_sent_at = now     │
  │ invoice.status = invoice_sent          │
  │ timesheet.status = invoice_sent        │
  └────────────────────────────────────────┘
  ▼
PM (if they didn't see it before) sees invoice in QC Minute
PM pays through their own AP process
(Out of system from here)
```

## Step-by-step detail

### Step 1: Recruiter submits

**Trigger:** Recruiter manual action.

**Pre-conditions:**
- Timesheet exists in `draft` (auto-created by scheduled job)
- At least one time entry exists
- Recruiter has `timesheets.submit_for_approval` permission on this property

**UI:**
- Back office → Properties → [Name] → Timesheets → Current week
- "Send for Approval" button (large, primary)
- Confirmation modal with optional note to PM

**Server actions:**
```php
DB::transaction(function () {
    $timesheet->update([
        'status' => 'pending_approval',
        'sent_for_approval_at' => now(),
        'sent_for_approval_by' => $user->id,
    ]);
    
    $timesheet->payroll_period->update(['status' => 'locked']);
    
    activity()->on($timesheet)
        ->causedBy($user)
        ->withProperties(['note' => $request->note])
        ->log('Sent for approval');
    
    // In-app notice (instant) plus a queued email — see phase-09g.
    Notification::send($pms, new TimesheetStatusChanged($timesheet, 'submitted', '…'));
    Notification::send($pms, new TimesheetAwaitingApproval($timesheet));
});
```

**Post-conditions:**
- timesheet.status = pending_approval
- payroll_period.status = locked
- PM notified
- Activity log entry

**Failure modes:**
- No time entries → button disabled, message "Add at least one time entry first"
- Period already locked → "Already submitted" (shouldn't happen via UI; defensive check)

### Step 2: PM reviews

**Trigger:** PM opens the email notification, or logs into QC Minute and sees the pending timesheet in their dashboard.

**Pre-conditions:**
- PM has `timesheets.view_history` and `timesheets.approve`/`decline` permissions

**UI:**
- QC Minute → Dashboard → "Timesheets awaiting approval" widget OR direct deep-link from email
- Same weekly grid view (now showing as "Pending Approval")
- Two buttons: "Approve" (green) and "Decline" (orange/red)
- Note field if note was added during submission

### Step 3a: PM approves

**UI:**
- Click "Approve" → confirmation modal
- "Confirm: I approve this timesheet and authorize the invoice"
- Optional note

**Server actions:**
```php
DB::transaction(function () {
    $timesheet->update([
        'status' => 'approved',
        'approved_at' => now(),
        'approved_by' => $user->id,
    ]);
    
    activity()->on($timesheet)
        ->causedBy($user)
        ->log('Approved');
    
    GenerateInvoiceJob::dispatch($timesheet);
});
```

**GenerateInvoiceJob** then:

```php
// (queued, with retries)
DB::transaction(function () use ($timesheet) {
    $timesheet->refresh()->lockForUpdate();
    
    // Idempotency
    if ($timesheet->invoice_id) return;
    
    $invoice = Invoice::create([...]);
    
    foreach ($timesheet->workOrders() as $wo) {
        $summary = TimeSummary::where(...)->first();
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'work_order_id' => $wo->id,
            'contractor_name' => $wo->contractor->fullName(),
            // ... all snapshots
        ]);
    }
    
    foreach ($billableAdjustments as $adj) {
        InvoiceAdjustment::create([...]);
    }
    
    $invoice->update([
        'work_subtotal' => ...,
        'adjustment_total' => ...,
        'subtotal' => ...,
        'tax_amount' => ...,
        'total' => ...,
        'property_snapshot' => $timesheet->property->toSnapshot(),
        'invoicer_snapshot' => config('company.invoicer_snapshot'),
        'invoice_number' => generateNextInvoiceNumber(),
        'status' => 'invoiced',
        'frozen_at' => now(),
    ]);
    
    $timesheet->update([
        'invoice_id' => $invoice->id,
        'status' => 'invoiced',
    ]);
    
    $timesheet->payroll_period->update([
        'status' => 'invoiced',
        'invoiced_at' => now(),
    ]);
    
    event(new InvoiceGenerated($invoice));
});
```

**Post-conditions:**
- Invoice exists, frozen
- Timesheet linked
- Payroll period invoiced
- Recruiter sees on dashboard ("Invoice ready to send")

### Step 3b: PM declines

**UI:**
- Click "Decline" → modal:
  - Required text: "Reason for declining"
  - Optional dropdown: Category (Wrong Hours / Wrong Rate / Unauthorized Work / Other)
- Submit

**Server actions:**
```php
$timesheet->update([
    'status' => 'declined',
    'declined_at' => now(),
    'declined_by' => $user->id,
    'decline_reason' => $request->reason,
    'decline_category' => $request->category,
]);

$timesheet->payroll_period->update(['status' => 'open']);

activity()->on($timesheet)
    ->causedBy($user)
    ->withProperties(['reason' => $request->reason])
    ->log('Declined');

$timesheet->sentForApprovalBy->notify(
    new TimesheetDeclined($timesheet, $request->reason)
);
```

**Post-conditions:**
- timesheet.status = declined
- payroll_period.status = open (back to editable)
- Recruiter notified with reason
- Activity log entry

### Step 4: Recruiter edits and resubmits

Period is open again. Recruiter:
- Reviews decline reason on the timesheet detail page
- Edits time entries / adjustments as needed
- Clicks "Send for Approval" again (loops back to Step 1)

There's no limit on cycles. Each loop is auditable.

### Step 5: Recruiter sends invoice

**Trigger:** After approval (with invoice generated), recruiter chooses when to notify the PM.

**UI:**
- Recruiter dashboard → "Approved invoices awaiting notification" widget
- Each entry: property name + invoice number + total + age
- Click on entry → invoice detail page
- "Send Invoice to Property" button

**Server actions:**
```php
$pdf = InvoicePdfService::render($invoice);

Mail::to($recipient)
    ->send(new InvoiceMail($invoice, $pdf, $request->cover_message));

$invoice->update([
    'status' => 'invoice_sent',
    'notification_sent_at' => now(),
    'notification_sent_by' => $user->id,
    'notification_recipient' => $recipient,
]);

$timesheet->update(['status' => 'invoice_sent']);
```

**Post-conditions:**
- Invoice in `invoice_sent` state
- Email delivered to recipient
- PM can also see in QC Minute if they log in

## Edge cases

| Case | Handling |
|---|---|
| Two recruiters race to submit | DB-level UNIQUE on (timesheet, status='pending_approval'); second one fails clearly |
| PM approves before recruiter submits | UI doesn't show the approval button until pending_approval; can't happen via supported flow |
| PM doesn't act for a long time | Stays in pending_approval; recruiter dashboard surfaces age; can re-notify with one click |
| Invoice generation fails | Job retries; alerts on persistent failure; timesheet stays in approved (recoverable) |
| Recruiter forgets to send invoice | Stays in invoiced; dashboard counter visible; no auto-send by design |
| PM logs in and sees unsent invoice | Fine — they can view and download |

## Activity log examples

Each row in `activity_log` for this flow:

```
[2026-05-19 17:34:00] Sent for approval     - Recruiter Jane Doe → Timesheet #42 (Marriott DT, week of 2026-05-12)
[2026-05-19 19:12:00] Declined              - PM John Smith → Timesheet #42 ("John's Tuesday hours look high")
[2026-05-19 19:45:00] Sent for approval     - Recruiter Jane Doe → Timesheet #42 (re-submission)
[2026-05-19 21:08:00] Approved              - PM John Smith → Timesheet #42
[2026-05-19 21:08:01] Invoice frozen        - System → Invoice INV-2026-001234
[2026-05-20 09:15:00] Invoice sent          - Recruiter Jane Doe → Invoice INV-2026-001234 (to billing@marriott.com)
```

## Related

- ADR-0007 — Staged timesheet approval (full decision)
- ADR-0006 — Invoice freeze + void/reissue
- `20-domain/timesheets.md` — state machine details
- `20-domain/invoicing.md` — invoice generation details
- `40-flows/import-hours.md` — alternative flow for import-only properties
- `40-flows/invoice-generation.md` — the auto-generation pipeline (TBD separate file or merged here)
