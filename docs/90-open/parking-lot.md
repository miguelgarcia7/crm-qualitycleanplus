# Parking Lot

| Field | Value |
|---|---|
| Status | Living document — items added as they surface, removed when resolved |
| Last updated | 2026-08-30 |
| Owner | Product |

Items we've identified but explicitly deferred. Each one has a context note so future-us (or a new session) can pick it up cold.

## Gas / mileage deductions

**Context:** Email 3 noted that gas deductions are dynamic and tied to actual worked schedules. The client confirmed these can remain inside the timekeeping/payroll system — they don't need to live in the CRM/back-office. We deferred design.

**Open questions:**
- Where exactly do they live? On time entries? Separate `mileage` table?
- How are they computed? Per-mile? Per-trip? Per-day?
- Who enters them? Contractor? Recruiter? Auto from route data?
- Are they billable or only deductions?

**Decision needed by:** Phase 03 (time tracking) at the earliest; could defer to Phase 04 (workflows) or later if not blocking.

## ~~Receptionist role~~ (resolved differently — added as Front Desk)

Initially deferred ("office_manager handles all"), then reopened during a later design conversation when the role's scope became clearer.

**Final resolution (2026-05-21):** Added as `front_desk` role per ADR-0013. Front Desk owns supply request fulfillment, uniform issuance, onboarding document receipt, stock receipt/corrections, PO creation, and physical-task workflow steps. Office Manager retains all the same permissions as fallback. See ADR-0013 for the full role definition.

## Multi-step approval chains

**Context:** ADR-0011 and the workflow engine support multi-step approvals natively. None of our day-one workflows use it. Future scenarios that might:
- Pay-increase approvals routed through office manager → ownership for large increases
- Contract renewals requiring multiple sign-offs
- Termination of high-tenure contractors requiring HR + recruiter + ownership

**Decision needed when:** A specific workflow is identified as needing multi-step. Engine is ready.

## Multi-tenancy resurrection

**Context:** ADR-0002 explicitly drops multi-tenancy. If the business pivots back toward selling QC Minute as SaaS, retrofitting is a real but bounded project.

**Action:** If/when business direction changes, write a new ADR superseding 0002 and plan the migration. Estimated 3-6 months of work at that point.

## ~~Applicant-to-contractor promotion flow~~ (resolved — built)

**Resolved:** built in Phase 08b. `PromoteApplicantToContractor` transitions
`applicant` → `contractor_active`, sets `converted_to_contractor_at` and the
primary recruiter, and is gated on `OnboardingChecklist::isComplete()` (ID
front/back, I-9, W-9, contractor agreement, background check — each waivable).
`ReversePromotion` undoes it. UI at `/admin/applicants/{id}`.

The open questions below were answered by the build; kept for context.

**Original context:** The workflow exists in the catalog and is part of Phase 4/8 build, but a detailed step-by-step flow doc hasn't been written.

**What we know (from `20-domain/people-lifecycle.md`):**

- Applicant fills out the form on the public site
- Recruiter / HR reviews
- Front Desk marks onboarding checklist items as docs physically arrive
- Promotion is a status transition from `applicant` → `contractor_active`
- Application date is preserved forever
- Reversible while no work orders attached

**What's open:**

- Detailed form for the promotion step (what fields, what validation)
- Notification choreography during onboarding
- What happens if a required checklist item is skipped
- Integration with hire date / W-2 onboarding (if person is being hired as W-2 staff instead)

**Status:** RESOLVED — built in Phase 08b (see the note at the top of this entry).

## Recruiter-to-property transfer workflow

**Context:** Bulk reassignment of all properties owned by Recruiter A to Recruiter B (e.g. when Recruiter A leaves or moves to a different role).

**What we know:**

- Initiated by Office Manager or Super Admin
- Reassigns property → recruiter relationships for all properties owned
- Updates `primary_recruiter_id` on all affected contractors (since their primary recruiter is now Recruiter B)
- Pending workflows initiated by Recruiter A reassigned to Recruiter B
- Activity log captures the bulk action

**What's open:**

- UI shape (one-click bulk or per-property confirmation?)
- What happens to in-flight pay-increase / more-staff requests assigned to Recruiter A
- Edge cases (Recruiter B already overloaded?)
- Should this also handle the W-2 termination of Recruiter A in one workflow, or are these separate?

**Status:** Deliberately deferred to revisit. Likely Phase 4 work.

## ~~Service requests for office supplies~~ (resolved — now in scope for v1)

Resolved 2026-05-21: office supplies are part of the **unified inventory system** alongside uniforms and equipment. See ADR-0012 and `20-domain/inventory.md`.

The "request button on everyone's dashboard" is implemented as the `supply_request` workflow (see `40-flows/supply-request.md`). Phase 04 of the roadmap covers this.

## ~~Uniform deduction across rehire~~ (resolved)

Resolved 2026-05-21:

- Schedule is settled at termination — either via "Returned" path (cancelled) or via "accelerated to final paycheck"
- Termination triggers final-check determination (mid-week → current open period; post-close → just-closed period with super_admin reopen or void/reissue path)
- **Cap-at-zero rule:** if final paycheck cannot cover the balance, deduction caps at available pay; uncovered remainder is logged as expense against QCP. No negative check, no collection pursuit
- Rehired contractor starts fresh with no carried balance (all prior schedules are terminal)

Captured in `20-domain/inventory.md` under "Termination interaction → Determining the final paycheck" and "Cap-at-zero rule."

## Contract expiration with no successor

**Context:** When a property's contract expires and no replacement is uploaded, what happens?

**Earlier client answer:** "Keep generating invoices but flag loudly." We have this as the working assumption.

**Open question for confirmation:**
- Is the flag a dashboard warning, or does it appear on every invoice generated against the expired contract?
- Should there be a hard cap (e.g. after 60 days post-expiration, block new work orders)?

**Decision needed by:** Phase 02 (Property Bible) or Phase 03 (work orders).

## Per-line timesheet decline

**Context:** ADR-0007 alternative B. PM declines whole timesheet with reason today. If usage shows PMs frequently object to one or two specific contractor rows, we could add per-line decline.

**Decision needed when:** Usage data accumulates. Not v1.

## Tax document delivery

**Context:** Contractors don't see tax documents per current scope (per the contractor-login clarification). But QCP still has to deliver 1099s annually.

**Open questions:**
- How are 1099s generated (current process)?
- Should the new system surface them (mailed, emailed, downloadable from contractor portal)?
- Is this in scope for v1 or v2?

**Current default:** Out of scope for v1. Existing process continues. Revisit in v2.

## Two-factor authentication — partially built

**Context:** No longer "not in v1". Fortify's TOTP feature is enabled in
`config/fortify.php` with `confirm` + `confirmPassword`, all nine
`/user/two-factor-*` routes are registered, `Person` uses
`TwoFactorAuthenticatable`, and the columns exist. The challenge and
confirm-password screens were built with the rest of the auth pages.

No external service is involved: `pragmarx/google2fa` generates and validates
codes, `bacon/bacon-qr-code` renders the enrollment QR — both already
installed. The second factor is the user's own authenticator app.

**What's missing:** the enrollment panel on the profile settings page (enable →
QR → confirm code → recovery codes, plus regenerate and disable). Until that
exists 2FA is dormant, since it is opt-in per user and nobody can opt in.

**Decision needed when:** Whenever someone wants it on. Roughly half a day
against endpoints that already exist.

## Native push notifications

**Context:** Browser-only for v1. Push requires either a native app or service worker setup.

**Decision needed when:** Recruiters or PMs report missing notifications often. Not v1.

## Cross-domain super-admin switching

**Context:** Super admin can log in to either domain. We've planned a "Switch domain" link in the nav. Not yet specified exactly how the UX works.

**Decision needed by:** Phase 06 (Dashboards).

## Adjustment template categorization

**Context:** As `adjustment_items` accumulates, the catalog may need grouping (e.g. "Performance Bonuses" / "Equipment / Tools" / "Disciplinary"). Currently flat.

**Decision needed when:** Catalog grows beyond ~10 items.

## Live grid for import-only properties

**Context:** PMs at import-only hotels don't log into QC Minute (no PM in the system). But what if they want to? Should we let them?

**Currently:** No login surface for import-only PMs.

**Decision needed when:** A specific import-only property requests visibility.

## Audit log search performance

**Context:** Activity log grows fast. After a year, full-table scans for "show all activity by user X" will slow down.

**Decision needed by:** Year 2 (or when query times become noticeable). Solutions: partitioning, archiving to cold storage, indexed search via Meilisearch / similar.

## Reports subscriptions

**Context:** Scheduled email of reports (e.g. "weekly revenue report every Monday to ownership"). Mentioned as a possible v1 feature in Phase 09. May defer.

**Decision needed by:** Phase 09.

---

## How to use this file

- Add an item when you spot something deferred. Include enough context that a fresh reader can understand it.
- Remove or move items when they get resolved (move to an ADR or a domain file).
- Review during phase planning — some items become blockers for specific phases.
