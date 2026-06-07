# ADR-0004: One `people` Table with Status, Not Separate Tables

| Field | Value |
|---|---|
| Status | Accepted |
| Decided | 2026-05-21 |
| Owner | Engineering |
| Supersedes | — |
| Superseded by | — |

## Context

The legacy QCP CRM has three separate tables that all represent humans:

- `users` — anyone with CMS login (recruiters, office managers, etc.)
- `employees` — workforce records (W-2 staff details, with their own `password` field, but no FK to `users`)
- `applicants` — public job application submissions

A person who is hired moves *conceptually* from applicant → employee, but data-wise there's no link; the applicant record sits there, and a new employee record is created. The same person who is later promoted to a recruiter role ends up with both an `employees` row and a `users` row, again with no FK.

This creates real problems:

- Duplicate identity (same human, two rows, two passwords)
- Lost history (the original application data is detached from the employee record)
- Confused querying ("show me everyone we've hired" requires UNION)
- Bugs ("the user table and the employee table have the same email but different first names")

The new system needs a single source of truth for identity.

## Decision

**One `people` table** holds every human in the system, with a `status` enum that tracks lifecycle position:

```
status: applicant
        ↓ (recruiter promotes after onboarding)
        contractor_active
        ↓ (terminated, transferred, etc.)
        contractor_inactive  ←→  contractor_active (rehire path)
        ↓ (final state)
        terminated
        
And separately for staff:
        staff_active  ←→  staff_inactive
```

Plus immutable history columns:

- `application_date` (set when first created; never changes)
- `converted_to_contractor_at` (set on first promotion; never changes)
- `terminated_at` (set on termination)

**Roles** (Spatie) are independent of status. The same person can be a contractor (status = `contractor_active`, role = `contractor`) or a recruiter (status = `staff_active`, role = `recruiter`).

> **A person who is both a recruiter and a contractor has two `people` rows.** One per identity. This is intentional — the two roles have different audit, compensation, and access surfaces, and conflating them creates the same identity confusion we're solving.

## Consequences

### Positive

- One canonical record per identity
- Application data is preserved on the contractor profile forever (the "Application" tab on the contractor profile)
- Status transitions are auditable as a single timeline
- Queries are clean: `Person::contractors()` is one scope, not three
- File attachments accumulate over the lifecycle (resume at application → ID at onboarding → uniform receipts as active → final paycheck stub at termination)
- Rehires are status transitions (`contractor_inactive` → `contractor_active`), not new records — preserves history

### Negative

- The "same person, two identities" rule for recruiter-also-contractor edge case requires policy enforcement (HR has to know to create a new record, not flip the existing one)
- The People table will be wider than any single legacy table (more columns to cover all status cases)
- Some fields apply only to certain statuses (e.g. application_date, hire_date) — must be nullable

### Implementation requirements

- `people` table with columns covering: identity (name, email, phone, address), lifecycle (status, dates), employment (hire_date, role assignment), application data (citizenship, felony, transportation, emergency contact), uniforms (current balance), authentication (password for those who log in)
- `application_date` is set on creation and immutable thereafter (enforce in model)
- Status transitions are recorded in the activity log with old → new
- Move-to-contractor is reversible only while no work orders are attached
- `people_external_ids` separate table for hotel-specific external IDs (since one contractor may have different IDs at multiple hotels)

## Alternatives considered

### A. Keep separate tables, add FKs to link them

Rejected. Still requires duplicate data entry, still requires UNION queries, still has two-passwords problem. FKs help but don't solve the core issue.

### B. Separate `applicants` table, merge `users` and `employees`

Rejected. Same problem at the applicant boundary. The promotion from applicant to contractor is currently a real event that should be a status transition on one record, not a copy from one table to another.

### C. Multi-row identity with a `person_id` group key

Rejected as overengineering. The "same person, two identities" case is rare (a recruiter who occasionally contracts). Forcing every query to handle multi-row identities adds complexity disproportionate to the benefit.

## Related

- `20-domain/people-lifecycle.md` — full status machine + transitions
- `10-architecture/identity-and-auth.md` — login + role assignment
- ADR-0010 — Legal hold on PII (applies to `people` rows)
