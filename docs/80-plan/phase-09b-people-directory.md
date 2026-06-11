# Phase 09b — People directory + person profiles

**Status: ✅ done (as built)**

## Goal

Retire the last meaningful "Soon" placeholder: a browsable People section — contractor/staff
directory plus a person profile page — tying together data every other phase already produces
(work orders, hours, adjustments, PTO, activity log, avatars).

Applicants are explicitly out of scope: they already have a full section (Phase 08b-ii) behind
the `people.applicants.*` permission family.

## Authorization (permissions-matrix.md §People — seeded since Phase 01, first consumed here)

- `people.contractors.view` — super_admin, admin, office_manager, front_desk, hr, payroll see
  ALL contractors; **recruiters see only their own** (`primary_recruiter_id`, ADR-0019).
  property_manager's "(own)" grant is moot on this surface (PMs cannot enter the back office).
- `people.staff.view` — super_admin, admin, office_manager, hr only. Roles without it (payroll,
  front_desk, recruiter) get no Staff tab.
- Row scoping enforced by `PersonPolicy` (`App\Domain\People\Policies`), registered on `Person`.
  "(own)" cells grant the base permission; the policy adds the row filter — same pattern as
  `PropertyPolicy::GLOBAL_ROLES`.

## Surfaces

- `/admin/people` — directory. Tabs **Contractors** (default) / **Staff**, each independently
  gated. Search by name/email/phone, status filter. Contractors tab covers the whole contractor
  lifecycle (active, inactive, pending termination, terminated); Staff = `status->isStaff()`.
- `/admin/people/{person}` — profile in the HRM identity-card layout (as property detail and
  My Profile): identity card (photo/initials avatar, lifecycle status, roles, contact, hire
  date, primary recruiter) + permission-gated tabs:
  - **Work Orders** — always (anyone who can view the person); pay/bill rates only with
    `bible.rates.view`.
  - **Hours** — recent weekly time summaries; requires `timesheets.view_history`.
  - **Adjustments & Charges** — requires `time_entries.add_adjustment` (the role set that
    manages them; no separate view permission exists).
  - **PTO** — staff only, requires `pto.balances.view_all`.
  - **History** — activity log entries for the person; requires `audit.activity_log.view`.
- Sidebar "People" entry goes live (gated `people.contractors.view`, as scaffolded in Phase 01).
- Person names across existing screens (property Team tab, work orders) link into the profile.

## Increments

0. ✅ PersonPolicy + registration + this doc
1. ✅ Directory: PeopleController@index, routes, UI, sidebar
2. ✅ Profile: PeopleController@show, identity card + tabs UI
3. ✅ Link-ups (work-orders contractor names, property Team tab), Pest coverage, docs sync

## As built notes

- PTO tab reads the existing open `PtoYearAllotment` only — a profile view never materializes
  one (no side effects on GET); no open year → no tab.
- Hours tab = last 12 `time_summaries` weeks, minutes only — money stays in Reports (payouts
  report is the gated money view).
- `TimeEntryAdjustment::adjustmentItem()` relation added (was missing; profile needed it).

## Out of scope / deferred

Editing from the profile (contractor edits keep flowing through existing workflows: pay
increases, transfers, terminations, info changes); onboarding-document downloads on the profile
(stay on the applicant record); avatar upload for OTHERS (self-serve only, Phase 09a-adjacent
"My Profile" work); a Workflows-tab aggregation (candidate for the unified workflow inbox if it
ever lands).
