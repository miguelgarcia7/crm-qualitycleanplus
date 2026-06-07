# Flow: PTO Request

| Field | Value |
|---|---|
| Status | Accepted |
| Last updated | 2026-05-21 |
| Owner | Product + Engineering |

End-to-end lifecycle of a PTO request for a W-2 employee. Submission → approval → completion, with cancellation and notice-period edge cases.

Related: ADR-0016, `20-domain/pto.md`.

## Actors

| Actor | Role |
|---|---|
| Requester | Any W-2 employee — recruiter, office_manager, front_desk, hr, payroll, w2_employee, admin, super_admin |
| Approver | Admin, HR (with self-approval guardrail), Super Admin |
| System | Automated tier crossings, anniversary rollovers, balance computations |

## Pre-conditions

- Person is W-2 staff (not a contractor — contractors don't get PTO)
- Person has at least one open `pto_year_allotment` (created automatically when they pass probation)
- Requester has `workflows.pto.initiate` permission

## Path 1: Standard request (W-2 employee, not HR)

```
Employee (Back Office → My PTO)
  │
  ▼
Sees current balances:
  Vacation:    10 hours available  (20 pending, of 30 allotted)
  Scheduled:   30 hours available  (0 pending, of 30 allotted)
  Unscheduled: 25 hours available  (5 pending, of 30 allotted)
  
  Clicks "Request PTO"
  │
  ▼
Form:
  - Bucket (vacation / scheduled / unscheduled)
  - Start date, end date (date picker)
  - Hours (decimal, validated against available)
  - Reason (text)
  
  System computes per-day breakdown if multi-day
  
  Live validation: "10 hours available — this request is for 8" (OK)
                  or "10 hours available — this request is for 16" (BLOCK)
  
  Submit
  │
  ▼
Server:
  - Validates: requested ≤ available
  - If invalid: rejects with clear message
  - If valid:
      pto_request row created (status=pending)
      submitted_at = now
      Available balance updates (pending hours now deducted)
      Activity log entry
  
  Routing:
      If requester is hr → notification to admin, super_admin
      Otherwise → notification to admin, super_admin, hr
  │
  ▼
Requester sees confirmation: "Request submitted (PTO-####). Awaiting approval."
```

## Path 2: HR requests for themselves (self-approval guardrail)

Same as Path 1, but routing differs:

```
Server detects: requester.id ∈ persons with role 'hr' (and not also admin)
  → Notification routed ONLY to admin + super_admin
  → HR users do NOT see this in their queue
```

The HR person who submitted cannot approve their own request. Must wait for Admin.

## Path 3: Approver reviews

```
Approver (Back Office → Pending PTO Approvals)
  │
  ▼
Sees queue:
  | Request ID | Person | Bucket | Dates | Hours | Status |
  
  If person has active termination workflow:
    Row shows a warning icon ⚠️
  
  Click row → detail view
  │
  ▼
Detail view shows:
  - Full request info (person, bucket, dates, hours, reason)
  - Current available balance for that bucket
  - Year's usage history for that bucket
  - If person has active termination: warning banner
    "This person has a termination in progress (initiated [date], scheduled effective [date]). 
    Vacation during notice period is generally not permitted to ensure smooth transition."
  
  Two action options:
  
  Option A — Approve:
    Server:
      pto_request.status = approved
      approved_at = now, approved_by = user.id
      If user.id == request.person_id: is_self_approved = true (for audit)
      If notice period warning was shown: notice_period_warning_acknowledged = true
      Activity log entry
      Notification → requester: "PTO request approved"
  
  Option B — Reject:
    Modal asks for reject_reason (required text)
    Server:
      pto_request.status = rejected
      rejected_at = now, rejected_by = user.id, reject_reason = text
      Available balance updates (pending hours return)
      Activity log entry
      Notification → requester with reason
```

## Path 4: Cancellation

A request can be cancelled at any status by the requester, an approver, or super_admin.

```
Requester or Approver → request detail → "Cancel Request"
  Modal asks for cancel_reason (optional)
  Submit
  
  Server:
    pto_request.status = cancelled
    cancelled_at = now, cancelled_by = user.id, cancel_reason = text
    Available balance updates immediately (hours return to available)
    Activity log
    Notifications:
      If cancelled by requester: notify approvers ("cancelled before approval" or "cancelled approved request")
      If cancelled by approver: notify requester ("Your PTO was cancelled")
```

Cancellation rules:
- HR cannot cancel another HR person's own request (self-approval guardrail applies)
- Admin and Super Admin can cancel anything

## Path 5: Tier crossing or anniversary (automated)

```
Scheduled job: ProcessPtoTenureCrossings runs daily at 03:00 UTC
  │
  ▼
For every active W-2 person:
  Check today vs hire_date:
    
    If today == hire_date + 30 days → Day 30 tier crossing
      pto_grant created:
        grant_type = tier_milestone
        +20 vacation, +20 scheduled, +20 unscheduled (delta from 0 → 20)
        reason = "30-day probation completed (50% tier)"
      pto_year_allotment updated
      Notification → person: "You're now eligible for 50% PTO benefit"
    
    If today == hire_date + 6 months → 6-month tier crossing
      pto_grant created with +10/+10/+10 delta
      pto_year_allotment updated
      Notification → person
    
    If today == hire_date + 12 months → 1-year tier crossing (and anniversary)
      Two events combined:
      1. Close current PTO year (status=closed, forfeit unused hours)
      2. Open new PTO year (status=open, tier_3, 40/40/40 allotment)
      Notification → person: "Happy anniversary! You're at 100% PTO benefit."
    
    If today == hire_date + N years (N ≥ 2) → Anniversary (no tier change)
      Close current PTO year (forfeit unused)
      Open new PTO year (40/40/40 allotment, same tier_3)
      Notification → person: "PTO year refreshed"
```

The job is **idempotent** — re-running for the same date produces the same result (existing grants/allotments are not duplicated).

## Path 6: Manual balance adjustment (admin/super_admin only)

Edge case for correcting system errors, granting bonuses, etc.

```
Admin (Back Office → All PTO Balances → person)
  → "Manual Adjustment" button
  Form:
    - Bucket (vacation / scheduled / unscheduled)
    - Hours (+ or − decimal)
    - Reason (required text)
  Submit
  
  Server:
    pto_grant created:
      grant_type = manual_adjustment
      hours (positive or negative)
      reason
      created_by = admin.id
    pto_year_allotment updated
    Activity log
    Notification → person if positive grant
```

## Notifications summary

| Event | Recipient(s) | Channels |
|---|---|---|
| PTO request submitted (requester ≠ hr) | admin, super_admin, hr | Mail + in-app |
| PTO request submitted (requester is hr) | admin, super_admin | Mail + in-app |
| PTO request approved | Requester | Mail + in-app |
| PTO request rejected | Requester (with reason) | Mail + in-app |
| PTO request cancelled by requester | Approvers | In-app |
| PTO request cancelled by approver | Requester (with reason) | Mail + in-app |
| Tier crossing (Day 30, 6 months) | Person | Mail + in-app |
| Anniversary (year close + open) | Person | Mail + in-app |
| Manual grant added | Person (if positive) | In-app |

## Edge cases

| Case | Handling |
|---|---|
| Request spans tier crossing | Tier active on start_date determines bucket; balance check uses that tier's amount |
| Request spans anniversary | Block at submission — must split into two requests, one per PTO year |
| Insufficient balance | Block at submission with clear message; cannot submit |
| Negative balance via manual adjustment | Allowed for admin; person sees warning indicator on their balance view |
| Person terminated with pending PTO | All pending requests auto-cancelled; year_allotment closed (forfeited) |
| Person terminated with approved future PTO | Approved-but-not-taken PTO is cancelled; balance forfeited |
| HR submits own request, no admin available | Bottleneck — super_admin can step in |
| Admin self-approves | Allowed; `is_self_approved` flag set for audit visibility |
| Notice period vacation submitted | Allowed; warning shown at submission AND approval; manager decides |

## Audit trail

Each PTO request lifecycle produces activity_log entries:

```
[2026-05-21 09:14] Submitted   - Jane (recruiter) → Request PTO-1052 (vacation, 16 hrs, May 28-29)
[2026-05-21 14:30] Approved    - David (admin) → Request PTO-1052
[2026-05-22 11:00] Cancelled   - Jane (requester) → Request PTO-1052 ("plans changed")
[2026-05-22 11:00] Balance restored - System → Jane: +16 vacation hours back to available
```

## Related

- ADR-0016 — PTO tier-based accrual policy
- `20-domain/pto.md` — full domain model
- `20-domain/workflows.md` — PTO catalog entry
- `20-domain/people-lifecycle.md` — W-2 vs contractor distinction
- `10-architecture/permissions-matrix.md` — PTO permission rows
