# ADR-0020: Pay Increase Workflow

| Field | Value |
|---|---|
| Status | Accepted |
| Decided | 2026-05-21 |
| Owner | Product (David) + Engineering |
| Supersedes | — (refines `pay_increase` catalog entry in `20-domain/workflows.md`) |
| Superseded by | — |

## Context

Pay increases for contractors happen continuously. Two paths:

- **PM-initiated** — the property manager (at the hotel) decides Maria deserves more pay and asks QCP to raise her rate. The PM is volunteering to pay more.
- **Recruiter-initiated** — the recruiter recognizes a contractor's value and gives them a raise without waiting for PM ask.

Critical operational details surfaced during design:

1. **PMs don't know contractor pay rates.** They only know the bill rate (what they pay QCP). Per ADR-0019 and the broader confidentiality model, contractor pay must stay hidden from PMs.

2. **PMs request an INCREASE amount, not a target rate.** "Maria gets a dollar more" — not "Maria should be at $25/hr." The PM frames the conversation in terms of how much more they're willing to pay.

3. **QCP always honors PM's offer to pay more.** If PM says "+$5," QCP raises the bill rate by at least $5. (Why turn down free margin?)

4. **The contractor's pay raise is recruiter's call.** Default is pass-through (contractor gets the same dollar amount the bill went up by). But recruiter can adjust — give the contractor less (eat margin) or give them more (rare).

5. **Rate changes always effective at next pay period boundary** (per ADR-0005). No mid-period rate changes. Recruiter can also push effective date further out.

## Decision

A `pay_increase` workflow with these properties:

### Initiation

Two paths:

- **PM initiates** — submits via QC Minute with the increase amount they're offering
- **Recruiter / HR / Admin initiates** — submits via Back Office with both rates and effective period directly

PM-initiated and recruiter-initiated workflows have slightly different forms but produce the same downstream result (new WO with new rates effective next pay period).

### PM-initiated form (in QC Minute)

| Field | Type | Required |
|---|---|---|
| Contractor | dropdown of contractors at PM's property | yes |
| Increase amount per hour | positive decimal (e.g. "+$1.00") | yes |
| Reason / justification | text | yes |
| Notes | text | optional |

PM does NOT see contractor's current pay rate. They DO see the contractor's current bill rate (what their property is paying) for reference. They specify how much MORE they're willing to pay per hour.

On submit: workflow created with `initiated_by = PM`, requested_increase_amount stored, status = `pending_recruiter_approval`.

### Recruiter approval form (in Back Office)

The form opens with context:

```
PM Jane Smith requested a pay increase for Maria Lopez

  Property: Marriott DT Phoenix
  Position: Housekeeper
  
  PM requested: $1.00/hr increase
  
  Maria's current rates:
    Pay rate:  $17.00 / hr
    Bill rate: $20.00 / hr (PM's side)
    OT pay:    $25.50 / hr
    OT bill:   $30.00 / hr
  
  Suggested new rates (pass-through of PM's request):
    Pay rate:  [$18.00 / hr]   ← editable
    Bill rate: [$21.00 / hr]   ← editable (cannot go below $21.00)
    OT pay:    [$27.00 / hr]   ← editable (auto-recalc on pay rate change)
    OT bill:   [$31.50 / hr]   ← editable (auto-recalc on bill rate change)
  
  Effective: [Next pay period — May 27 ▼]
             Options: May 27 (next) / June 3 (2 periods out)
  
  Notes / counter-offer rationale: [text]
  
  [Approve & Create New WO]   [Decline with Reason]
```

Validation:
- `new_bill_rate >= current_bill_rate + PM's requested increase` (the "always honor" rule)
- `new_pay_rate >= 0` and reasonable (sanity check)
- `effective_date` is the next pay period start OR 2 periods out (no other options for v1)

### Recruiter-initiated form (in Back Office)

When recruiter initiates without a PM request, the form is simpler — they just enter the new rates directly:

| Field | Type | Required |
|---|---|---|
| Contractor | dropdown | yes |
| New pay rate | decimal | yes |
| New bill rate | decimal | yes |
| New OT pay rate | decimal | yes (auto-calc default) |
| New OT bill rate | decimal | yes (auto-calc default) |
| Effective period | dropdown (next / 2 out) | yes |
| Reason | text | yes |

No "increase amount" concept here — recruiter is setting the rates directly. Workflow goes straight to "approved" (no second-party approval needed when recruiter is the initiator). Auto-creates new WO.

### Effective date

Per ADR-0005, rate changes always take effect at a **pay period boundary**, never mid-week. The form offers two options:

- **Next pay period start** (the soonest possible) — default
- **2 periods out** (push back one period) — for "give them the raise starting in 3 weeks" scenarios

No other options for v1. (3+ periods out, calendar-specific dates, etc. — defer.)

### On approval (PM-initiated)

```
1. New WO created with the rates from the approval form
   - status = active
   - start_date = effective_period.start_date
   - source = pay_increase_from_wo_id
   - parent_wo_id = old WO

2. Old WO closes
   - status = closed
   - end_date = effective_period.start_date - 1 day (last day at old rate)

3. Activity log on both WOs + workflow

4. Notify PM: "Your pay increase request for Maria has been approved — new bill rate effective May 27"
   PM sees the new BILL rate (their side); does NOT see pay rate.

5. Notify contractor (Maria): "Your pay rate increases to $18/hr effective May 27"
   Contractor sees their NEW PAY rate.
```

### On decline

```
1. Workflow status = declined
   - reject_reason captured (required text)
2. Notify PM with reason
3. New WO NOT created; old WO continues
4. PM can submit a new request later
```

### Cancellation by initiator

A pending pay_increase can be cancelled by its initiator before action:

- **PM cancels** their own pending request — yes, allowed; required reason; workflow closes with status=cancelled
- **Recruiter cancels** a self-initiated request — yes, allowed
- **Super_admin** cancels any pending request

After approval (new WO created), cancellation is no longer a workflow concern — it would require closing the new WO and reopening the old one, which is an admin operation, not a cancellation.

## Consequences

### Positive

- PMs frame requests in terms they understand (dollars more) without exposure to contractor pay
- Recruiter has full discretion on pay rate (margin management)
- "Always honor PM's offer" rule is enforceable at validation time
- Effective date constraint (next pay period or +1) keeps the system predictable
- Same effect downstream as a transfer with rate change: close old WO + open new WO at boundary
- PM stays in the loop with confirmation that their request was honored
- No PM-side visibility into pay rates preserves margin confidentiality

### Negative

- PM doesn't see if recruiter passed the full raise to the contractor or kept some as margin (intentional — but PMs may eventually ask)
- Recruiter form has more cognitive load (pay rate + bill rate + OT calculations) — UI should auto-calc by default
- If PM submits multiple back-to-back requests, each creates a new WO (system handles fine, just looks busy in audit)

### Implementation requirements

Schema:

- `pay_increase_requests` denormalized table for reporting:
  ```
  - id, workflow_id
  - person_id (contractor)
  - property_id
  - work_order_id_at_request (the WO being changed)
  - initiated_by (FK to people)
  - initiation_source: enum (pm | recruiter | hr | admin)
  - pm_requested_increase_amount (decimal, nullable — null when recruiter-initiated)
  - approved_by (FK to people, nullable)
  - approved_at (datetime, nullable)
  - new_pay_rate, new_bill_rate, new_ot_pay_rate, new_ot_bill_rate (decimal, nullable until approved)
  - effective_period_id (FK to payroll_periods)
  - resulting_work_order_id (FK to new WO, populated on approval)
  - declined_at, declined_by, decline_reason (nullable)
  - cancelled_at, cancelled_by, cancel_reason (nullable)
  - created_at, updated_at
  ```

Validation logic at recruiter approval:
- `new_bill_rate >= current_bill_rate + (pm_requested_increase_amount OR 0)`
- `new_pay_rate >= 0`
- `effective_period_id` is one of: next open period OR period after that
- Sanity check: new rates significantly different from current (catch typos)

Permissions:

- `workflows.pay_increase.initiate` — PM (own property), recruiter (own contractor), HR, admin, super_admin
- `workflows.pay_increase.approve` — recruiter (own), admin, super_admin
- `workflows.pay_increase.cancel_own` — initiator
- `workflows.pay_increase.cancel_others` — super_admin

## Alternatives considered

### A. PM specifies target rate (e.g. "$25/hr")

Rejected. PM doesn't know contractor's current pay; "target rate" is meaningless to them. Increase amount is what they naturally think in.

### B. Allow PM to see contractor pay rate for transparency

Rejected per David's confidentiality direction. Margin model relies on PM not knowing.

### C. Auto-approve pay increases under a threshold (e.g. <$0.50)

Rejected. Every rate change should be a deliberate recruiter decision. Auto-approval undermines accountability.

### D. Allow PM to specify new bill rate directly

Rejected. PM understands "I'll pay $1 more"; they may not understand "the new bill rate is $21." Increase framing is more natural.

### E. Recruiter can give contractor a raise BELOW the PM's offered amount (eat the margin in reverse)

Allowed by the design — recruiter has full pay-rate discretion. Edge case but supported. Bill rate increase is mandatory (always honor PM).

### F. Auto-recompute OT rates strictly

Default OT calculation: 1.5× the new base rate (industry standard). Form auto-fills with this, but recruiter can adjust if a different OT multiplier applies.

## Related

- `40-flows/pay-increase.md` — step-by-step flow
- `20-domain/workflows.md` — pay_increase catalog entry
- `20-domain/work-orders.md` — WO model + transition mechanics
- ADR-0005 — Rate snapshots (no retroactive changes)
- ADR-0019 — Transfer workflow (similar shape: close old WO, open new WO at boundary)
- `10-architecture/permissions-matrix.md` — pay_increase permissions
