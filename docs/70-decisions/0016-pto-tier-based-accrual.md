# ADR-0016: PTO — Tier-Based Accrual Policy

| Field | Value |
|---|---|
| Status | Accepted |
| Decided | 2026-05-21 |
| Owner | Product (David) + Engineering |
| Supersedes | — (resolves the open item `90-open/pto-system.md`) |
| Superseded by | — |

## Context

The legacy QCP CRM has PTO (three buckets: vacation, scheduled absence, unscheduled absence) but lacks a proper accrual policy. Balances are entered manually by HR. There's no automated tier progression. Approval is informal.

The new system needs a real PTO policy with automated accrual, structured approval routing, balance enforcement, and audit-clean cancellation handling.

## Decision

**Tenure-based tier accrual on hire anniversary cycles** with three separate buckets, automated tier transitions, submission-time balance enforcement, and Admin/HR approval routing with a self-approval guardrail for HR.

### Tier-based accrual

PTO eligibility is determined by tenure (continuous employment from hire date). The four tiers:

| Tenure | Vacation | Scheduled | Unscheduled | Total | % of full benefit |
|---|---|---|---|---|---|
| Day 1-30 (Probation) | 0 | 0 | 0 | 0 | 0% |
| 30 days completed | 20 | 20 | 20 | 60 | 50% |
| 6 months completed | 30 | 30 | 30 | 90 | 75% |
| 12 months completed | 40 | 40 | 40 | 120 | 100% |

After 12 months, the tier stays at 100% for the duration of employment. No further growth.

### Cycle: hire anniversary

PTO years run from hire anniversary to hire anniversary, not calendar year. Each anniversary refreshes the allotment to the current tier amount.

### No rollover

Use it or lose it. Unused hours at anniversary are forfeited. Fresh allotment is granted for the new year.

### Tier crossings mid-year (top up by delta)

When an employee crosses a tier threshold mid-year, the current year's allotment is **topped up by the delta**, not replaced.

Example: Jane crosses 6-month mark on December 1. Before: allotment 20/20/20 (50% tier). After: 30/30/30 (75% tier). The 10-hour delta per bucket is added to her current year's allotment. Any hours she's already used stay used; remaining available balance grows by the delta.

This is fairer than replace — no penalty for taking PTO before milestone.

### Strict bucket separation

Each bucket (vacation / scheduled / unscheduled) is a separate pool. A vacation request can only debit vacation hours. No substitution between buckets, even by manager override.

### Submission-time balance check

When an employee submits a PTO request, the system blocks if requested hours exceed available balance for that bucket. No "submit and warn" — block at submission.

### Deduct on submission, not just approval

Available balance reflects pending requests too. Formula:

```
available = allotment − SUM(hours from requests where status IN ('pending', 'approved'))
```

So if Jane has 30 vacation hours and submits 20 hours pending, her available becomes 10. She can't double-book by submitting another 20 hours before the first is decided.

If a pending request is rejected or cancelled, those hours return to available.

The UI shows both numbers clearly:
```
Vacation: 10 hours available  (20 hours pending approval, of 30 total allotted)
```

### Approval routing: Admin and HR

`workflows.pto.approve` permission is granted to: `super_admin`, `admin`, `hr`. Other roles (office_manager, payroll, recruiter) do NOT approve PTO.

**Self-approval guardrail:** HR cannot approve their own PTO requests. The policy enforces:

- If the requester's `person_id` equals the approver's `person_id` AND the approver has role `hr` (but not also `admin` or `super_admin`), the approve action is denied.
- HR's own PTO requests route to Admin (and super_admin).

Admin and Super Admin **can self-approve.** They're at the top of the chain.

### Cancellation

A PTO request can be cancelled by:

- The requester themselves (any status: pending, approved)
- An approver (admin, hr — same self-approval rule applies)
- Super Admin

On cancellation, the request status becomes `cancelled`. The hours return to available immediately. Audit trail captures who cancelled and when.

### Notice period vacation — soft warning

If a person has an active termination workflow in flight, submitting a vacation request shows a warning at both submission and approval:

> "This person has a termination in progress (initiated [date], scheduled effective [date]). Vacation during notice period is generally not permitted to ensure smooth transition."

The system does NOT block — just surfaces the policy. Approver exercises judgment.

### Termination: no payout

Per QCP policy:

- **Accrued but unused vacation is NOT paid out** on separation
- **Notice-period vacation requests** generally denied (per soft warning above)
- All unused PTO at termination is forfeited

### Automation

A daily scheduled job `ProcessPtoTenureCrossings` runs:

- For every active W-2 person, check if they crossed a tier threshold today (Day 30, 6-month, 12-month) OR if today is their hire anniversary
- On tier crossing: create a `pto_grant` with the delta, update current `pto_year_allotment`
- On anniversary: close current year's allotment (forfeit unused), open new year allotment at current tier

## Consequences

### Positive

- Predictable, fair accrual aligned with QCP's stated policy
- Self-approval guardrail keeps HR compliance defensible
- Submission-time balance check + pending-hours deduction prevents over-allocation cleanly
- Strict bucket separation matches operational meaning (vacation ≠ sick day ≠ planned absence)
- Audit trail captures every grant, request, approval, cancellation
- Tier crossing automation removes manual HR work

### Negative

- Notice-period vacation handling is policy-only (no hard block) — relies on manager judgment
- HR-on-vacation-and-no-Admin scenario: HR cannot self-approve, so they need Admin (or Super Admin) available. Rare but possible bottleneck.
- The accrual job needs to be reliable — a missed tier crossing means an employee can't request PTO they're entitled to. (Mitigation: rerunnable, idempotent; surface failures.)

### Implementation requirements

Schema:

```
pto_year_allotments
  - id, person_id, year_start, year_end
  - tier_at_year_start (enum: probation | tier_1 | tier_2 | tier_3)
  - vacation_allotment, scheduled_allotment, unscheduled_allotment (hours, decimal)
  - status (enum: open | closed | forfeited)
  - closed_at, forfeited_hours_at_close (jsonb capturing what was lost)
  - created_at, updated_at
  - UNIQUE(person_id, year_start)

pto_grants
  - id, person_id, year_allotment_id (FK)
  - grant_type (enum: tier_milestone | annual_refresh | manual_adjustment)
  - vacation_hours, scheduled_hours, unscheduled_hours (delta, decimal)
  - reason (text)
  - effective_date
  - created_by (nullable for system grants)
  - created_at

pto_requests
  - id, person_id, year_allotment_id
  - bucket (enum: vacation | scheduled | unscheduled)
  - start_date, end_date, hours (decimal)
  - reason (text)
  - status (enum: pending | approved | rejected | cancelled)
  - submitted_at, approved_at, approved_by, rejected_at, rejected_by, reject_reason
  - cancelled_at, cancelled_by, cancel_reason
  - is_self_approved (bool, default false — flagged for HR self-approval edge cases; admin/super_admin self-approval also flagged)
  - notice_period_warning_acknowledged (bool, default false)
  - created_at, updated_at
```

Permissions:

- `workflows.pto.approve` revised: super_admin, admin, hr ONLY
- Self-approval policy enforced in `PtoRequestPolicy::approve()`

UI:

- Submission form shows live balance: "Available: 10 / Pending: 20 / Allotted: 30" per bucket
- Approval queue: pending requests for the approver's view
- "My PTO" dashboard widget for every W-2 person showing balances + history

Background jobs:

- `ProcessPtoTenureCrossings` — daily, handles tier transitions + anniversary refreshes
- `ProcessPtoYearClose` — invoked by ProcessPtoTenureCrossings on anniversary

## Alternatives considered

### A. Calendar-year cycle (Jan 1 reset)

Rejected. Tier crossings are hire-date-based; mixing calendar-year refresh with hire-date tiers creates confusing edge cases (someone hired in November would have weird first-year mechanics).

### B. Allow rollover (cap or full)

Rejected per David's policy direction: use it or lose it.

### C. Continue growth past 12 months (e.g. 5+ years → 50/50/50)

Rejected per David's policy direction: 100% tier is the ceiling.

### D. Allow HR self-approval silently (no guardrail)

Rejected. Compliance posture suffers. Routing HR's own requests to Admin is a small friction with significant audit/governance benefit.

### E. Block notice-period vacation hard

Rejected. "Generally not permitted" implies manager judgment. Soft warning surfaces the policy at the right moment without removing the option entirely.

## Related

- `20-domain/pto.md` — full domain model
- `40-flows/pto-request.md` — step-by-step request lifecycle
- `20-domain/workflows.md` — PTO catalog entry
- `10-architecture/permissions-matrix.md` — pto.approve revised
- `90-open/pto-system.md` — original open file, now resolved by this ADR
