# Schema Conventions

| Field | Value |
|---|---|
| Status | Accepted |
| Last updated | 2026-05-21 |
| Owner | Engineering |

Rules that apply to **every table** unless an ADR explicitly overrides. Following these consistently makes the schema easier to reason about, query, and migrate.

## Naming

- **Tables: snake_case, plural.** `people`, `time_entries`, `payroll_periods`.
- **Columns: snake_case.** `created_at`, `pay_rate_snapshot`.
- **Foreign keys: `{singular}_id`.** `person_id`, `work_order_id`, `payroll_period_id`.
- **Pivot tables: alphabetical order of related table singulars.** `kb_article_role`, not `role_kb_article`.
- **Boolean columns: `is_*`, `has_*`, `was_*`, or `*_active` patterns.** `is_billable`, `was_updated`, `is_anonymized`.
- **Status enums: column named `status`.** Enum cases in lowercase_snake_case.
- **Timestamps: `_at` suffix.** `approved_at`, `frozen_at`, `notification_sent_at`.
- **Person FK timestamps: paired with `_by`.** `approved_at` + `approved_by`, `created_at` + `created_by`.
- **Snapshot columns: `_snapshot` suffix.** `pay_rate_snapshot`, `bill_rate_snapshot`. For JSON snapshots, `_snapshot` is also fine: `property_snapshot`, `invoicer_snapshot`.

## Types

| Concept | Column type | Notes |
|---|---|---|
| Money | `BIGINT` | Stored as cents. Never `DECIMAL`, never `FLOAT`. |
| Money in form input | (decimal in form; converted to cents on save) | UX shows $30.00; DB stores 3000 |
| Percentages / rates (tax) | `DECIMAL(5,4)` | E.g. tax_rate stored as 0.0875 for 8.75% |
| Durations | `INT` minutes | `duration_minutes`, `regular_minutes`, etc. |
| Hours from external imports | (decimal in file) | Converted to minutes on import; stored as integer |
| Currency code | `CHAR(3)` | ISO 4217. Always 'USD' today; add column anyway for future |
| Phone numbers | `VARCHAR(32)` raw + `VARCHAR(15)` normalized | `phone` (display) + `normalized_phone` (digits only, for matching) |
| Dates only | `DATE` | `week_start`, `effective_date`, `hire_date` |
| Date + time UTC | `TIMESTAMP` (or `DATETIME`) UTC | Standard Laravel timestamps |
| Date + time in property TZ | `DATETIMETZ` + `timezone` string column | For displayable local time |
| JSON / arrays | `JSON` (MySQL 8 native) | For snapshots, source_metadata, etc. |
| Slugs | `VARCHAR(255)` | URL-safe; usually unique |
| Free text | `TEXT` | For notes, reasons, descriptions |
| Long text (article content) | `LONGTEXT` | For KB articles |

## Required columns on every table

Unless you have a deliberate reason to skip, every table includes:

```
- id (BIGINT UNSIGNED, PK, auto-increment)
- created_at, updated_at (TIMESTAMP, nullable, Laravel default)
- deleted_at (TIMESTAMP, nullable) — for soft-delete-eligible models
```

PII-bearing tables additionally include:

```
- legal_hold (BOOLEAN, default false)
- legal_hold_reason (TEXT, nullable)
- legal_hold_set_at, legal_hold_set_by (timestamp + person FK, nullable)
- is_anonymized (BOOLEAN, default false)
- anonymized_at, anonymized_by (timestamp + person FK, nullable)
```

See ADR-0010 and `20-domain/audit-and-pii.md`.

## Foreign keys

- **Always declare FKs in migrations.** No "loose" int columns that look like FKs but aren't enforced. (Legacy `applicants.job_id` was an example of what to avoid.)
- **Default on delete: RESTRICT.** Prevents accidental cascade deletes. Override explicitly when cascade is intended (e.g. `invoice_items` cascade on `invoice` delete).
- **Default on update: CASCADE.** Primary keys shouldn't change, but in case they do.
- **Cascade on delete for child tables in clear parent-child relationships:**
  - `invoice_items` → `invoices`
  - `kb_attachments` → `kb_articles`
  - `kb_article_versions` → `kb_articles`
- **`SET NULL` for "tracked by" relationships:**
  - `kb_articles.last_edited_by` → `people` (if the editor is deleted, article keeps its content)
  - `time_entries.created_by` → `people`

## Snapshot columns

Per ADR-0005, ADR-0006, and the broader principle: any value that's "as of the moment of this row's creation" gets snapshotted onto the row.

- Time entries snapshot rates from the work order
- Invoice items snapshot contractor names, position names, job codings, rates, hours, amounts
- Invoice rows snapshot property and invoicer info (jsonb)
- Activity log snapshots old + new property values

Snapshots are NEVER updated after creation. Editing a snapshot is a code smell.

## Tenant-free

There is **no `tenant_id`** on any table. See ADR-0002.

## Soft delete + retention

- Soft delete eligible: most domain tables — people, work_orders, time_entries, properties, invoices, kb_articles, products, ...
- Soft delete NOT eligible: payroll_periods (deletion would break referential integrity badly), activity_log (immortal), pivot tables generally
- Hard delete via `RetentionPurge` (scheduled, configured per model)

## Indexing

- **Primary FKs always indexed** (Laravel default)
- **Lookups by date range need composite indexes**: `(property_id, week_start)`, `(payroll_period_id, person_id)`
- **Frequent reporting axes get covering indexes**: `(week_start, week_end)` on summaries
- **Slugs and external IDs get unique indexes**: `(property_id, external_id)` on people_external_ids
- **Polymorphic lookups need composite indexes**: `(subject_type, subject_id)` on activity_log, `(feedbackable_type, feedbackable_id)` on feedback, `(fileable_type, fileable_id)` on files

## Enums

MySQL enum at the DB level for status columns:

```sql
status ENUM('draft', 'pending_approval', 'declined', 'approved', 'invoiced', 'invoice_sent', 'voided') NOT NULL DEFAULT 'draft'
```

PHP enum at the application level (typed enums for casting):

```php
enum TimesheetStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    // ...
}
```

Both must stay in sync. Adding a value requires a migration.

## Timestamps and timezones

- **All `_at` columns are stored as UTC.** Laravel default; do not deviate.
- **Display in the request user's timezone** at the view layer.
- **Property-locked timestamps** (e.g. `time_entries.start_at`) are stored as `DATETIMETZ` with the `timezone` column carrying the IANA name. This is for display fidelity; reports group by `start_at_utc`.

## JSON columns

- Use MySQL 8's native `JSON` type, not `TEXT`
- Document the schema of each JSON column in the migration comment + the domain doc
- Don't query into JSON columns from reports — extract relevant fields into proper columns

## Migrations

- **One migration per cohesive change.** Don't pile unrelated changes into one file.
- **Always reversible.** Provide `down()` even when painful.
- **Comments encouraged.** A `// Adds the snapshot columns per ADR-0005` comment helps the next reader.
- **Test data via factories + seeders, not in migrations.** Migrations are schema only.
- **Index changes get their own migrations.** Cheaper to revert.

## Pint formatting

All PHP enforced via `vendor/bin/pint --format agent` before commit (per Boost guidelines). No exceptions.

## Related

- `30-schema/erd.md` — high-level diagram
- ADR-0005, ADR-0006, ADR-0008, ADR-0009, ADR-0010 — schema-shaping decisions
- `20-domain/*` — semantic model that drives the schema
