# Flow: Pay Increase

| Field | Value |
|---|---|
| Status | Accepted |
| Last updated | 2026-05-21 |
| Owner | Product (David) + Engineering |

End-to-end flow for raising a contractor's pay rate. Two initiation paths converge into the same downstream effect: old WO closes at current pay period end; new WO opens at next pay period with new rates.

Related: ADR-0020, ADR-0005, `20-domain/work-orders.md`.

## Actors

| Actor | Role |
|---|---|
| Property Manager (PM) | Initiates from QC Minute; requests an increase amount |
| Recruiter | Approves with actual new rates; can self-initiate from Back Office |
| HR / Admin / Super Admin | Can initiate on any contractor |
| Contractor | Notified of the new pay rate after approval |
| System | Creates new WO at effective date; manages workflow state |

## Pre-conditions

- Contractor has an active work order at the property
- For PM initiation: PM is at the property with the contractor
- Initiator has appropriate permission (`workflows.pay_increase.initiate`)
- The payroll_period for the effective date is `open`

---

## Path 1: PM-initiated pay increase

```
1. PM (Jane) on QC Minute → opens contractor list at her property
   │
   ▼
2. Sees Maria on her property's roster
   Clicks "Request Pay Increase" on Maria's row
   │
   ▼
3. Modal opens with form (in QC Minute):
   ┌─────────────────────────────────────────────┐
   │  Request Pay Increase for Maria Lopez        │
   │                                               │
   │  Position: Housekeeper                       │
   │  Your current rate for this position:        │
   │    $20.00 / hr                                │
   │                                               │
   │  How much more would you like to pay         │
   │  per hour?                                    │
   │                                               │
   │    + $ [1.00]                                 │
   │                                               │
   │  Your new bill rate will be: $21.00 / hr     │
   │                                               │
   │  Reason: [text — required]                    │
   │  Notes: [text — optional]                     │
   │                                               │
   │  [Cancel]  [Submit Request]                   │
   └─────────────────────────────────────────────┘
   │
   ▼
4. Submit
   │
   ▼
5. Server creates workflow + pay_increase_request:
   - workflow row, status=pending_recruiter_approval
   - pay_increase_requests row:
     - person_id = Maria
     - property_id, work_order_id_at_request
     - initiated_by = Jane (PM)
     - initiation_source = pm
     - pm_requested_increase_amount = 1.00
     - reason, notes captured
   - Activity log
   │
   ▼
6. Notification routed to Recruiter (the recruiter assigned to Maria via her primary_recruiter_id):
   - Mail + in-app: "PM Jane Smith requested a $1.00/hr pay increase for Maria"
   │
   ▼
7. PM sees confirmation: "Request submitted. The recruiter will review."
```

## Path 2: Recruiter reviews and approves

```
1. Recruiter (David, Maria's primary) sees pending request in dashboard
   │
   ▼
2. Opens detail view in Back Office:
   ┌─────────────────────────────────────────────┐
   │  Pay Increase Request — Maria Lopez          │
   │                                               │
   │  Initiated by: PM Jane Smith (Marriott DT)   │
   │  Submitted: May 21, 2026 9:14 AM             │
   │  Status: Pending your approval                │
   │                                               │
   │  PM requested: $1.00/hr increase             │
   │  Reason: "Maria has been outstanding. Wants  │
   │          to retain her."                      │
   │                                               │
   │  Maria's current rates:                       │
   │    Pay rate:  $17.00 / hr                     │
   │    Bill rate: $20.00 / hr (PM's side)         │
   │    OT pay:    $25.50 / hr                     │
   │    OT bill:   $30.00 / hr                     │
   │                                               │
   │  Suggested new rates (pass-through):          │
   │    Pay rate:  [ $18.00 ] / hr   editable     │
   │    Bill rate: [ $21.00 ] / hr   editable      │
   │                  (cannot go below $21.00)     │
   │    OT pay:    [ $27.00 ] / hr   auto-calc     │
   │    OT bill:   [ $31.50 ] / hr   auto-calc     │
   │                                               │
   │  Effective period:                            │
   │    ○ Next period (May 27 - June 2)            │
   │    ○ 2 periods out (June 3 - June 9)          │
   │                                               │
   │  Notes: [text]                                │
   │                                               │
   │  [Decline]  [Approve & Create New WO]         │
   └─────────────────────────────────────────────┘
   │
   ▼
3. Recruiter reviews:
   - May accept pass-through defaults (most common)
   - May adjust pay_rate down (keep margin) — but bill_rate must stay ≥ current + PM's request
   - May give a bigger pay increase if generous (rare)
   - May push effective date to 2 periods out if there's a reason
   │
   ▼
4. Recruiter clicks "Approve"
   │
   ▼
5. Server processes:
   a. Validate:
      - new_bill_rate >= current_bill_rate + PM's requested increase ($21.00 minimum)
      - new_pay_rate >= 0
      - effective_period_id is valid (next or +1)
   b. Create new WO:
      - status = active
      - start_date = effective_period.start_date
      - parent_wo_id = old WO
      - source = pay_increase_from_wo_id
      - rates from form
   c. Close old WO:
      - status = closed
      - end_date = effective_period.start_date - 1 day
   d. Update pay_increase_requests:
      - approved_at, approved_by, new_*_rate, effective_period_id, resulting_work_order_id
   e. Activity log on both WOs + workflow
   │
   ▼
6. Notifications:
   - PM Jane: "Your pay increase request for Maria has been approved.
              New bill rate: $21.00/hr effective May 27."
              (PM does NOT see pay rate)
   - Contractor Maria: "Your pay rate increases to $18.00/hr effective May 27."
              (Contractor sees their pay rate)
   - Activity log entries
   │
   ▼
7. From the effective date forward:
   - Maria's clock-ins create time_entries with new rate snapshots ($18 pay, $21 bill)
   - Old WO's time_entries remain at old rates (snapshotted at clock-in time per ADR-0005)
   - Billing and payroll for the new period use new rates
```

## Path 3: Recruiter declines

```
1. Recruiter in detail view clicks "Decline"
   │
   ▼
2. Modal asks for reason (required):
   ┌─────────────────────────────────────────────┐
   │  Decline Pay Increase Request                │
   │                                               │
   │  Reason (visible to PM):                      │
   │  [text — required]                            │
   │                                               │
   │  [Cancel]  [Submit Decline]                   │
   └─────────────────────────────────────────────┘
   │
   ▼
3. Submit
   │
   ▼
4. Server:
   - workflow status = declined
   - pay_increase_requests.declined_at, declined_by, decline_reason
   - Activity log
   │
   ▼
5. Notify PM: "Your pay increase request for Maria has been declined. Reason: [recruiter's reason]"
   │
   ▼
6. PM may submit a new request later — no system block
```

## Path 4: PM cancels their own pending request

```
1. PM in QC Minute → "My Requests" → finds the pending one
   Clicks "Cancel"
   │
   ▼
2. Modal: "Why are you cancelling?" [optional text]
   │
   ▼
3. Server:
   - workflow status = cancelled
   - pay_increase_requests.cancelled_at, cancelled_by, cancel_reason
   - Activity log
   │
   ▼
4. Notify recruiter: "PM Jane cancelled her pay increase request for Maria"
   │
   ▼
5. No new WO created; old WO continues unchanged
```

## Path 5: Recruiter-initiated pay increase (no PM request)

```
1. Recruiter on Back Office → Maria's profile → "Adjust Pay Rate"
   │
   ▼
2. Form (simpler — no PM request to translate):
   ┌─────────────────────────────────────────────┐
   │  Adjust Pay Rate for Maria Lopez             │
   │                                               │
   │  Current rates:                               │
   │    Pay rate:  $17.00 / hr                     │
   │    Bill rate: $20.00 / hr                     │
   │    OT pay:    $25.50 / hr                     │
   │    OT bill:   $30.00 / hr                     │
   │                                               │
   │  New rates:                                   │
   │    Pay rate:  [ $18.00 ] / hr                 │
   │    Bill rate: [ $20.00 ] / hr  (no change)   │
   │    OT pay:    [ $27.00 ] / hr  (auto-calc)    │
   │    OT bill:   [ $30.00 ] / hr  (no change)   │
   │                                               │
   │  Effective:                                   │
   │    ○ Next period   ○ 2 periods out            │
   │                                               │
   │  Reason: [text]                               │
   │                                               │
   │  [Cancel]  [Apply Increase]                   │
   └─────────────────────────────────────────────┘
   │
   ▼
3. Submit
   │
   ▼
4. Server processes (no PM approval step — recruiter is the authority):
   - pay_increase_requests row with initiation_source = recruiter
   - Skip "pending approval" — go straight to processed
   - New WO created
   - Old WO closed
   - Activity log
   │
   ▼
5. Notify contractor of new pay rate
   - PM notified IF bill rate changed (optional; usually doesn't change in recruiter-initiated cases)
```

## Effective date mechanics

Per ADR-0005 + ADR-0020:

```
Today: Friday, May 22, 2026
Current pay period: May 20 - May 26 (the week we're in now)
Next pay period:    May 27 - June 2

If PM submits today and recruiter approves today:
- Effective options:
  * Next period (May 27 - June 2) — default
  * 2 periods out (June 3 - June 9)

- New WO start_date = effective_period.start_date (e.g. May 27)
- Old WO end_date = effective_period.start_date - 1 day (May 26)

- Maria works the rest of THIS week at OLD rates ($17/$20)
- Maria works NEXT week onward at NEW rates ($18/$21)
- No mid-week rate splits; rates change at period boundary
```

## Notifications

| Event | Recipient(s) | Channels | What they see |
|---|---|---|---|
| PM submits request | Recruiter (assigned to contractor) | Mail + in-app | "PM Jane requested $X/hr increase for Maria. Review & approve." |
| Recruiter approves (PM-initiated) | PM | Mail + in-app | New BILL rate (not pay rate) + effective date |
| Recruiter approves (PM-initiated) | Contractor | In-app | New PAY rate + effective date |
| Recruiter declines | PM | Mail + in-app | Reason for decline |
| PM cancels own request | Recruiter | In-app | "Request cancelled" |
| Recruiter self-initiates | Contractor | In-app | New pay rate + effective date |
| Recruiter self-initiates (bill rate also changing) | PM | Mail + in-app | New bill rate + effective date |

## Permissions

| Action | Roles |
|---|---|
| `workflows.pay_increase.initiate` (own property) | property_manager |
| `workflows.pay_increase.initiate` (own contractor) | recruiter |
| `workflows.pay_increase.initiate` (any contractor) | hr, admin, super_admin |
| `workflows.pay_increase.approve` | recruiter (own contractor — for PM-initiated requests), admin, super_admin |
| `workflows.pay_increase.cancel_own` | the initiator (PM or recruiter) |
| `workflows.pay_increase.cancel_others` | super_admin only |

## Audit trail example

```
[09:14] Workflow initiated   - PM Jane → Request #3120 for Maria Lopez
                                 type=pay_increase, increase_amount=$1.00, 
                                 reason="Outstanding performance"

[09:14] Notification sent    - System → Recruiter David: "PM request for Maria"

[14:30] Recruiter reviewed   - David opens request #3120
[14:32] Workflow approved    - Recruiter David → Request #3120
                                 new_pay=$18.00, new_bill=$21.00,
                                 ot_pay=$27.00, ot_bill=$31.50,
                                 effective=May 27 (next period)

[14:32] WO closed            - System → WO #4521 (Maria @ Marriott DT) closed effective May 26
[14:32] WO opened            - System → WO #4790 (Maria @ Marriott DT, Housekeeper, $18/$21) start May 27,
                                 source=pay_increase_from_wo_id, parent_wo_id=4521

[14:32] Notification sent    - System → PM Jane: "Pay increase approved. New bill rate $21.00 eff May 27"
[14:32] Notification sent    - System → Maria: "Your pay rate increases to $18.00/hr eff May 27"
```

## Edge cases

| Case | Handling |
|---|---|
| PM tries to submit a $0 increase (or negative) | Block at validation — "Increase amount must be positive" |
| Recruiter tries to set bill_rate below PM's requested floor | Block at approval — "Bill rate must be at least $X.XX (current + PM's request)" |
| Maria is currently on a temp assignment when increase is approved | Increase applies to her home WO (per ADR-0019, temp WOs are separate; this pay increase is for her home assignment) |
| Maria has open clock-in when approval happens | No effect — rates change at next pay period boundary, not now. Current clock-in continues at old rates. |
| Multiple consecutive pay increases | Each creates a new WO. WO chain via parent_wo_id captures history. |
| Pay increase request still pending when PM is terminated/leaves property | Workflow stays pending; if no other PM at property, recruiter can still approve (the request is for the contractor, not the PM personally). System optionally flags this. |
| Pay increase request still pending when contractor is terminated | Workflow auto-cancelled (per ADR-0018's auto-cancel rule for pending workflows initiated for or by the terminated person — but here Maria is subject, not initiator. Cancel anyway since the WO will close.) |
| Recruiter is on vacation; PM's request stalls | No automatic re-route. Surfaces in admin dashboard as "Stale pay-increase requests." Admin can manually approve or reassign. |
| Approved with effective date in the past (error?) | Not possible — form constrains to next period or +1 |

## What this enables for reports

- "Pay increases granted this quarter" — count + total dollar impact
- "Average time from PM request to recruiter approval" — workflow analytics
- "Pay increase requests declined" — pattern detection
- "Margin maintained / eroded per increase" — bill_rate_change vs pay_rate_change comparison

## Related

- ADR-0020 — Pay Increase workflow (full decision)
- ADR-0005 — Rate snapshots on time entries
- ADR-0019 — Transfer workflow (similar shape: close old WO + open new WO at period boundary)
- `20-domain/workflows.md` — pay_increase catalog entry
- `20-domain/work-orders.md` — pay increase WO transition mechanics
- `10-architecture/permissions-matrix.md` — pay_increase permissions
