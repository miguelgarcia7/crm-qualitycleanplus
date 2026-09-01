# Audit and PII

| Field | Value |
|---|---|
| Status | Accepted |
| Last updated | 2026-05-21 |
| Owner | Product + Engineering |

Two intertwined concerns:

1. **Audit** — what happened, when, by whom (immortal)
2. **PII** — personal data, with retention rules, legal hold, anonymization (deletable under controlled conditions)

See ADR-0010 for the full legal-hold + retention rationale.

## Audit log

All significant model changes are recorded via Spatie's activity log:

```
activity_log
  - id
  - log_name (string, indexed — e.g. 'default', 'auth', 'workflows', 'payroll')
  - description (text — short human-readable)
  - subject_type, subject_id (polymorphic — what changed)
  - causer_type, causer_id (polymorphic — who did it)
  - properties (jsonb — old + new values, context)
  - event (string — created | updated | deleted | restored | custom)
  - batch_uuid (string — for grouping related changes)
  - created_at, updated_at
```

### What gets logged

Every model that uses the `LogsActivity` trait. At minimum:

- `people` (status changes, role changes, PII updates, login events)
- `properties` (bible edits, status changes)
- `work_orders` (creation, rate changes, closures)
- `time_entries` (create, edit, delete — captures `was_updated` for non-contractor edits)
- `time_entry_adjustments` (create, edit, delete)

#### The `payroll` log

Time entries and adjustments are written to a `payroll` log by explicit `activity()` calls in their actions, not by the `LogsActivity` model trait — the actions know what the change *meant* ("changed the punch from 9:00 am–5:30 pm to 9:00 am–1:00 pm"), where a model trait would only see column diffs.

They are logged **against the contractor**, not against the entry or the person who made the change. The subject is what the trail is about, and the causer records who did it. This is what puts payroll corrections on the contractor's profile History tab, where someone reviewing a disputed week will actually look. The same rows are reachable from the global audit log at `/admin/audit`, filtered by log name.

A correction carries `from` and `to` in `properties` (start, end, minutes). Neither screen renders those today — the description sentence carries the times.
- `timesheets` (every state transition)
- `invoices` (frozen, sent, voided, replaced)
- `contracts` (uploaded, edited, downloaded)
- `workflows` and `workflow_steps` (state changes, approvals, rejections)
- `kb_articles` (publish, unpublish, archive, edit)
- `permissions` and roles (changes)
- Authentication (login, logout, failed attempts, password change)
- Settings changes
- Bulk actions (each subject change is its own log row, grouped by `batch_uuid`)

### What doesn't get logged

- Reads (too noisy — view tracking is on view_count where it matters)
- Background recomputation jobs (the trigger is logged, not the result)
- Routine scheduled tasks (cron logs handle these)

### Activity log is immortal

Activity log rows are **never** hard-deleted. Even when subjects are anonymized or purged, the activity log retains a reference (the subject_type + subject_id stay, but querying back resolves to an anonymized or null record).

This means:

- "Who approved invoice #1234?" is answerable forever
- "When did contractor X get terminated?" is answerable forever
- "Did this rate change happen before or after that audit?" is answerable forever

## PII handling

### What counts as PII

In `people` and related tables:

- Names (first, middle, last, second_last)
- Email, phone, address
- Date of birth
- Social Security Number (or last 4)
- Photos and uploaded IDs
- Emergency contact details (also PII for the contact)
- Bank info (if stored — TBD)

In `kb_attachments` and `files`:

- Anything uploaded that might be a person's photo or document

In `time_entries` (per ADR-0017 — field check-in flows):

- `clock_in_gps_lat`, `clock_in_gps_lng`, `clock_in_gps_accuracy_meters`
- `clock_in_selfie_file_id` (FK → files, which holds the selfie image)
- `clock_out_gps_lat`, `clock_out_gps_lng`, `clock_out_gps_accuracy_meters`
- `clock_out_selfie_file_id`

In `field_visits` (per ADR-0017 — recruiter visit logging):

- `check_in_gps_lat`, `check_in_gps_lng`, `check_in_gps_accuracy_meters`
- `check_in_selfie_file_id`
- `check_out_gps_lat`, `check_out_gps_lng`, `check_out_gps_accuracy_meters`

Each selfie creates a row in `files` (the polymorphic file storage), with the actual image stored in S3 (production) or local storage (dev).

### Soft delete (default)

Every PII-bearing table has `deleted_at`. Default deletion = soft. Soft-deleted rows are hidden from normal queries but recoverable.

### Legal hold (blocks deletion)

A `legal_hold` flag on PII-bearing rows. When true:

- Soft delete is blocked (model event throws `LegalHoldException`)
- Hard delete is blocked
- Retention purge skips the row

Only super_admin can set or clear:

- Set: requires a `legal_hold_reason` (text)
- Clear: requires a confirmation reason in the activity log

Use cases: active litigation, audit in progress, contractor wage dispute, government investigation.

### Retention purge (scheduled)

Daily job `RetentionPurge`:

1. For each PII model:
   - Find rows where `deleted_at < (now - retention_period)` AND `legal_hold = false`
   - Hard-delete in batches
2. Per-model retention configured in `config/retention.php`:
   ```php
   'people' => '7 years',
   'files' => '7 years',
   'failed_logins' => '90 days',
   'selfies' => '1 year',            // per ADR-0017 — field check-in flows
   'gps_coordinates' => '1 year',    // co-purged with parent time_entry / field_visit if older than retention
   ```
3. Log each deletion to activity_log (`event=retention_purged`)
4. Files in storage (S3) deleted in parallel

### Selfie + GPS retention (per ADR-0017)

Selfies and GPS coordinates captured during contractor clock-in (QR flow) and recruiter field visits are governed by these rules:

| Aspect | Behavior |
|---|---|
| Stored where | Selfies in S3 via `files` table; GPS as decimal columns on `time_entries` and `field_visits` |
| Retention period | **1 year** from creation |
| Purge job | `RetentionPurge` deletes selfie files past 1 year AND nulls the GPS columns on the parent row |
| Legal hold | Blocks both selfie file deletion AND GPS column nulling (the parent row's `legal_hold` flag covers it) |
| Anonymization | When a person is anonymized: all their selfie files are deleted immediately (not waiting for retention); GPS columns nulled on their time_entries and field_visits |
| Review access | Selfies are NOT actively monitored. Reviewed only on dispute or investigation by super_admin / admin / hr. |
| Audit logging | Selfie capture and selfie deletion both create activity_log entries |

The 1-year retention is shorter than the general 7-year people retention because selfies and GPS coordinates are highly sensitive verification data, not employment records. The verification need (proving "Maria was at Marriott on May 21 at 9:14 AM") fades quickly after the dispute window closes.

### Time entry GPS retention specifics

GPS coordinates on `time_entries`:

- Captured at every QR clock-in and clock-out
- Co-located with the time_entry row (not a separate table)
- Purged via NULL update (the time_entry stays for historical/billing purposes; only the GPS columns are blanked)
- The accuracy_meters field is also nulled when GPS is nulled (it's meaningless without the lat/lng)

This means: 1 year after a clock-in, the system can still show "Maria clocked in at Marriott on May 21 at 9:14 AM" (the time_entry remains for billing/payroll/audit), but cannot show the exact GPS coordinates. That's the right balance — audit trail preserved, location precision purged.

### Field visit purge

When a `field_visit` is purged past its 1-year retention:

- The selfie file is deleted from S3 and the files row hard-deleted
- GPS columns on the field_visit row are nulled
- The field_visit row itself stays (audit: "Jane visited Marriott on May 21 at 10:14 AM")
- Only the precise location and selfie image are removed

Same balance as time_entries — historical fact preserved, sensitive verification data purged.

### Anonymization (for "right to be forgotten" requests)

When a former contractor formally requests data removal and we can honor it:

```
On anonymization:
  - Set is_anonymized = true
  - Set anonymized_at, anonymized_by
  - Replace PII fields:
    - first_name = "Former"
    - last_name = "Contractor #{$id}"
    - email = null
    - phone = null
    - normalized_phone = null
    - address, city, state, zip = null
    - dob = null
    - ssn = null
  - Delete uploaded files from storage (file rows kept with anonymized filenames)
  - Profile photo deleted from storage; photo_url cleared
  - Activity log entry
```

The row itself stays (so invoices/timesheets/work orders that reference it still resolve). Display layers show the anonymized name.

Anonymization is **irreversible**. Permission: super_admin only. Confirmation flow requires typing the contractor's name + the word "ANONYMIZE."

### File storage and PII

Uploaded files go to S3 (production) or local storage (dev). When a person is anonymized or purged:

- Application code deletes the underlying S3 object (or local file)
- The file row in the `files` (or `kb_attachments`) table is itself soft-deleted
- Filename is replaced with a non-identifying placeholder

Files in storage should never outlive their referencing rows.

## Surfaces for review

### Activity log viewer

- Per-resource: every detail page has an "Activity" tab/section
- Global: super_admin and office_manager can search all activity (filters: causer, subject, date range, event type)
- Per-workflow: workflow detail page renders its activity timeline

### Legal hold list

- Super_admin only: a list of all currently held records
- Add/remove with required reason

### Anonymization request queue

- Super_admin only: a list of pending anonymization requests
- Per request: a form to review, deny, or anonymize
- High-friction (double confirmation, name match required)

## Encryption

Sensitive PII fields are encrypted at the application layer using Laravel's encrypted casts:

- `ssn` (full or last 4)
- `bank_routing`, `bank_account` (if stored)
- Maybe `dob` (TBD — common to encrypt)

```php
protected $casts = [
    'ssn' => 'encrypted',
    'dob' => 'encrypted:date',
];
```

DB-at-rest encryption (managed by RDS) covers everything else.

## Backups and PII

Database backups contain PII. Backups should:

- Be encrypted at rest
- Have a defined retention period (e.g. 90 days)
- Be excluded from anonymization (anonymization in production doesn't reach historical backups — operators should accept this and document it as a residual risk)

## Permissions

| Action | Roles |
|---|---|
| View activity log | super_admin, office_manager |
| Search global activity | super_admin, office_manager |
| Set legal hold | super_admin |
| Clear legal hold | super_admin |
| Initiate anonymization | super_admin |
| View retention purge log | super_admin |

## Related

- ADR-0010 — Legal hold on PII (full decision)
- ADR-0004 — One people table (PII concentrates in `people`)
- `10-architecture/permissions-matrix.md` — audit permissions
- `20-domain/people-lifecycle.md` — anonymization in context
- `30-schema/audit-tables.md` (TBD)
