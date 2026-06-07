# ADR-0019: Transfer + Temporary Assignment Workflows

| Field | Value |
|---|---|
| Status | Accepted |
| Decided | 2026-05-21 |
| Owner | Product (David) + Engineering |
| Supersedes | — (refines `transfer` entry in `20-domain/workflows.md`; adds new `temporary_assignment` workflow) |
| Superseded by | — |

## Context

The legacy "transfer" concept conflates two operationally distinct cases:

1. **Permanent transfer** — contractor moves from Property A to Property B; their home base changes; their recruiter may change; they don't return to Property A.

2. **Temporary assignment** — contractor stays under their primary recruiter at Property A, but works temporarily at Property B for a defined period (a day, two weeks). After the period ends, they return to Property A. For roster-count and accountability purposes, they remain under their original recruiter.

These are operationally different. Modeling them as one workflow with a "type" field conflates concerns. Better to have two distinct workflows that share some infrastructure but have different semantics.

Additionally, the system today has no explicit concept of a contractor's **primary recruiter**. Recruiter ownership is inferred from property assignments transitively (contractor → WO → property → recruiter), which breaks down when a contractor has multiple WOs at different properties under different recruiters. The temp assignment case makes this explicit need visible.

## Decision

### 1. Two separate workflows

- **`transfer`** — permanent move. Close old WO, open new WO. May update `primary_recruiter_id`.
- **`temporary_assignment`** — concurrent assignment with defined end date. Keep old WO open. Open temp WO with `start_date` AND `end_date` set. **`primary_recruiter_id` UNCHANGED.**

### 2. New field on people: `primary_recruiter_id`

```
people
  ...
  primary_recruiter_id (FK to people, nullable)
```

- Set when applicant is promoted to contractor (defaults to the recruiter at their first property)
- Updated only on **permanent transfer** when the recruiter changes
- **NOT changed** by temporary assignments
- Used for roster-count metrics: "how many active contractors does Jane have?" filters by `primary_recruiter_id`

### 3. Transfer workflow (permanent)

**Form fields:**

| Field | Type | Required |
|---|---|---|
| Subject contractor | FK to people | yes |
| Effective date | date | yes (defaults today; supports forward/backward per termination model) |
| New property | FK to properties | yes (can be same as old property if position is changing) |
| New recruiter | FK to people | optional (defaults to property's assigned recruiter) |
| New position | FK to positions | yes (defaults to old WO's position) |
| New pay rate / bill rate / OT rates | cents | yes (auto-filled from new property's Bible; editable) |
| Reason | enum: `operational_reassignment` / `contractor_request` / `property_request` / `coverage_need` / `performance_fit` / `position_change` / `other` | yes |
| Notes | text | optional |

**On submit:**

```
1. Validate (effective_date sane, new property exists, etc.)
2. Auto-close any open clock-in at effective_date (similar to termination)
3. Close old WO (status=closed, end_date=effective_date)
4. Open new WO (status=active, start_date=effective_date+1, parent_wo_id=old WO, source=transfer_from_wo_id)
5. If new_recruiter is different from old recruiter:
   - Update people.primary_recruiter_id = new_recruiter
6. If active contractor_charge_schedules exist:
   - Remap scheduled entries to new property's payroll_periods (matching week_start)
7. Reassign pending pay-increase workflows for this contractor to the new recruiter
8. Notify new recruiter (if changed): "Maria has been transferred to you at Hyatt DT effective May 22"
9. Activity log entries on both WOs and the workflow
```

### 4. Temporary Assignment workflow

**Form fields:**

| Field | Type | Required |
|---|---|---|
| Subject contractor | FK to people | yes |
| New property | FK to properties | yes (must differ from contractor's current primary property) |
| Start date | date | yes |
| End date | date | yes (must be after start_date) |
| Position at new property | FK to positions | yes |
| Pay rate / bill rate / OT rates | cents | yes (auto-filled from new property's Bible; editable) |
| Reason | enum: `coverage_need` / `event_staffing` / `cross_training` / `peak_demand` / `other` | yes |
| Notes | text | optional |

**On submit:**

```
1. Validate (start <= end, contractor not already temp-assigned elsewhere during this window)
2. Old WO (at contractor's home property) STAYS OPEN — unchanged
3. Create new TEMP WO at new property:
   - start_date = start_date from form
   - end_date = end_date from form (REQUIRED for temp; differs from permanent)
   - parent_wo_id = home WO
   - source = temporary_assignment_from_wo_id
   - is_temporary_assignment = true (flag)
4. primary_recruiter_id on people: UNCHANGED
5. contractor_charge_schedules: UNCHANGED (no remapping needed; they belong to the person, not the WO)
6. Notify receiving recruiter: "Maria is temporarily assigned to your property Hyatt DT from May 22 to June 5"
7. During temp period: contractor can clock in at either property (GPS verifies which; system records to whichever WO matches)
8. Activity log entry
```

**Auto-close at end_date:**

A daily scheduled job (`ProcessTemporaryAssignmentEnds`) runs:

- Finds temp WOs where `end_date <= today` AND `status = active`
- Closes them (`status = closed`)
- Notifies both recruiters: "Maria's temp assignment at Hyatt has ended; she's back on her home roster at Marriott"
- Activity log entry

**Extending a temp assignment:**

If the temp needs to go longer, the home recruiter can edit the WO's `end_date` (if it hasn't passed yet) OR initiate a new `temporary_assignment` workflow for the extension.

### 5. Where temp shows up in queries

| Query | Behavior |
|---|---|
| Recruiter's roster count | `COUNT(people WHERE primary_recruiter_id = X AND status IN ('contractor_active', 'contractor_inactive'))` — temp visitors NOT counted |
| "Contractors at my property right now" | Includes both home contractors AND temp visitors (any active WO at the property) |
| Reports filtered by recruiter | Use `primary_recruiter_id` for ownership reports; use property assignments for activity reports |
| Receiving recruiter's UI | Shows temp visitors with a "Temporary — from [Home Property], returns [date]" badge |

### 6. Workflow distinctions side-by-side

| Aspect | `transfer` (permanent) | `temporary_assignment` |
|---|---|---|
| Old WO | Closes at effective_date | Stays open |
| New WO start_date | effective_date + 1 day | start_date from form |
| New WO end_date | null (open-ended) | end_date from form (required) |
| New WO `is_temporary_assignment` | false | true |
| Primary recruiter | Updates if changing | Unchanged |
| Contractor count for new recruiter | Moves to new recruiter (if changed) | Stays with original recruiter |
| Charge schedules | Remapped to new property's periods | Unchanged (not property-bound) |
| Active uniform/equipment | Stays with contractor (they own it) | Stays with contractor |
| End-of-temp auto-close | N/A | Daily job closes WO at end_date |
| Cancellation | Super_admin only, before completion | Super_admin only, while temp WO is open |

### 7. Cancellation rules

Both workflows can be cancelled by super_admin:

- **Transfer cancellation** (before completion): old WO reopens, new WO deleted, primary_recruiter reverts. Auto-closed clock-ins NOT auto-restored (manual super_admin action).
- **Temp assignment cancellation** (before end_date): temp WO closed early with `was_cancelled = true` flag. Activity log captures reason.

### 8. Same property, different position (still uses transfer)

The transfer workflow handles same-property-different-position by accepting the same `property_id` as the old WO. The UI adapts:

- If `new_property_id != old.property_id` → form header: "Transfer"
- If `new_property_id == old.property_id` (position change only) → form header: "Position Change"
- Same underlying mechanism (close old WO + open new WO + audit trail)
- Reason enum includes `position_change` for clarity

Workflow internal name stays `transfer` — the UI adapts to make it natural.

## Consequences

### Positive

- Two distinct workflows match operational reality (permanent vs temporary)
- `primary_recruiter_id` makes recruiter ownership explicit and queryable
- Roster-count metrics are clean and unambiguous
- Receiving recruiter sees temp visitors clearly distinguished from owned contractors
- Auto-close job handles temp end-of-period cleanly
- Charge schedules don't need to be remapped on temp moves (they belong to person, not WO/property)
- Contractors moving don't need to return uniforms/badges — they own them (per David's clarification)

### Negative

- One more workflow to maintain + document
- New schema field on people (`primary_recruiter_id`) — needs to be set on every existing contractor during migration
- Auto-close at temp end_date is one more scheduled job
- "Same property, different position" still uses Transfer (slightly awkward semantically, but mechanically sound)

### Implementation requirements

Schema additions:

- `people.primary_recruiter_id` (FK to people, nullable)
- `work_orders.is_temporary_assignment` (boolean, default false)
- `work_orders.source` enum gains `temporary_assignment_from_wo_id`

Logic:

- Promotion workflow (applicant → contractor) sets `primary_recruiter_id` based on first property's recruiter
- Transfer workflow updates `primary_recruiter_id` only on permanent moves
- Temp assignment workflow does NOT touch `primary_recruiter_id`
- Contractor_charge_schedule entries: on permanent transfer, scheduled entries' payroll_period_id is remapped to new property's equivalent period. No change on temp assignment.

Scheduled jobs:

- `ProcessTemporaryAssignmentEnds` — daily, closes temp WOs at end_date
- `ProcessScheduledTransfers` — daily, advances forward-dated permanent transfers to their effective_date

Permissions:

- `workflows.transfer.initiate` — recruiter (own contractor), hr, admin, super_admin
- `workflows.transfer.cancel` — super_admin only
- `workflows.temporary_assignment.initiate` — recruiter (own contractor — home recruiter), hr, admin, super_admin
- `workflows.temporary_assignment.cancel` — super_admin only

Auto-actions on transfer:

- Auto-close open clock-in (same as termination)
- Remap charge schedule entries
- Reassign pending pay-increase workflows to new recruiter
- No uniform/equipment return (contractor owns these — per David's clarification)

## Alternatives considered

### A. One workflow with `assignment_type: permanent | temporary` field

Rejected. The shapes are too different (close vs keep old WO; update vs preserve primary_recruiter). Conditional logic gets messy. Two workflows are clearer.

### B. Make "same property, different position" a separate workflow (`position_change`)

Rejected. Mechanism is identical to transfer (close old WO, open new WO). Two workflows doing the same thing is wasteful. UI adaptation handles the naming.

### C. Use a separate `temp_recruiter_id` field on people to track temp assignments

Rejected. People can have multiple concurrent temp assignments. A field on people doesn't capture that. WO with end_date is the right model.

### D. Auto-update primary_recruiter on temp assignment (so receiving recruiter "owns" them temporarily)

Rejected per David's explicit direction. Roster counts should stay with the home recruiter for accountability.

### E. Require return of uniforms/equipment on transfer

Rejected. Per David: contractors own their uniforms and badges (they paid for them). Wrong-size / misspelled-name exchanges are handled by the existing inventory exchange flow, not by transfer.

## Related

- `40-flows/transfer.md` — permanent transfer flow
- `40-flows/temporary-assignment.md` — temp flow
- `20-domain/workflows.md` — catalog entries
- `20-domain/work-orders.md` — WO model (incl. is_temporary_assignment flag)
- `20-domain/people-lifecycle.md` — primary_recruiter_id field
- ADR-0014 — contractor_charge_schedules (remapping rule on permanent transfer)
- ADR-0018 — Termination workflow (similar effective-date model + cancellation pattern)
- ADR-0013 — Front Desk role
- `10-architecture/permissions-matrix.md` — transfer + temp permissions
