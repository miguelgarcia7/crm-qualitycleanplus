# Flow: Transfer (Permanent)

| Field | Value |
|---|---|
| Status | Accepted |
| Last updated | 2026-05-21 |
| Owner | Product (David) + Engineering |

End-to-end flow for permanently transferring a contractor to a new assignment. Covers three sub-cases:

- **Property change** — contractor moves from Property A to Property B
- **Position change** — contractor stays at same property, changes role
- **Recruiter change** — contractor's primary recruiter changes (often happens alongside property change)

For **temporary** moves (contractor stays under primary recruiter, goes to another property for a defined period), see `40-flows/temporary-assignment.md`.

Related: ADR-0019, `20-domain/work-orders.md`, `20-domain/people-lifecycle.md`.

## Actors

| Actor | Role |
|---|---|
| Initiator | Recruiter (own contractors), HR, Admin, Super Admin |
| Receiving Recruiter | Notified (no approval required) |
| System | Auto-closes old WO, opens new WO, updates `primary_recruiter_id` if changed |

## Pre-conditions

- Contractor is `contractor_active` (or `contractor_inactive` — can transfer from inactive too)
- Initiator has appropriate permission (`workflows.transfer.initiate`)
- For recruiter initiation: contractor is currently under the initiator's primary management
- New property exists in Property Bible; has rates set for the target position

---

## Path 1: Property change (most common)

```
1. Recruiter views Maria's profile → clicks "Transfer"
   │
   ▼
2. Modal opens with form:
   ┌─────────────────────────────────────────────┐
   │  Transfer Maria Lopez                        │
   │                                               │
   │  From: Marriott Downtown Phoenix             │
   │  Current recruiter: Jane Doe                 │
   │                                               │
   │  Effective date: [today ▼]                   │
   │                                               │
   │  New property: [Hyatt Regency Atlanta ▼]     │
   │  → New recruiter at this property: David Smith
   │                                               │
   │  Position at new property:                    │
   │    [Housekeeper ▼] (defaults to current)     │
   │                                               │
   │  Rates (auto-filled from Hyatt's Bible):      │
   │    Pay rate:    [$ 22.00 / hr]                │
   │    Bill rate:   [$ 35.00 / hr]                │
   │    OT pay:      [$ 33.00 / hr]                │
   │    OT bill:     [$ 52.50 / hr]                │
   │                                               │
   │  Reason: [Operational Reassignment ▼]         │
   │  Notes: [text]                                │
   │                                               │
   │  [Cancel]  [Submit Transfer]                  │
   └─────────────────────────────────────────────┘
   │
   ▼
3. Submit
   │
   ▼
4. Server processes:
   a. Validate (effective_date >= today is typical; backdated supported)
   b. Auto-close any open time_entry at Maria's current property (effective_date as end time)
   c. Close old WO:
      - status = closed
      - end_date = effective_date
   d. Open new WO:
      - status = active
      - start_date = effective_date + 1 day
      - parent_wo_id = old WO.id
      - source = transfer_from_wo_id
      - is_temporary_assignment = false
      - rates from form (auto-filled or overridden)
   e. Update primary_recruiter:
      - If new recruiter ≠ old recruiter:
          people.primary_recruiter_id = new recruiter.id
   f. Remap active contractor_charge_schedule entries:
      - For each scheduled entry: replace payroll_period_id with new property's equivalent period (matching week_start)
   g. Reassign pending pay-increase workflows for Maria:
      - Workflows initiated for Maria that are still pending: routed to new recruiter
   h. Activity log entries on both WOs + workflow
   │
   ▼
5. Notify receiving recruiter (David):
   - In-app + mail: "Maria Lopez has been transferred to you at Hyatt Regency Atlanta effective May 22"
   - Maria appears on David's roster starting May 22
   │
   ▼
6. Maria's contractor profile updates:
   - Old WO appears in history (closed)
   - New WO appears as current assignment
   - Activity log shows transfer event
   - "Past Properties" section gains Marriott DT
```

## Path 2: Position change (same property)

```
1. Recruiter views Maria's profile → clicks "Transfer"
   │
   ▼
2. Form opens — but recruiter selects Maria's CURRENT property as "New property"
   - UI detects same-property → form header changes to "Position Change"
   - New position field is the meaningful change
   - Rates auto-fill from new (position, property) combination
   │
   ▼
3. Submit
   │
   ▼
4. Server processes (same as Path 1):
   - Close old WO at effective_date
   - Open new WO same property, new position, new rates
   - primary_recruiter_id UNCHANGED (recruiter is the same)
   - No charge schedule remapping needed (same property's periods)
   - Activity log captures the position change
   │
   ▼
5. No external notification needed (same recruiter, same property)
```

## Path 3: Recruiter change at same property

```
- Rare case where the recruiter for a property changes, and a specific contractor's primary recruiter needs to update
- More commonly handled by the `recruiter_to_property_transfer` workflow (bulk reassignment of all contractors at a property)
- For individual case: use this Transfer workflow with same property, but a different "new recruiter" override (admin-only override; recruiters can't change other recruiters' ownership)
```

Permission: only admin / super_admin can explicitly assign a different recruiter via Transfer when property is unchanged. Recruiters can only initiate transfers that follow the new property's natural recruiter.

## Forward-dated transfers (planning ahead)

```
1. Today: May 15
2. Recruiter initiates transfer with effective_date = June 1
3. Workflow created in `scheduled` state — no immediate changes
4. Maria continues working at Marriott normally
5. Daily scheduled job (ProcessScheduledTransfers) checks:
   - On June 1: workflow is now actionable
   - Runs the standard processing (steps 4a-4h above)
6. Maria's first day at Hyatt = June 2
```

## Backdated transfers (post-hoc entry)

```
- "Maria's been at Hyatt since last Wednesday; we forgot to enter the transfer"
- Recruiter initiates transfer with effective_date in the past
- Server processes immediately (effective_date is past):
  * Close old WO with end_date = backdated effective_date
  * Open new WO with start_date = backdated effective_date + 1
- Existing time_entries during the gap: depends on what was recorded
  * If Maria was somehow clocking in at Marriott during that period (shouldn't happen if she's physically at Hyatt — GPS check would block): super_admin manually moves those entries to the new WO
  * Usually clock-ins at Hyatt fail because no WO exists for her there yet, so this is uncommon
- Activity log captures the discrepancy: "Backdated — effective June 1 (entered June 5)"
```

## Workflow cancellation (rare, super_admin only)

```
- Transfer was initiated by mistake
- Super Admin clicks "Cancel Workflow"
- Modal: required reason
- Server:
  * If workflow is in `scheduled` state (forward-dated, not yet processed): cancel cleanly; nothing was changed
  * If workflow has been processed (old WO closed, new WO open):
    - Reopen old WO (status=active, end_date=null)
    - Delete new WO (or close it as `cancelled` for audit visibility)
    - Revert primary_recruiter_id if it was changed
    - Manual cleanup: auto-closed clock-ins are NOT auto-restored (super_admin can restore individually)
    - Charge schedule remapping is NOT auto-reverted (the new property's periods remain — usually fine)
  * Activity log captures the cancellation
- Receiving recruiter notified of cancellation
```

## Notifications

| Event | Recipient(s) | Channels |
|---|---|---|
| Transfer initiated | New recruiter (if different), HR | Mail + in-app |
| Transfer processed (effective date arrives) | Both recruiters (if different) | In-app |
| Transfer cancelled | Both recruiters, HR | Mail + in-app |

## Permissions

| Action | Roles |
|---|---|
| `workflows.transfer.initiate` (own contractor) | recruiter, admin, super_admin, hr |
| `workflows.transfer.initiate` (any contractor) | hr, admin, super_admin |
| `workflows.transfer.assign_different_recruiter_same_property` | admin, super_admin only |
| `workflows.transfer.cancel` | super_admin only |

## Audit trail example (Path 1)

```
[14:00] Workflow initiated      - Jane (recruiter) → Transfer workflow #2104 for Maria
                                    effective_date=2026-05-21
                                    from=Marriott DT, to=Hyatt Atlanta
                                    new_recruiter=David, reason=operational_reassignment

[14:00] Open clock-in closed    - System → Maria's open time_entry closed at 2026-05-21 15:00
[14:00] Old WO closed           - System → WO #4521 (Maria @ Marriott DT) closed effective 2026-05-21
[14:00] New WO opened           - System → WO #4787 (Maria @ Hyatt Atlanta, Housekeeper, $22/$35) start 2026-05-22
[14:00] Primary recruiter updated - System → Maria.primary_recruiter_id: Jane → David
[14:00] Charge schedules remapped - System → 1 active schedule remapped to Hyatt's periods
[14:00] Pay-increase requests reassigned - System → 0 pending pay-increase requests for Maria

[14:01] Notification sent       - System → David (new recruiter): "Maria Lopez transferred to you"

[2026-05-22 09:14] Clocked in   - Maria → Hyatt Atlanta (new WO #4787)
```

## Edge cases

| Case | Handling |
|---|---|
| Contractor still has an open clock-in at old property | Auto-closed at effective_date (mid-day allowed for "transfer effective end of day") |
| New property doesn't have rates for the target position | Block at form validation: "Add this position + rate at Hyatt Atlanta's Bible first" |
| New property's geofence not set | Allowed (warning shown); contractor clock-in at new property will need GPS verification once configured |
| Contractor has multiple open WOs (was temp-assigned elsewhere) | Transfer only affects the WO at the FROM property. Temp WOs are unaffected. |
| Transferred contractor has pending workflows | Pay-increase requests reassigned; other workflows handled case-by-case |
| Transfer with same property AND same position AND same recruiter | Blocked at validation: "No change detected" |
| Receiving recruiter rejects the transfer | Not supported v1 — out-of-band conversation only. If rejected, originating recruiter initiates cancel/reverse |

## What stays with the contractor across transfers

Per ADR-0019 and David's clarification:

- **Uniforms, badges, name tags** — contractor owns them (they paid). Stay with them.
- **Equipment assignments** — stay assigned to the contractor (they didn't return it).
- **`contractor_charge_schedules`** — continue (entries get remapped to new property's periods).
- **Personal info, application history** — same person, same record.
- **Their `primary_recruiter_id`** — updates only if the transfer changes recruiter.

## What does NOT stay

- **Old WO** — closed, in history only.
- **Old roster membership** — old recruiter no longer "owns" them (if recruiter changed).

## Related

- ADR-0019 — Transfer + Temporary Assignment workflows
- `40-flows/temporary-assignment.md` — for non-permanent moves
- `20-domain/workflows.md` — transfer catalog entry
- `20-domain/work-orders.md` — WO model
- `20-domain/people-lifecycle.md` — primary_recruiter_id field
- ADR-0018 — Termination workflow (similar effective-date model)
- ADR-0014 — contractor_charge_schedule remapping rule
- `10-architecture/permissions-matrix.md` — transfer permissions
