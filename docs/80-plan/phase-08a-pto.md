# Phase 08a — PTO (tier-based accrual)

| Field | Value |
|---|---|
| Status | ✅ Done (Pest green — 10 PTO tests; Pint + Larastan clean, build + types clean, `migrate:fresh --seed` clean). |
| Last updated | 2026-06-08 |
| Owner | Engineering |

## As built (current state)

- **Schema/enums:** `pto_year_allotments` / `pto_grants` / `pto_requests`; enums `PtoBucket`,
  `PtoTier` (`hoursPerBucket`), `PtoRequestStatus`, `PtoAllotmentStatus`, `PtoGrantType`.
  Models + factories; `PtoYearAllotment::{allotmentFor,reservedFor,pendingFor,availableFor}`.
- **Logic:** `PtoTenure` (tier + hire-anniversary math); `EnsurePtoYear` (idempotent year open
  at current entitlement + annual_refresh grant); `SubmitPtoRequest` (staff-only, deduct-on-
  submission block); `Approve/Reject/CancelPtoRequest`; `AdjustPtoBalance` (manual grant);
  `ProcessPtoTenureCrossings` (tier top-up by delta + anniversary forfeit/refresh, idempotent).
  `PtoRequestPolicy` (HR self-approval guardrail; admin/super_admin self-approve flagged).
- **Surfaces:** `PtoController` (index/store/approve/reject/cancel/adjust) + `backoffice.pto.*`;
  `views/admin/pto/index.tsx` (3 bucket cards, request form w/ notice-period ack, my requests +
  cancel, approval queue, staff balances + adjust modal). Dashboard: "PTO available (vacation)"
  for staff + "PTO to approve" for approvers. Sidebar "Time Off".
- **Job:** `pto:process-crossings` command, scheduled daily (`routes/console.php`).
- **Tests:** `tests/Feature/PtoTest.php` (10). **Seed:** recruiter `hire_date` ≈14mo (tier_3) +
  open allotment + a pending vacation request.

## Context

W-2 staff PTO per ADR-0016: tenure-based tier accrual on hire-anniversary cycles, three
strictly-separate buckets, submission-time balance enforcement (deduct on submission),
Admin/HR approval with an HR self-approval guardrail, and a daily job for tier crossings +
anniversary refreshes. Deferred from Phase 04. Built as a **dedicated PTO module** (back
office only). First slice of Phase 08; Applicants/Job-postings (08b) and KB (08c) follow.

## Locked rules (ADR-0016)

Tiers (continuous tenure from `hire_date`): <30d probation 0 · ≥30d 20 · ≥6mo 30 · ≥12mo 40
(per bucket; tier_3 caps). Buckets vacation/scheduled/unscheduled, no substitution.
Hire-anniversary cycle, no rollover. Mid-year crossing tops up by delta. `available =
allotment − Σ(pending+approved)`; block over-balance at submission. Approve = super_admin/
admin/hr; **HR can't self-approve** (admin/super_admin can, flagged). Cancel by requester/
approver/super_admin returns hours. Notice-period vacation = soft warning. Eligibility = W-2
staff (`PersonStatus::isStaff()`).

## Increments

0. **Infra** — this doc; enums (PtoBucket/PtoTier/PtoRequestStatus/PtoAllotmentStatus/
   PtoGrantType); migrations (pto_year_allotments, pto_grants, pto_requests); models + factories.
1. **Logic** — `PtoTenure` (tier + anniversary), `EnsurePtoYear`, Submit/Approve/Reject/Cancel/
   AdjustPtoBalance actions, `PtoRequestPolicy` (self-approval guardrail), `ProcessPtoTenureCrossings`.
2. **Surfaces** — `PtoController` + Time Off page (balances/request/history/approval queue/adjust);
   dashboard My-PTO + PTO-to-approve; `pto:process-crossings` daily command; sidebar.
3. **Tests + docs + seed** — `PtoTest`; seed staff hire_date + allotment + pending request; gates;
   this doc + roadmap + Domain README (`Pto` context).

## Out of scope / deferred

Applicants + job postings (08b); KB (08c); PTO notifications; deeper termination→forfeit hook;
ICS export. Permissions already seeded.

## Related

ADR-0016; `20-domain/pto.md`; `40-flows/pto-request.md`; `80-plan/roadmap.md`.
