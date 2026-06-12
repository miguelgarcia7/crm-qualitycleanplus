# People Lifecycle

| Field | Value |
|---|---|
| Status | Accepted |
| Last updated | 2026-05-21 |
| Owner | Product + Engineering |

The `people` table holds every human in the system. One row per identity. Status transitions track lifecycle position. Roles (separate, via Spatie) control access.

See ADR-0004 for the rationale behind single-table identity.

## Status state machine

```
                                            ┌────────────────┐
                                            │                ▼
applicant ─────promote─────→ contractor_active ──→ contractor_inactive
   │                              │                       │
   │                              │                       │
   │                              ▼                       ▼
   │                          pending_termination ←───────┘
   │                              │
   │                              ▼
   │                          terminated
   │
   └── (no work orders yet, recruiter changes mind) ──→ unmoved/discarded
   
Staff path (no applicant stage; created directly by HR):
                  
                  staff_active ──leave──→ staff_inactive
                       ▲                       │
                       └──────return───────────┘
                       │                       │
                       ▼                       ▼
                  pending_termination ◀────────┘
                       │
                       ▼
                  terminated
```

### Status values

| Status | Meaning | Allowed transitions |
|---|---|---|
| `applicant` | Submitted a job application; not yet promoted | `→ contractor_active` (promote) |
| `contractor_active` | Active contractor; can be on work orders, can clock in | `→ contractor_inactive`, `→ pending_termination` |
| `contractor_inactive` | Not currently working but eligible to return | `→ contractor_active` (rehire), `→ pending_termination` |
| `pending_termination` | Termination workflow initiated; person cannot work but file-move not yet complete (per ADR-0018) | `→ terminated` (on file-move completion), `→ prior status` (if super_admin cancels workflow) |
| `terminated` | Final state; cannot return without a new `people` record | (none — terminal) |
| `staff_active` | Active W-2 employee (recruiter, office manager, etc.) | `→ staff_inactive`, `→ pending_termination` |
| `staff_inactive` | Former staff member | `→ staff_active`, `→ pending_termination` |

Status transitions are recorded in the activity log with `old_status` and `new_status`.

### `pending_termination` semantics

A person in `pending_termination` cannot:
- Be on new work orders
- Clock in (QR or tablet)
- Submit new PTO requests
- Initiate new workflows
- Be assigned to new property assignments

They can still:
- View their own profile, hours, paychecks (read-only)
- Be referenced in historical data (timesheets, invoices, etc.)

Status flips to `terminated` when Front Desk completes the file-move step of the termination workflow. Until then, they remain `pending_termination`. See ADR-0018 and `40-flows/termination.md` for the full lifecycle.

## Immutable history

These columns are set at creation or first transition and **never change**:

- `application_date` — set when the row is created with `status = applicant`. Never updated, even on rehire. Preserves the legal record of original application.
- `converted_to_contractor_at` — set on the first applicant → contractor_active transition. Records when they joined.
- `terminated_at` — set when status moves to `terminated`. Records when the relationship ended.
- `hire_date` — set by HR when a staff member starts. Distinct from `converted_to_contractor_at` (which is for contractors).

Other dates (last clock-in, last paycheck, etc.) are derived from related tables.

## Move-to-contractor reversibility

A recruiter can promote an applicant by mistake. To handle this:

- **The promotion is reversible as long as no work orders are attached to the person.**
- Once any work order exists for this person, the promotion is irreversible (rolling back would orphan the work order).
- "Reverse" means: status goes back to `applicant`, `converted_to_contractor_at` is cleared, audit log captures the reversal.
- Permission: `people.applicants.promote` includes the right to reverse one's own promotions; super_admin can reverse anyone's.

## Onboarding checklist

When an applicant is being prepared for promotion to contractor, a checklist of required items must be tracked. Initial set (v1, hardcoded — may become configurable later):

| Item | Stored as |
|---|---|
| Government ID (front) | `id_front_uploaded_at`, `id_front_file_id` |
| Government ID (back) | `id_back_uploaded_at`, `id_back_file_id` |
| Work authorization (I-9) | `i9_uploaded_at`, `i9_file_id`, `i9_verified_by`, `i9_verified_at` |
| W-9 (for 1099 contractors) | `w9_uploaded_at`, `w9_file_id` |
| Signed contractor agreement | `contractor_agreement_signed_at`, `contractor_agreement_file_id` |
| Background check (if required) | `background_check_status`, `background_check_completed_at` |
| Uniform issued | tracked through inventory (see `20-domain/inventory.md`) |

Promotion to `contractor_active` is gated on completion of required items. Some items may be marked optional by HR.

**Who marks checklist items:** Per ADR-0013, `front_desk` is the primary role that marks checklist items as documents physically arrive (ID handed over, W-9 received, etc.). HR retains the same permission as a fallback and also makes the final promotion decision. The specific permission is `people.applicants.onboarding_checklist.edit`. Front Desk does not have broader applicant edit access — only the checklist fields.

Front Desk dashboard surfaces a widget: "Onboarding Docs Pending" — applicants with incomplete checklists where items are expected.

## External IDs (for import-only properties)

A contractor working at an import-only hotel needs to be matched against rows in that hotel's payroll export. The external ID is the hotel's identifier for the contractor.

Because the same contractor may work at multiple import-only hotels, each with its own ID system, this is a separate table:

```
people_external_ids
  - id
  - person_id (FK)
  - property_id (FK)
  - external_id (string)
  - source_system (string — name of the hotel's system, optional)
  - created_at, created_by
  - UNIQUE(property_id, external_id)
```

When an import runs, the system matches by `(property_id, external_id)`. Unmatched rows surface in the import preview for resolution.

## Identity separation rule

If the same human is both a recruiter (W-2 staff) and (occasionally) a contractor placed at a hotel, **they are two separate `people` rows** with distinct identities. This rule is enforced by convention, not by the schema (there's no constraint preventing dual roles on one record). HR must know to create the second record.

Why this matters:

- A recruiter's W-2 employment data (payroll, PTO, taxes) is wholly separate from a contractor's 1099 data
- The two roles have different access surfaces — one logs into back office, one logs into QC Minute
- Audit trails stay clean (you can see "recruiter Jane did X" without it confusing the "contractor Jane did Y" trail)
- If the contractor work ends, only that record's status changes, not the recruiter identity

## Email & soft deletes

`people.email` is **unique across soft-deleted rows too** — a deliberate decision, not an
accident. One identity per human: a rehire or re-application must reuse the original row
(preserving `application_date` and history), never create a second row with the same email.
Consequences:

- Validation (`Rule::unique(Person::class)`) checks the whole table, matching the DB
  constraint — a live user can't claim a soft-deleted person's email; they get a clean
  validation error.
- Public application intake (`SubmitApplication::resolvePerson`) looks up email
  `withTrashed()` and **restores** a soft-deleted match, so a returning person resurfaces
  their original record instead of hitting the unique constraint.
- Anonymized rows clear the email entirely (audit-and-pii.md), freeing it for reuse.
  NB: when the anonymization flow is built, `people.email` must become nullable —
  it is currently `NOT NULL`.

## Profile editing

Most fields on a person record are editable by:

- The person themselves (some fields, like phone/address — through a `request change` workflow, not direct edit)
- HR (most fields, with audit log entries)
- Super admin (anything)

Some fields are edit-restricted:

- `application_date`, `converted_to_contractor_at`, `terminated_at` — never editable after set (model-level enforcement)
- `dob`, `ssn` — once set, only HR or super admin can edit; an audit log entry is required with reason
- `email` — changes trigger a verification flow for accounts that log in
- `status` — only changeable via the appropriate workflow (terminate, transfer, etc.)

Contractor self-service edits go through a "Request Change" workflow:

1. Contractor submits change request from their profile in QC Minute
2. HR receives it as a workflow task
3. HR verifies and applies (or rejects with reason)

This prevents fraud (e.g. changing direct deposit info after gaining access to someone else's login).

## Files

People accumulate files over their lifecycle. All file references go through the polymorphic `files` table (see `20-domain/audit-and-pii.md`):

- Application form scan or upload
- Resume / CV
- IDs, work authorization documents
- Tax forms
- Signed contracts
- Uniform receipts
- Reprimands, performance notes (HR)
- Final paycheck stub

Files are soft-deleted with the person; legal hold prevents deletion.

## Anonymization

When a former contractor requests "right to be forgotten" and we can honor it (no legal hold, retention period passed or waived):

- Name → "Former Contractor #1234" (the ID stays for referential integrity)
- Email, phone, address → null
- DOB, SSN → null
- Profile photo deleted from storage
- Uploaded ID/W-9/etc. files deleted from storage (file rows kept with anonymized filenames)
- `is_anonymized = true`
- Invoices and timesheets referencing this person continue to resolve to the anonymized name

See ADR-0010 for full anonymization rules.

## Primary recruiter (contractors only)

Each contractor has a `primary_recruiter_id` field on their `people` record. This is the recruiter who "owns" the contractor for roster-count and accountability purposes.

```
people
  ...
  primary_recruiter_id (FK to people, nullable — set when applicant promoted to contractor)
```

**Set when:**
- Applicant is promoted to contractor — defaults to the recruiter assigned to the contractor's first property
- Updated only on **permanent transfer** that changes recruiter (per ADR-0019)

**NOT changed by:**
- Temporary assignments (contractor visits another recruiter's property for a defined period; primary recruiter stays)
- Position changes at same property (recruiter is the same)
- Property change without recruiter change

**Used for:**
- Recruiter roster count: "How many contractors does Jane have?" filters by `primary_recruiter_id`
- Ownership for routing pay-increase requests, terminations, transfers
- "Whose contractor is this?" — always the primary recruiter

A contractor can have multiple concurrent work_orders at multiple properties (e.g. during a temp assignment), but only ONE primary recruiter at a time. See ADR-0019.

## PTO eligibility (W-2 only)

W-2 staff (recruiters, office manager, front desk, HR, payroll, w2_employee, admin, super_admin) accrue PTO via a tier-based system tied to hire date. Contractors do NOT get PTO.

The `hire_date` field on `people` is the anchor for tier calculations:

- Day 1-30: probation (0 hours)
- Day 30+: tier 1 (50% benefit)
- 6 months+: tier 2 (75% benefit)
- 12 months+: tier 3 (100% benefit; stays here forever)

Tier transitions are automated by a daily scheduled job. PTO years run hire-anniversary to hire-anniversary with no rollover. Full domain model in `20-domain/pto.md`. Policy in ADR-0016.

## Related

- ADR-0004 — One people table with status
- ADR-0010 — Legal hold on PII
- ADR-0016 — PTO tier-based accrual policy
- `10-architecture/identity-and-auth.md` — login + roles
- `10-architecture/permissions-matrix.md` — `people.*` permissions
- `20-domain/workflows.md` — promotion, termination, transfer workflows
- `20-domain/pto.md` — PTO domain model (W-2 staff only)
- `30-schema/people-tables.md` — column-level detail (TBD)
- `40-flows/applicant-to-contractor.md` — promotion flow
- `40-flows/pto-request.md` — PTO request lifecycle
