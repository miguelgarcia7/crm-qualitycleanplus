# Recruiting (Job Board + Applications)

| Field | Value |
|---|---|
| Status | Accepted (08b-i + 08b-ii built) |
| Last updated | 2026-06-09 |
| Owner | Product + Engineering |

How candidates discover openings and apply, and how those applications become
people in the system. The public surface lives on the marketing site
(`qualitycleanplus.com`, Blade — ADR-0023); the back-office management surface is
Phase 08b-ii.

## Identity model — applicant *is* a Person

Per ADR-0004 there is one `people` table. A public application creates (or links
to) a `Person` with `status = applicant`. When hired, that same row transitions
`applicant → contractor_active` (see `people-lifecycle.md`) — nothing is copied.

The intake data splits two ways (decided in Phase 08b-i):

- **Durable facts → `people`**: name, email, phone, date of birth, address,
  citizenship / work-eligibility, and emergency contact. These remain true once
  the applicant becomes a contractor.
- **The application event → `job_applications`**: which posting, desired
  position/pay/start date, and the **at-the-time legal attestations** (felony
  declaration, "worked here before", "with another agency", transportation) plus
  the consent acknowledgement. One immutable row per submission — the legal record
  of what was declared, when. Supports the same person applying more than once.

`SubmitApplication` matches an existing person by email (one identity per human)
and never overwrites their durable fields; with no email it creates a fresh
applicant with a placeholder `…@qcp.invalid` address.

## Job postings (the public board)

`job_postings` are advertised openings — **distinct** from the Property-Bible
`positions` job-title catalog (the legacy table was confusingly named `positions`).
A posting has a status (`draft` / `published` / `closed`; only `published` is
public), a title, pay range, free-text content, optional hours, and a location
(either a linked `property_id` or a free-text `location_label`). `slug` is the
public route key. The job board lists published postings; each links to the
application form pre-filled for that posting.

## Surfaces

- **Public (Blade, `qualitycleanplus.com`)**: `/job-openings` (board),
  `/application` + `/application/{posting}` (form), `/application/thank-you`.
  Contact forms (`/contact-us/job-seekers`, `/contact-us/business-inquiries`)
  record `ContactInquiry` leads (Marketing context).
- **Back office (08b-ii, built)**: `/admin/job-postings` (create-as-draft / edit /
  publish / close; delete only while no applications) and `/admin/applicants` —
  the status-filtered review queue + detail page with start-review / reject
  (reason), the onboarding checklist, and promote/reverse.

## Onboarding checklist + promotion (08b-ii)

The v1 checklist is hardcoded in `OnboardingChecklist` (people-lifecycle.md): ID
front/back, I-9 (upload **and** HR verification; re-upload clears verification),
W-9, signed contractor agreement, background check (`BackgroundCheckStatus`;
`not_required`/`passed` satisfy the gate). Documents are real uploads — polymorphic
`File` rows attached to the person, with per-item download (checklist-permission
gated; these are PII). HR/admin may **waive** items; front desk edits the checklist
but cannot waive (ADR-0013).

`PromoteApplicantToContractor` requires every non-waived item complete, then flips
the person to `contractor_active`, sets `converted_to_contractor_at` (first time
only), assigns the contractor role, and defaults `primary_recruiter_id` to the
promoting recruiter. `ReversePromotion` undoes a mistaken promotion **only while
no work orders exist** — promoter or super_admin only. Both are activity-logged
with old/new status.

## Related

- ADR-0023 — marketing site in-monorepo as a Blade surface
- ADR-0004 — one people table with status
- `20-domain/people-lifecycle.md` — applicant lifecycle, onboarding checklist, promotion
- `80-plan/phase-08b-i-marketing-applications.md` — the build
