# Phase 08b-i — Marketing site + public job board + application

| Field | Value |
|---|---|
| Status | 🚧 In progress |
| Last updated | 2026-06-08 |
| Owner | Engineering |

## Goal

Bring the existing **marketing site** (`/Users/miguelgarcia/Code/www.qualitycleanplus.site`)
into this codebase as a server-rendered **Blade + Bootstrap** surface on
`qualitycleanplus.com` "/" (ADR-0023), with a **public job board** reading `job_postings`
and a **public application form** that creates a `Person(status=applicant)` plus a dated
`job_application` record. English-only; `/es/*` structured-for-later. Back-office
management of applicants/postings is **08b-ii**.

## Data model (hybrid — durable on the person, application as an event)

Durable facts live on `people` (still true once they become a contractor); each submission
is an immutable `job_applications` row (the legal record of what was declared, when, to
which posting).

- **`people`** += `dob`, `address`, `apartment_number`, `city`, `state`, `zip`,
  `usa_citizen`, `eligible_to_work`, `emergency_contact_name`, `emergency_contact_phone`,
  `emergency_contact_relationship`, `emergency_contact_address`. (`name` is composed from
  the submitted name parts; the raw parts are kept on the application.)
- **`job_postings`** (legacy `positions`, renamed to avoid the Property-Bible `positions`
  catalog clash): `status` (draft/published/closed), `title`, `slug` (route key),
  `pay_range`, `content`, `hour_start`, `hour_end`, `property_id` (nullable FK) +
  `location_label` fallback, `created_by`/`updated_by`, timestamps.
- **`job_applications`**: `person_id`, `job_posting_id` (nullable), submitted name parts,
  `desired_position`, `desired_salary`, `desired_start_date`, `transportation`,
  `work_at_qcp`(+explain), `another_staff_agency`, `non_complete`, `convicted_felon`
  (+explanation), `acknowledgement`, `status` (submitted/reviewing/promoted/rejected),
  `submitted_at`, timestamps.

## Naming collisions avoided

| Legacy | This app already has | Our name |
|---|---|---|
| `positions` (advertised openings) | `positions` = payroll job-title catalog | **`job_postings`** / `JobPosting` |
| `locations` | `properties` (Property Bible) | reference `property_id` (+ `location_label`) |
| `Applicant` model | `Person` with `status=applicant` (ADR-0004/0023) | no model — applicant **is** a Person |

## Increments

0. **Infra** — this doc; 3 migrations; enums `JobPostingStatus`/`JobApplicationStatus`;
   `app/Domain/Recruiting/Models/{JobPosting,JobApplication}` + factories; `Person`
   (new cols/casts, `jobApplications()`), `PersonStatus::isApplicant()`; Vite `site`
   bundle (`resources/css/site/app.scss` + `resources/js/site/app.js`, Bootstrap 5 via
   `bootstrap` + `sass`); static images → `resources/images/site/`.
1. **Marketing pages** — `Site/PageController`; port `site/layouts/{website,navigation,
   footer}` + `elements/*` + `pages/{home,services,about-us,contact-us(+business,
   +job-seekers)}`; `routes/marketing.php`; contact POST → `contact_inquiries`.
2. **Public job board** — `Site/JobBoardController` → `/job-openings` (published only) +
   single posting; `pages/job-openings.blade.php`; "Apply" → `/application/{posting}`.
3. **Application intake** — `Site/ApplicationController` (index/apply/store/thank-you);
   `StoreApplicationRequest` (legal-core validation mirrored from legacy);
   `Recruiting/Actions/SubmitApplication`; `pages/application(+_thankyou).blade.php`.
4. **Tests + docs + seed** — Pest feature tests; docs (`20-domain/recruiting.md`, this
   doc as-built, roadmap, Domain README); seed published postings + a sample application.

## Out of scope (08b-ii and later)

Back-office job-posting CRUD/publish; applicant review queue; promote-to-contractor +
onboarding checklist; `job_postings.*` permissions; Spanish `/es/*`; resume upload;
email notifications on new applications.

## Related

ADR-0023 (marketing in Blade); ADR-0004 (one people table); `20-domain/people-lifecycle.md`
(applicant lifecycle, onboarding checklist); `80-plan/roadmap.md`.
