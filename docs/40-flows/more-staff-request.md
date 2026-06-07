# Flow: More-Staff Request

| Field | Value |
|---|---|
| Status | Accepted |
| Last updated | 2026-05-21 |
| Owner | Product (David) + Engineering |

End-to-end flow for a Property Manager requesting additional staff at their property. PM submits via QC Minute; lands in recruiter's "Talent Needs" queue; recruiter fulfills by placing contractors at the property and linking placements to the request.

Related: ADR-0021, ADR-0011.

## Actors

| Actor | Role |
|---|---|
| Property Manager (PM) | Initiates request from QC Minute |
| Recruiter | Receives request; fulfills by placing contractors; links WOs to the request |
| HR / Admin / Super Admin | Can initiate or oversee |
| System | Tracks progress, flags overdue, notifies parties |

## Pre-conditions

- PM has `workflows.more_staff.initiate` permission for their property
- The position exists in the Property Bible for that property
- The payroll_period system is operational (placements create WOs at start of next period)

---

## Path 1: PM submits, recruiter fulfills (happy path)

### Step 1: PM submits

```
1. PM (Jane Smith at Marriott DT) on QC Minute
   │
   ▼
2. Navigates to her property page → clicks "Request More Staff"
   OR clicks "Request Staff" widget from her dashboard
   │
   ▼
3. Modal opens:
   ┌─────────────────────────────────────────────┐
   │  Request More Staff — Marriott DT Phoenix    │
   │                                               │
   │  Position needed: [Housekeeper ▼]            │
   │  Quantity:        [3]                         │
   │  Needed by:       [2026-06-15 ▼]              │
   │  Urgency:         [Normal ▼]                  │
   │                                               │
   │  Reason / context:                            │
   │  [text — required]                            │
   │  "Two housekeepers are taking maternity leave │
   │   in early June. Need coverage."              │
   │                                               │
   │  Notes:                                       │
   │  [text — optional]                            │
   │                                               │
   │  [Cancel]  [Submit Request]                   │
   └─────────────────────────────────────────────┘
   │
   ▼
4. Submit
   │
   ▼
5. Server creates more_staff_request:
   - workflow row created (workflow_id, status=submitted)
   - more_staff_requests row:
     - property_id, position_id, quantity_requested=3
     - by_date, urgency=normal, reason, notes
     - initiated_by = Jane (PM)
     - assigned_recruiter_id = property's assigned recruiter (David)
     - status = submitted
   - Activity log
   │
   ▼
6. Notification routed to David (recruiter):
   - Mail + in-app: "Marriott DT PM requested 3 Housekeepers by June 15"
   │
   ▼
7. PM sees confirmation: "Request submitted (REQ-####). You'll be notified as staff is placed."
```

### Step 2: Recruiter sees the request

```
1. Recruiter (David) on Back Office → Dashboard
   │
   ▼
2. "Talent Needs" widget shows:
   ┌──────────────────────────────────────────────────────────┐
   │  Talent Needs — Open Requests                              │
   │                                                             │
   │  🟢 Normal  Marriott DT — 3 Housekeepers — by Jun 15       │
   │     0 of 3 placed · submitted 2 hrs ago                    │
   │                                                             │
   │  🔴 Urgent  Hyatt — 1 Banquet Server — by today            │
   │     0 of 1 placed · OVERDUE                                │
   │                                                             │
   └──────────────────────────────────────────────────────────┘
   │
   ▼
3. Sorted by urgency (urgent → high → normal → low), then by oldest first
   Color/icon indicates urgency level and overdue status
   │
   ▼
4. Clicks the Marriott DT row to open detail view
```

### Step 3: Recruiter places contractors

The recruiter has multiple ways to place a contractor:

**Option A — New hire (promote applicant or hire from pipeline):**

```
1. Recruiter promotes Maria from applicant → contractor
2. Creates a new work order:
   - Property: Marriott DT
   - Position: Housekeeper
   - Rates: from property's Bible
   - Form prompts: "Link to a pending request?"
     → Dropdown shows: "Marriott DT — 3 Housekeepers (0 of 3 placed) — REQ-1247"
   - Recruiter ticks the link
3. Server creates WO with more_staff_request_id = REQ-1247
4. more_staff_requests.quantity_fulfilled += 1 (now 1 of 3)
5. Status: submitted → in_progress (auto-transition)
6. Notification → PM Jane: "1 of 3 Housekeepers placed (Maria Lopez)"
```

**Option B — Transfer from another property:**

```
1. Recruiter initiates `transfer` workflow for an existing contractor (e.g. Carlos at Hyatt)
   to bring him to Marriott DT
2. Transfer form captures new property = Marriott DT
3. Form prompts: "Link to a pending request?"
4. Recruiter links the resulting new WO at Marriott DT to REQ-1247
5. Same effect: quantity_fulfilled increments, PM notified
```

**Option C — Temporary assignment:**

```
1. Recruiter initiates `temporary_assignment` workflow to send a contractor from Hyatt
   to Marriott DT for 2 weeks
2. Temp form captures temp property = Marriott DT, end_date set
3. Form prompts: "Link to a pending request?"
4. Recruiter links the temp WO to REQ-1247
5. quantity_fulfilled increments
   NOTE: temp placements count toward fulfillment even though they're not permanent
```

### Step 4: Full fulfillment

```
1. Recruiter places 3rd contractor; links to REQ-1247
   │
   ▼
2. Server detects quantity_fulfilled (3) >= quantity_requested (3)
   - Status: in_progress → fulfilled
   - fulfilled_at = now
   - Activity log entry
   │
   ▼
3. Notification → PM Jane: "All 3 Housekeepers have been placed. Request complete."
   - PM sees list of who was placed (when expanded)
```

## Path 2: Recruiter declines

```
1. Recruiter (David) reviews the request and determines it can't be fulfilled
   (e.g. no candidates in pipeline; can't pull from other properties without harm)
   │
   ▼
2. Clicks "Decline Request"
   │
   ▼
3. Modal:
   ┌─────────────────────────────────────────────┐
   │  Decline Request                              │
   │                                               │
   │  REQ-1247: 3 Housekeepers @ Marriott DT       │
   │                                               │
   │  Reason (visible to PM):                      │
   │  [text — required]                            │
   │  "No candidates available; recommend          │
   │   reposting and waiting 1 week"               │
   │                                               │
   │  [Cancel]  [Submit Decline]                   │
   └─────────────────────────────────────────────┘
   │
   ▼
4. Submit
   │
   ▼
5. Server:
   - Status: → declined
   - declined_at, declined_by, decline_reason
   - Activity log
   │
   ▼
6. Notification → PM with reason
   │
   ▼
7. PM can submit a new request later — no system block
```

## Path 3: PM cancels own request

```
1. PM's plans change (e.g. another contractor returned, no longer need 3)
   │
   ▼
2. PM in QC Minute → "My Requests" → finds the pending one
   Clicks "Cancel"
   │
   ▼
3. Modal: "Cancel this request? Reason:" [text required]
   │
   ▼
4. Submit
   │
   ▼
5. Server:
   - Status → cancelled
   - cancelled_at, cancelled_by, cancel_reason
   - Activity log
   │
   ▼
6. Notification → Recruiter David: "PM cancelled Request REQ-1247: '[reason]'"
   │
   ▼
7. Already-linked placements (if any) STAY — those WOs are valid; they just no longer satisfy a pending request.
   The cancellation just stops future placements from counting toward this request.
```

## Path 4: Overdue request

```
1. by_date arrives, request still not fully fulfilled (e.g. fulfilled=1, requested=3)
   │
   ▼
2. Daily scheduled job (CheckMoreStaffRequestsOverdue):
   - Finds: requests where by_date < today AND status IN (submitted, in_progress)
   - Flags them as overdue (sets `is_overdue = true` or computed)
   │
   ▼
3. Dashboard surfaces:
   - PM dashboard: "1 of your requests is overdue"
   - Recruiter dashboard: red badge on the queue card
   - Reports flag: "Overdue more-staff requests"
   │
   ▼
4. Notification (one-time per overdue threshold):
   - 24h past by_date: notify both PM and Recruiter
   │
   ▼
5. NO automatic state transition — status stays as it was (submitted or in_progress)
   Recruiter is still expected to act; PM can decide to wait, cancel, or escalate
```

## Path 5: Recruiter spins up a public job posting from a request

```
1. Recruiter on the request detail view
   Sees "Create Job Posting from this Request" button
   │
   ▼
2. Clicks it
   │
   ▼
3. New job posting form opens, pre-filled:
   - Title: "Housekeeper — Marriott Downtown Phoenix"
   - Location: Marriott DT's city + state
   - Pay range: from Bible's rates for this position
   - Posting end_date: by_date from the request
   - Description: pulls from request's reason / standard template
   │
   ▼
4. Recruiter edits as needed → publishes
   │
   ▼
5. Job posting appears on public marketing site
   New applications flow into the applicant pipeline naturally
   When recruiter promotes one to contractor and places them at Marriott DT,
   they link the new WO to REQ-1247 (just like Option A above)
```

The job posting and the request are loosely linked (no hard FK; just contextual creation). Recruiter could create multiple postings for one request or vice versa.

## Notifications summary

| Event | Recipient(s) | Channels |
|---|---|---|
| Request submitted | Recruiter | Mail + in-app |
| Placement linked (1 of N) | PM | In-app |
| Fully fulfilled | PM | Mail + in-app |
| Declined | PM (with reason) | Mail + in-app |
| PM cancelled own request | Recruiter | In-app |
| Overdue (24h past by_date, one-time) | PM + Recruiter | In-app |

## Permissions

| Action | Roles |
|---|---|
| `workflows.more_staff.initiate` | PM (own property), admin, super_admin |
| `workflows.more_staff.fulfill` (link WOs to requests) | Recruiter (own property), admin, super_admin |
| `workflows.more_staff.decline` | Recruiter (own property), admin, super_admin |
| `workflows.more_staff.cancel_own` | The initiator (PM) |
| `workflows.more_staff.cancel_others` | Super_admin |

## Audit trail example

```
[09:14] Request submitted    - PM Jane → REQ-1247: 3 Housekeepers @ Marriott DT by 2026-06-15
                                 urgency=normal, reason="2 housekeepers on maternity leave"

[09:14] Notification sent    - System → Recruiter David: "New request"

[14:30] Placement linked     - Recruiter David → WO #4901 (Maria Lopez) linked to REQ-1247
                                 quantity_fulfilled: 0 → 1
[14:30] Status changed       - System → REQ-1247: submitted → in_progress
[14:30] Notification sent    - System → PM Jane: "1 of 3 Housekeepers placed"

[Day +3, 11:00] Placement linked - Recruiter David → WO #4912 (Carlos via transfer) linked
                                    quantity_fulfilled: 1 → 2
[Day +3, 11:00] Notification    - PM Jane: "2 of 3 Housekeepers placed"

[Day +5, 09:00] Placement linked - Recruiter David → WO #4925 (Anna, new hire) linked
                                    quantity_fulfilled: 2 → 3
[Day +5, 09:00] Status changed   - System → REQ-1247: in_progress → fulfilled
[Day +5, 09:00] Notification    - PM Jane: "All 3 Housekeepers placed. Request complete."
[Day +5, 09:00] Workflow complete
```

## Edge cases

| Case | Handling |
|---|---|
| PM submits with by_date in past | Block at validation — by_date must be >= today |
| PM submits quantity 0 or negative | Block at validation |
| Recruiter assigned to property changes (recruiter_to_property_transfer workflow) | Pending requests auto-reassign to new recruiter |
| Linked WO is later closed (e.g. contractor terminated) | quantity_fulfilled does NOT decrement — the placement happened. Request stays fulfilled (or whatever its final status was). PM can submit a new request if more staff still needed. |
| Linked WO is a temp assignment that expires | Same as above — placement counted at time of linking; subsequent WO closure doesn't unwind. |
| Recruiter links a WO that doesn't belong to the request's property | Block at validation — WO's property_id must match request's property_id |
| Multiple recruiters at the property (shared coverage) | Request routes to the assigned primary recruiter; admin can manually reassign if needed |
| PM is terminated/leaves property before fulfillment | Request stays open; new PM (or recruiter) can manage it; no auto-cancel |
| Recruiter creates WO but forgets to link to request | quantity_fulfilled stays at 0; PM thinks no staff placed. Mitigation: WO creation form prominently shows pending requests and prompts to link. Recruiter can edit WO later to add the link retroactively. |

## Reporting surfaces

- **PM dashboard:** "My open requests" (count + status mix)
- **Recruiter dashboard:** "Talent Needs queue" (sorted by urgency, overdue flag)
- **Admin reports:**
  - "Open requests by property" — operational pulse
  - "Average time to full fulfillment" (per property, per recruiter, per position)
  - "Declined requests" (with reasons — pattern analysis)
  - "Overdue requests" (count and aging)
  - "Fulfillment rate per recruiter" (fulfilled / total submitted)

## Related

- ADR-0021 — More Staff Request workflow (full decision)
- ADR-0011 — Original "more-staff creates a search task" decision
- ADR-0019 — Transfer + Temporary Assignment (placements via transfer or temp count toward fulfillment)
- `20-domain/workflows.md` — more_staff catalog entry
- `20-domain/work-orders.md` — `more_staff_request_id` field on WOs
- `10-architecture/permissions-matrix.md` — more_staff permissions
- `40-flows/transfer.md`, `40-flows/temporary-assignment.md` — related placement flows
