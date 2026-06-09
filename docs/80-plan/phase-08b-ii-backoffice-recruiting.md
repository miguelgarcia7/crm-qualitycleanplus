# Phase 08b-ii — Back-office recruiting

| Field | Value |
|---|---|
| Status | 🚧 In progress |
| Last updated | 2026-06-09 |
| Owner | Engineering |

## Goal

The internal half of recruiting (08b-i shipped the public half): staff manage **job
postings** (create/publish/close), work the **applicant review queue**, complete the
**onboarding checklist** with real document uploads, and **promote** an applicant to
contractor (reversibly, until work orders exist). Scope decision: **applicants only** —
no contractor/staff directory pages in this slice; **full uploads** — checklist items
store documents via the polymorphic `File` model, not just checkboxes.

## Locked rules (people-lifecycle.md, ADR-0013)

- Checklist items (v1, hardcoded): ID front, ID back, I-9 (+ HR verification), W-9,
  signed contractor agreement, background check (status). Uniform is tracked through
  inventory, not here.
- `front_desk` marks checklist items (`people.applicants.onboarding_checklist.edit`)
  but has no broader applicant edit access. HR may also **waive** items.
- Promotion requires every non-waived item complete; sets `status=contractor_active`,
  `converted_to_contractor_at` (first time only — immutable after), contractor role,
  and `primary_recruiter_id` (the promoting recruiter).
- **Reversal** allowed only while the person has **no work orders**: status back to
  `applicant`, `converted_to_contractor_at` cleared, audit-logged. Promoter can reverse
  their own promotion; super_admin anyone.
- `application_date` never changes (model-enforced since Phase 01).

## Schema

- `people` += onboarding columns: `{id_front,id_back,i9,w9,contractor_agreement}_file_id`
  + `*_uploaded_at`; `i9_verified_by`/`i9_verified_at`; `contractor_agreement_signed_at`
  (alias of its uploaded_at semantics — one timestamp); `background_check_status`
  (`not_required|pending|passed|failed`) + `background_check_completed_at`;
  `onboarding_waived_items` json (item keys waived by HR).
- `job_applications` += `reviewed_by`, `reviewed_at`, `rejected_reason`.
- New permission: `job_postings.manage` (admin, office_manager, hr, recruiter).

## Increments

0. **Infra** — this doc; the two migrations; `BackgroundCheckStatus` enum;
   `OnboardingChecklist` support class (item definitions + per-person status +
   completion check); permission seed; `JobPostingPolicy` + `JobApplicationPolicy`
   (+ registration); model fillable/casts updates.
1. **Job postings management** — `JobPostingController` (index/store/update/publish/
   close/destroy; slug auto-generated, unique); `backoffice.job-postings.*`;
   `views/admin/job-postings/index.tsx`; sidebar "Job Postings".
2. **Applicants queue + detail** — `ApplicantController@index` (status-filtered queue)
   + `@show` (application + attestations + person + other applications); start-review
   + reject (reason); `views/admin/applicants/{index,show}.tsx`; sidebar "Applicants".
3. **Onboarding + promotion** — per-item upload/download endpoints; verify I-9;
   background-check status; HR waive/unwaive; `PromoteApplicantToContractor` +
   `ReversePromotion`; checklist UI on the show page; dashboard "Applications to
   review" + front-desk "Onboarding docs pending".
4. **Tests + docs + seed** — Pest; docs (recruiting.md, permissions matrix, roadmap,
   this doc as-built); seed a reviewing applicant with a partially-complete checklist.

## Out of scope / deferred

Contractor/staff directory pages; configurable checklist items; applicant
notifications/emails; resume upload on the public form; KB (08c).

## Related

`20-domain/people-lifecycle.md` (checklist + promotion rules) · `20-domain/recruiting.md` ·
ADR-0013 (front-desk role) · `phase-08b-i-marketing-applications.md` · `80-plan/roadmap.md`.
