# ADR-0010: Legal Hold on PII

| Field | Value |
|---|---|
| Status | Accepted |
| Decided | 2026-05-21 |
| Owner | Product + Engineering |
| Supersedes | — |
| Superseded by | — |

## Context

QCP stores PII on contractors and W-2 staff: names, addresses, dates of birth, social security numbers, photos, government ID copies. Standard data hygiene requires:

- Ability to remove a former employee's data eventually (retention policies, GDPR-style requests)
- Ability to retain data during active legal matters (litigation, audit, wage dispute, investigation)
- Ability to anonymize without breaking referential integrity (invoices, timesheets reference these people)

Soft delete alone is insufficient: it lets data be removed but doesn't differentiate "safe to remove" from "must not be removed."

## Decision

**Four-layer PII handling:**

### 1. Soft delete (default behavior)

Every PII-bearing model has `deleted_at`. Default user action = soft delete. Hidden from normal queries; visible to admin with "view trashed."

Models with soft delete:
- `people`
- `kb_attachments` (may contain personal photos)
- Uploaded document records
- Contractor photos
- Anything in `files` polymorphic

### 2. Legal hold (blocks deletion)

A `legal_hold` flag (boolean + optional `legal_hold_reason` text + `legal_hold_set_at` + `legal_hold_set_by`). When true:

- Soft delete is **blocked** (model event throws exception)
- Hard delete is **blocked** (same)
- Retention purge **skips** the row entirely

Only `super_admin` can set or clear a legal hold (per `audit.legal_hold.set` and `audit.legal_hold.clear` permissions).

### 3. Retention purge (scheduled)

A daily scheduled job (`RetentionPurge`):

- Finds soft-deleted PII records older than the retention threshold (default 7 years for employment records; configurable per model)
- Skips any record with `legal_hold = true`
- Hard-deletes the qualifying records
- Logs each deletion to the activity log (which is never itself deleted)

The retention period is set per-model in config so different categories can have different lifespans.

### 4. Anonymization (for "right to be forgotten" requests)

When a person formally requests data removal but their data is referenced by financial records (invoices, timesheets), we **anonymize** instead of delete:

- `name` → "Former Employee"
- `email` → null
- `phone` → null
- `address` → null
- `dob` → null
- Profile photo deleted from storage
- Uploaded IDs deleted from storage
- A flag `is_anonymized` set to true
- The row stays, with its `id` intact, so invoice_items / timesheet rows that reference it still resolve

Anonymization is **irreversible**. Permission: `super_admin` only.

## Consequences

### Positive

- Legal/compliance posture is defensible
- Active legal matters are protected from accidental data loss
- Routine PII purging happens automatically (no manual cleanup burden)
- Financial records keep referential integrity through anonymization
- Audit trail is preserved through everything (immortal activity log)
- Clear separation of concerns: retention is automatic, legal hold is manual, anonymization is exceptional

### Negative

- Three deletion modes (soft, anonymize, hard-via-retention) to understand
- Legal hold requires manual setting — someone has to remember to flag matters
- Anonymization is one-way; mistakes are permanent
- Storage of soft-deleted data accumulates between retention runs

### Implementation requirements

Base trait `HasLegalHold` (applied to all PII models):

```php
public function delete()
{
    if ($this->legal_hold) {
        throw new LegalHoldException("Cannot delete: under legal hold.");
    }
    parent::delete();
}

public function forceDelete()
{
    if ($this->legal_hold) {
        throw new LegalHoldException("Cannot force-delete: under legal hold.");
    }
    parent::forceDelete();
}
```

Schema additions to every PII-bearing table:

- `deleted_at` (already standard from soft deletes)
- `legal_hold` (boolean, default false)
- `legal_hold_reason` (text, nullable)
- `legal_hold_set_at`, `legal_hold_set_by`
- `is_anonymized` (boolean, default false)
- `anonymized_at`, `anonymized_by`

Scheduled jobs:

- `RetentionPurge` — daily; hard-deletes per-model based on config retention period
- Audit log emits "retention_purged" event per row removed

UI:

- Admin can view legal-held records explicitly
- Setting/clearing legal hold requires confirmation + reason
- Anonymization requires double confirmation + reason

## Alternatives considered

### A. Soft delete only, manual hard-delete by admin

Rejected. Relies on manual processes; PII accumulates forever without action; legal-hold protection is informal at best.

### B. Hard delete with extensive audit log

Rejected. Once data is hard-deleted, "what was their address?" is unanswerable even for legitimate audit needs (within retention window). Soft delete + retention purge gives us both.

### C. Encrypted-at-rest with delete by key destruction (cryptographic erasure)

Rejected as overengineering for our scale. The pattern works for backup systems but adds complexity disproportionate to value for an operational CRM.

## Related

- `20-domain/audit-and-pii.md` — full PII handling model
- ADR-0004 — One people table (PII concentrated in `people`)
- `10-architecture/permissions-matrix.md` — `audit.legal_hold.*` permissions
