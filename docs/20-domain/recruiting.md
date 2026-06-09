# Recruiting (Job Board + Applications)

| Field | Value |
|---|---|
| Status | Accepted (08b-i built; 08b-ii pending) |
| Last updated | 2026-06-08 |
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
- **Back office (08b-ii, pending)**: job-posting CRUD/publish, applicant review
  queue, promote-to-contractor + onboarding checklist. `people.applicants.*`
  permissions are already seeded; `job_postings.*` permissions land in 08b-ii.

## Related

- ADR-0023 — marketing site in-monorepo as a Blade surface
- ADR-0004 — one people table with status
- `20-domain/people-lifecycle.md` — applicant lifecycle, onboarding checklist, promotion
- `80-plan/phase-08b-i-marketing-applications.md` — the build
