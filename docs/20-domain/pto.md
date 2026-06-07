# PTO (Paid Time Off)

| Field | Value |
|---|---|
| Status | Accepted |
| Last updated | 2026-05-21 |
| Owner | Product (David) + Engineering |

PTO is for **W-2 employees only** — recruiters, office manager, HR, payroll, front desk, and other W-2 staff. Contractors do not get PTO.

The system implements a **tenure-based tier accrual** policy with three separate buckets (vacation, scheduled, unscheduled), automated tier transitions, submission-time balance enforcement, and Admin/HR approval routing.

See ADR-0016 for the full policy decision.

## The three buckets

| Bucket | Operational meaning | Examples |
|---|---|---|
| **Vacation** | Planned recreational time off | Family trip, holiday week, sabbatical |
| **Scheduled** | Planned but non-recreational | Doctor appointment, court date, scheduled procedure |
| **Unscheduled** | Unplanned, often last-minute | Sick day, family emergency, car broke down |

Each bucket is a separate pool. **No substitution between buckets** — you can't use scheduled hours to cover a vacation request.

## Tier-based allotment by tenure

| Tenure | Vacation | Scheduled | Unscheduled | Total |
|---|---|---|---|---|
| Day 1-30 (Probation) | 0 | 0 | 0 | 0 |
| 30 days completed | 20 | 20 | 20 | 60 |
| 6 months completed | 30 | 30 | 30 | 90 |
| 12 months completed | 40 | 40 | 40 | 120 |

After 12 months, the 100% tier (40/40/40) stays in effect forever. No further growth.

## Cycle and accrual

### Hire anniversary cycle

PTO years run **hire anniversary to hire anniversary** (not calendar year). Each anniversary, the previous year's unused PTO is forfeited and a fresh allotment is granted at the current tier.

### Tier crossings mid-year

When an employee crosses a tier threshold (Day 30, 6 months, 12 months) mid-year, their current year's allotment is **topped up by the delta**:

Example: Jane completes her 6-month milestone on December 1.
- Before: allotment 20/20/20 (50% tier)
- After: allotment 30/30/30 (75% tier)
- Delta added: +10/+10/+10
- If Jane had used 5 vacation hours already, she had 15 available. After top-up: 25 available.

### No rollover

Unused hours at anniversary are forfeited. The new PTO year starts fresh at the current tier amount.

### Automation

A daily scheduled job (`ProcessPtoTenureCrossings`) runs and:

- For every active W-2 person, checks if today is a tier-crossing day OR a hire anniversary
- On tier crossing: creates a `pto_grant` with the delta, updates the `pto_year_allotment`
- On anniversary: closes the current year's allotment (forfeits unused), opens a new year at the current tier

## Data model

```
pto_year_allotments  (one per person per PTO year)
  - id, person_id, year_start, year_end
  - tier_at_year_start: enum (probation | tier_1 | tier_2 | tier_3)
  - vacation_allotment, scheduled_allotment, unscheduled_allotment (hours, decimal)
  - status: enum (open | closed | forfeited)
  - closed_at, forfeited_hours_at_close (jsonb)
  - created_at, updated_at
  - UNIQUE(person_id, year_start)

pto_grants  (audit log of every allotment change)
  - id, person_id, year_allotment_id (FK)
  - grant_type: enum (tier_milestone | annual_refresh | manual_adjustment)
  - vacation_hours, scheduled_hours, unscheduled_hours (delta, decimal)
  - reason (text)
  - effective_date
  - created_by (nullable — null for system grants)
  - created_at

pto_requests  (the actual time-off requests)
  - id, person_id, year_allotment_id (FK)
  - bucket: enum (vacation | scheduled | unscheduled)
  - start_date, end_date, hours (decimal)
  - reason (text)
  - status: enum (pending | approved | rejected | cancelled)
  - submitted_at
  - approved_at, approved_by
  - rejected_at, rejected_by, reject_reason
  - cancelled_at, cancelled_by, cancel_reason
  - is_self_approved (bool — flagged when approver == requester)
  - notice_period_warning_acknowledged (bool)
  - created_at, updated_at
```

## Available balance computation

Always computed on read, not stored:

```
For (person, bucket, current PTO year):
  allotment = pto_year_allotments.{bucket}_allotment
  pending = SUM(pto_requests.hours WHERE status='pending' AND bucket matches)
  used = SUM(pto_requests.hours WHERE status='approved' AND bucket matches)
  available = allotment - pending - used
```

The UI shows all three:

```
Vacation:  10 hours available  (20 pending, of 30 allotted)
Scheduled: 30 hours available  (0 pending, of 30 allotted)
Unscheduled: 25 hours available  (5 pending, of 30 allotted)
```

This way the employee understands both what they can request NOW (available) and what's tied up awaiting approval (pending).

## Submission flow

1. Employee submits PTO request via form:
   - Bucket (vacation / scheduled / unscheduled)
   - Start date, end date
   - Hours (must be ≤ available for that bucket)
   - Reason (text)
2. System validates: `requested_hours <= available`
   - If insufficient: **block at submission** with clear message ("You have 8 vacation hours available; this request is for 16")
3. If valid: `pto_request` row created (status=pending)
4. Available balance updates immediately (pending hours deducted)
5. Notification routed to approval queue (admin, super_admin, hr; or admin/super_admin only if requester is hr)

## Approval routing

The `workflows.pto.approve` permission is granted to: `super_admin`, `admin`, `hr`.

**Self-approval guardrail:** HR cannot approve their own PTO requests.

Routing logic:

- If the requester is NOT `hr`: request appears in queue for admin, super_admin, hr (any can approve)
- If the requester IS `hr` (and not also admin/super_admin): request appears in queue for admin and super_admin only

Admin and Super Admin **can self-approve** (they're at the top of the chain). When they do, `is_self_approved` is flagged on the request for audit visibility.

## Cancellation

A PTO request can be cancelled by:

- The requester (any status — even after approval)
- An approver (admin, super_admin, hr — subject to same self-approval rules)

On cancellation:

- Status moves to `cancelled`
- Hours immediately return to available balance
- Audit trail captures who, when, why

## Notice period vacation — soft warning

When a person has an active termination workflow in flight, the system shows a warning at both submission and approval:

> "This person has a termination in progress. Vacation during notice period is generally not permitted to ensure smooth transition."

The warning is informational. System does NOT block — manager exercises judgment. If they approve anyway, `notice_period_warning_acknowledged` is set to true.

## Termination behavior

Per QCP policy:

- Accrued but unused PTO at termination is **forfeited** (no payout)
- Notice-period vacation requests typically denied (per soft warning above)
- On termination, the person's open `pto_year_allotment` status moves to `forfeited`
- All pending PTO requests are auto-cancelled

## Permissions

| Permission | Roles |
|---|---|
| `workflows.pto.initiate` | All W-2 roles: super_admin, admin, office_manager, front_desk, hr, payroll, recruiter, w2_employee |
| `workflows.pto.approve` | super_admin, admin, hr (with self-approval blocked for hr) |
| `workflows.pto.cancel_own` | Same as initiate (anyone can cancel their own) |
| `workflows.pto.cancel_others` | super_admin, admin, hr (with self-approval rules for hr) |
| `pto.balances.view_all` | super_admin, admin, hr, payroll |
| `pto.balances.adjust_manual` | super_admin, admin (creates a manual_adjustment grant) |
| `pto.balances.view_own` | All W-2 roles |

See `10-architecture/permissions-matrix.md` for the full matrix.

## UI surfaces

### W-2 employee dashboard

- "My PTO" widget showing available/pending/allotted per bucket
- "Submit PTO Request" button
- "My Recent Requests" list (5 most recent with status)

### Approver dashboard (admin, hr, super_admin)

- "Pending PTO Approvals" widget
- "PTO Requests From My Team" (informational — past + upcoming)

### HR/Admin tools

- "All PTO Balances" view — search by person, see balances + history
- "PTO Year Reports" — usage stats per bucket, per role, per year
- "Manual Balance Adjustment" form (for one-offs — e.g. correcting a system error, granting a bonus week)

## Edge cases

| Case | Behavior |
|---|---|
| Submit a PTO request for past dates | Allowed (with HR-only adjustment flow) — sometimes hours need to be logged retroactively |
| Request spans tier crossing | Use the tier active on the request's start_date for balance check |
| Request spans anniversary | Block — must split into two requests, one per PTO year |
| Half-days, partial hours | Supported — `hours` is decimal |
| HR-on-vacation-and-no-Admin available | Bottleneck — Super Admin can step in if needed |
| Person terminated with pending PTO | All pending requests auto-cancelled on termination |
| Negative balance through manual adjustment | Allowed (admin can set if needed) — surfaces with warning indicator |

## Related

- ADR-0016 — PTO tier-based accrual policy (full decision document)
- `20-domain/workflows.md` — PTO catalog entry
- `20-domain/people-lifecycle.md` — W-2 vs contractor distinction
- `40-flows/pto-request.md` — step-by-step lifecycle
- `10-architecture/permissions-matrix.md` — PTO permission rows
