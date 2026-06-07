# Flow: Import Hours from External System

| Field | Value |
|---|---|
| Status | Accepted |
| Last updated | 2026-05-21 |
| Owner | Product + Engineering |

For hotels that don't use QC Minute for clock-in but where QCP still places contractors and bills for hours, the system imports a weekly Excel export from the hotel's payroll system.

The import is a **wizard-driven flow** in the back office, ending with: time entries created, timesheet auto-approved, invoice generated.

## Actors

| Actor | Role |
|---|---|
| Office manager / Recruiter / Payroll | Uploads the file and resolves matches |
| System | Parses, matches, validates, applies |

## Pre-conditions

- Property exists in Property Bible
- Property is configured as "import-only" (no clock-in expected)
- Contractor records exist in the system, with `external_id` per `(property_id, external_id)`
- Payroll period exists for the import week (auto-created)
- User has `imports.upload` and `imports.commit` permissions

## Expected file shape

Excel file (.xlsx). Columns (order may vary; headers detected):

| Header (variations accepted) | Meaning |
|---|---|
| Name | Display name of the contractor (for human verification) |
| ID / EmployeeID / EmpID | The external ID for matching |
| TotalHours / Hours / Hrs | Weekly total hours (decimal, e.g. 40.5) |
| PayRate / Rate | What the hotel paid (per hour) |
| BillRate (optional) | What QCP charges (per hour) — sometimes in file, sometimes from contract |
| StartDate | Beginning of the work week |
| EndDate | End of the work week |
| Position (optional) | Position name (used for WO auto-creation) |

Extra columns are ignored. Missing required columns cause a hard fail with a clear error message.

## Visual flow

```
Office Manager → "New Import" button
  │
  ▼
[Step 1: Upload]
  Select property (dropdown of import-only properties)
  Select payroll period (defaults to current open period)
  Upload .xlsx file
  Click Parse
  │
  │ Server parses file → creates import_batches row + import_batch_rows
  ▼
[Step 2: Review Matches]
  Display table:
    File rows × {matched contractor | unmatched | rate conflict | duplicate}
  
  Stats: 32 rows, 28 matched, 3 unmatched, 1 rate conflict
  
  For each unmatched row:
    - Show: name + external_id + position
    - Action: "Find existing person" (search) OR "Create new contractor" (inline form) OR "Skip"
  
  For each rate conflict:
    - Show: file rate vs existing WO rate
    - Action: "Use file rate (create new WO)" OR "Use existing WO rate (override file)"
  
  Click "Next: Add Adjustments"
  │
  ▼
[Step 3: Add Adjustments (optional)]
  Per contractor row, optionally add adjustments from the catalog
    - Adjustment item dropdown
    - Value
    - Notes
  Can add multiple per contractor
  
  Click "Next: Review"
  │
  ▼
[Step 4: Final Review]
  Display final summary:
    - X rows will become time_entries
    - Y new contractors will be created
    - Z work orders will be created
    - W adjustments will be added
    - Total billable: $X,XXX.XX
    - Total payout: $X,XXX.XX
  
  Click "Commit Import" (with confirmation)
  │
  │ DB transaction:
  │   - Create time_entries (source=imported)
  │   - Create work_orders for new pairs
  │   - Create time_entry_adjustments
  │   - Recompute time_summaries
  │   - Auto-create + auto-approve timesheet
  │   - Auto-generate invoice (frozen)
  │
  ▼
[Step 5: Result]
  "Import successful"
  Links to: invoice, timesheet, import batch details
  Option: "Send Invoice to Property" (manual, as with clock-in flow)
```

## Server-side detail

### Step 1: Upload

**Input:** Property ID, payroll period ID, uploaded file.

**Actions:**
1. Validate user permissions
2. Validate property is import-only
3. Compute file_hash; reject if matches a recently-committed batch (duplicate file)
4. Parse Excel via maatwebsite/excel
5. Create `import_batches` row with status = `parsing`
6. For each row: create `import_batch_rows` with raw_data jsonb
7. Run match attempts:
   - For each row: find `people_external_ids` where (property_id, external_id) match
   - Set `matched_person_id` if exactly one match; else null
8. For each matched row, check existing WO for (person, property):
   - If exists and rates match → status = `matched`
   - If exists with different rates → status = `rate_conflict`
   - If doesn't exist → status = `needs_wo_creation`
9. Set batch status = `preview`

**Response:** Redirect to Step 2 review page.

### Step 2: Resolve

**For unmatched rows:**

UI options per row:
- "Find existing person": search `people` by name (autocomplete); on selection, also creates a `people_external_ids` entry so future imports match automatically
- "Create new contractor": inline form (minimal fields); creates `people` with status = `contractor_active` + `people_external_ids` entry
- "Skip": row is dropped from this import; logged

**For rate conflict rows:**

UI options per row:
- "Use file rate" → triggers new WO creation on commit (old WO will be closed)
- "Use existing WO rate" → file rate ignored, existing WO used; logged as "rate override"

User submits resolutions. Server updates rows accordingly.

### Step 3: Adjustments (optional)

UI: per-contractor row, add adjustments from the standard catalog.

Server: store as pending in the batch metadata; not yet creating `time_entry_adjustments`.

### Step 4: Final review

UI: aggregate summary screen. User confirms.

### Step 5: Commit

**Heavy transactional work:**

```php
DB::transaction(function () use ($batch) {
    foreach ($batch->rows()->matched()->get() as $row) {
        // Resolve or create work order
        $workOrder = match ($row->resolution) {
            'use_existing' => WorkOrder::find($row->existing_wo_id),
            'create_new_rate' => $this->createNewWorkOrderFor($row),
            'create_new_contractor' => $this->createForNewContractor($row),
        };
        
        // Create the time entry
        $entry = TimeEntry::create([
            'person_id' => $row->matched_person_id,
            'work_order_id' => $workOrder->id,
            'property_id' => $batch->property_id,
            'payroll_period_id' => $batch->payroll_period_id,
            'source' => 'imported',
            'entry_type' => 'work',
            'duration_minutes' => $row->raw_data['hours'] * 60,
            'pay_rate_snapshot' => $workOrder->pay_rate,
            'bill_rate_snapshot' => $workOrder->bill_rate,
            'ot_pay_rate_snapshot' => $workOrder->ot_pay_rate,
            'ot_bill_rate_snapshot' => $workOrder->ot_bill_rate,
            'source_metadata' => [
                'import_batch_id' => $batch->id,
                'row_number' => $row->row_number,
                'original_external_id' => $row->raw_data['external_id'],
                'original_file_data' => $row->raw_data,
            ],
            'created_by' => auth()->id(),
        ]);
        
        $row->update([
            'status' => 'applied',
            'resulting_time_entry_id' => $entry->id,
        ]);
    }
    
    // Apply adjustments
    foreach ($batch->pending_adjustments as $adj) {
        TimeEntryAdjustment::create([...]);
    }
    
    // Recompute summaries for all affected (work_order, week) pairs
    foreach ($affectedWorkOrders as $woId) {
        RecomputeTimeSummary::dispatchSync(
            workOrderId: $woId,
            payrollPeriodId: $batch->payroll_period_id
        );
    }
    
    // Create timesheet (auto-approved)
    $timesheet = Timesheet::create([
        'property_id' => $batch->property_id,
        'payroll_period_id' => $batch->payroll_period_id,
        'source' => 'imported',
        'status' => 'approved',
        'approved_at' => now(),
        'approved_by' => auth()->id(),
    ]);
    
    // Auto-generate invoice (same logic as clock-in path approval)
    GenerateInvoiceJob::dispatchSync($timesheet);
    
    $batch->update([
        'status' => 'applied',
        'applied_at' => now(),
    ]);
});
```

### Step 5: Result

UI shows:
- "Import successful"
- Link to the invoice
- Link to the timesheet
- Link to the import batch details (for audit)
- Optional: "Send Invoice to Property" button (same as clock-in flow)

## Re-import / corrections (void + reissue)

If a hotel sends a corrected file for an already-imported week:

1. Office manager opens the original import batch
2. Clicks "Void and Re-import"
3. System:
   - Voids the existing invoice (per ADR-0006)
   - Deletes the existing time_entries (source = imported, batch = X)
   - Recomputes summaries (now zero for that week)
   - Voids the timesheet
4. User starts a new import with the corrected file
5. New invoice generated; references voided one

The original batch is preserved in `import_batches` with status = `rolled_back`.

## Rollback (no replacement)

For mistakes (wrong file uploaded), authorized users can rollback an import without re-importing:

1. Voids the invoice
2. Deletes the time_entries
3. Recomputes summaries
4. Voids the timesheet
5. Batch status = `rolled_back`

## Edge cases

| Case | Handling |
|---|---|
| File has no header row | Reject with clear error |
| File has different week than selected payroll period | Block at upload; require matching |
| Contractor in file but not in system, user doesn't want to create | Mark row as skipped; logged on the batch |
| Two file rows for the same contractor (same week) | Reject as "duplicate IDs"; user must clean the file |
| Position in file doesn't match any property position | Surface during preview; user picks from dropdown |
| File rate is $0 | Block; clearly an error |
| File rate dramatically different from existing WO (>50% delta) | Flag as warning even when using existing rate |

## Audit trail

Every import creates:

- One `import_batches` row (file_hash, name, who, when, status)
- One `import_batch_rows` per file row (raw_data, status, resolution, resulting_time_entry_id)
- One `activity_log` entry per resolution decision (`needs_wo_creation` → `applied` etc.)
- One `activity_log` entry for the commit ("Import committed: 32 time entries, 1 invoice, $X,XXX")

Disputes ("why is John charged for 40 hours?") trace from the invoice → invoice_item → time_entry → source_metadata → import_batch_row → original_file_data.

## Permissions

| Action | Roles |
|---|---|
| Upload | super_admin, office_manager, payroll, recruiter (own property) |
| Commit | super_admin, office_manager, payroll, recruiter (own property) |
| Void / rollback | super_admin, office_manager, payroll |

## Related

- ADR-0006 — Invoice freeze + void/reissue (applies here)
- ADR-0008 — Time entries + summaries (entries created as `imported`)
- `20-domain/time-tracking.md`
- `20-domain/timesheets.md`
- `20-domain/invoicing.md`
- `40-flows/timesheet-approval.md` — equivalent for clock-in properties
- `30-schema/time-tables.md` (TBD)
