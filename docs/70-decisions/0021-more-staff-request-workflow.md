# ADR-0021: More Staff Request Workflow

| Field | Value |
|---|---|
| Status | Accepted |
| Decided | 2026-05-21 |
| Owner | Product (David) + Engineering |
| Supersedes | — (extends ADR-0011 which originally established that more-staff creates a search task) |
| Superseded by | — |

## Context

ADR-0011 established that "more staff" requests from property managers create a structured search task in the recruiter's "Talent Needs" queue (not an automatic job posting). It locked in the basic shape but deferred operational details:

- Form fields the PM fills out
- How fulfillment is tracked (counter vs. linked work orders)
- Partial fulfillment handling
- By-date and urgency mechanics
- Recruiter decline path
- Cancellation rules
- Notification cadence

This ADR locks those decisions for v1 implementation.

## Decision

A `more_staff_request` workflow with these properties:

### Form (PM-side, in QC Minute)

| Field | Type | Required |
|---|---|---|
| Position needed | dropdown (positions active at PM's property) | yes |
| Quantity | positive int | yes |
| By-date | date | yes (when staff needed by) |
| Urgency level | enum: `low` / `normal` / `high` / `urgent` (default `normal`) | yes |
| Reason / context | text | yes |
| Notes | text | optional |

**One position per request.** If PM needs multiple positions, they submit multiple requests. Matches the supply request pattern.

### Routing

On submit:

- `more_staff_request` row created (status=`submitted`)
- Routed to the recruiter assigned to the property (via property's recruiter assignment)
- Recruiter sees the request on their "Talent Needs" dashboard widget
- Notification sent to recruiter

### Tracking fulfillment

Each work_order created in response to this request is linked via a new nullable FK on `work_orders`:

```
work_orders
  ...
  more_staff_request_id (nullable FK to more_staff_requests)
```

The link is set when recruiter creates a WO at the property AND opts to associate it with a pending request. UI affordance: recruiter sees pending requests for that property when creating a WO and can tick "Link to: Request #X (3 of 3 placed)."

This applies regardless of placement type:
- New hire (newly promoted applicant → new WO)
- Transfer from another property (transfer workflow creates new WO at this property)
- Temp assignment (temporary_assignment workflow creates temp WO at this property)

### Partial fulfillment

`quantity_fulfilled` is computed (or stored + updated):

```
quantity_fulfilled = COUNT(work_orders WHERE more_staff_request_id = this.id)
```

Status logic:

- `submitted` — newly created, no placements yet
- `in_progress` — at least one placement linked but `quantity_fulfilled < quantity_requested`
- `fulfilled` — `quantity_fulfilled >= quantity_requested` (system auto-transitions when threshold reached)
- `declined` — recruiter explicitly declined (with reason)
- `cancelled` — PM or super_admin cancelled

UI shows progress: "1 of 3 fulfilled" badge on the request card.

### By-date handling

The system does NOT auto-close requests past their by-date. Instead:

- Dashboard surfaces "Overdue requests" widget on both PM and recruiter views
- Visual indicator on the request card (e.g. red badge "Overdue by 3 days")
- Recruiter still expected to act; PM can re-submit / escalate if needed

No automatic expiry. Status stays `in_progress` (or whatever it was) until manual action.

### Recruiter decline

When the recruiter determines the request cannot be fulfilled (no candidates available, position not viable, etc.):

- Recruiter clicks "Decline" on the request
- Required text reason
- Status → `declined`
- Notification to PM with reason
- PM can re-submit later (new request, no system block)

### Public job posting integration

A convenience button on the recruiter's task view: **"Create Job Posting from this Request"**

- Pre-fills the job posting form with: position, location, by-date as posting end_date, request context
- Recruiter chooses to publish to the public site or not
- This is a UI shortcut, not an automatic step

### Cancellation

| Action | Who |
|---|---|
| Cancel own pending request | PM (initiator) — required reason |
| Decline | Recruiter (assigned) — required reason |
| Cancel any request | Super_admin |

PM cancellation closes the workflow with status=`cancelled`. Hours fulfilled (placements linked) stay — those work orders remain valid; they just no longer satisfy a pending request.

### Notifications

| Event | Recipient | Channels |
|---|---|---|
| Request submitted | Recruiter | Mail + in-app |
| Placement linked (partial fulfillment) | PM | In-app |
| Fully fulfilled | PM | Mail + in-app |
| Declined | PM (with reason) | Mail + in-app |
| Cancelled by PM | Recruiter | In-app |
| Overdue (24h past by-date) | PM + Recruiter | In-app |

Per-placement notifications keep PM informed of progress without being chatty.

### Urgency level mechanics

`urgency` field drives:

- **Default sorting** on recruiter's dashboard queue (urgent → high → normal → low; within each, by oldest first)
- **Visual indicator** on request card (color or icon)
- **Reporting** ("urgent requests open >24h" surfaces management visibility)

No automatic escalation rules; informational signal only.

## Consequences

### Positive

- PM has a clear, structured way to ask for more staff
- Recruiter has a queryable queue of needs with clear prioritization
- Partial fulfillment tracked naturally (placements linked individually)
- Decline path keeps requests honest (no zombie open requests)
- Urgency level helps recruiter prioritize without forcing escalation rules
- Optional public job posting integration without coupling them
- Reports become possible: time-to-fulfill, fulfillment rate, declined-request analysis

### Negative

- Recruiter must remember to link WOs to requests (manual step). If they forget, fulfillment counter doesn't advance and PM sees "0 of 3" even after staff placed.
  - Mitigation: when creating a WO at a property with pending requests, the form prominently asks "Link to a pending request?" — hard to miss.
- Auto-overdue surfacing might cause noise if PM submits frequent requests
- Multi-position needs require multiple submissions (mild PM friction; acceptable for v1)

### Implementation requirements

Schema:

```
more_staff_requests
  - id
  - workflow_id (FK to workflows)
  - property_id (FK)
  - position_id (FK)
  - quantity_requested (int)
  - quantity_fulfilled (int, default 0 — auto-updated by trigger or job when WOs linked/unlinked)
  - by_date (date)
  - urgency: enum (low | normal | high | urgent)
  - reason (text)
  - notes (text, nullable)
  - status: enum (submitted | in_progress | fulfilled | declined | cancelled)
  - initiated_by (FK to people)
  - assigned_recruiter_id (FK to people)
  - declined_at, declined_by, decline_reason (nullable)
  - cancelled_at, cancelled_by, cancel_reason (nullable)
  - fulfilled_at (datetime, nullable — set when quantity_fulfilled first reaches quantity_requested)
  - created_at, updated_at
```

Schema addition to `work_orders`:

```
- more_staff_request_id (nullable FK to more_staff_requests)
```

Logic:

- WO creation: form offers "Link to pending more-staff request" dropdown showing this property's open requests
- WO linkage triggers `more_staff_requests.quantity_fulfilled` increment + status check + PM notification
- WO closure (e.g. transfer to elsewhere) DOES NOT decrement `quantity_fulfilled` — the placement happened, even if temporary
- Daily job `CheckMoreStaffRequestsOverdue` — flags requests past by_date for dashboard surfaces
- Auto-transition: when quantity_fulfilled crosses threshold, status → fulfilled + fulfilled_at set

Permissions:

- `workflows.more_staff.initiate` — PM (own property), admin, super_admin
- `workflows.more_staff.fulfill` — recruiter (own property), admin, super_admin (this covers linking placements)
- `workflows.more_staff.decline` — recruiter (own property), admin, super_admin
- `workflows.more_staff.cancel_own` — PM (own request)
- `workflows.more_staff.cancel_others` — super_admin

## Alternatives considered

### A. Multiple positions per request

Rejected. Matches supply-request pattern (one item per request). Each position is operationally distinct (different rate, different pipeline). PM can submit multiple in quick succession.

### B. No tracking of fulfillment — just a status flag

Rejected. Linked tracking provides real value for reporting ("which WOs came from which PM asks?") and partial-fulfillment visibility. Cost is small (one nullable FK).

### C. Separate "placements" table

Rejected as overkill. The FK on work_orders captures the relationship; no need for a separate table.

### D. Auto-expire requests past by-date

Rejected. Loses visibility. Better to flag and keep visible until manually acted on.

### E. Auto-create public job postings from requests

Rejected per ADR-0011. Posting is a deliberate recruiter decision (cost, exposure, candidate availability). Manual button is the right balance.

### F. Auto-escalate urgent requests to admin after N hours

Rejected for v1. Informational signal is enough; explicit escalation policies can be added later.

## Related

- `40-flows/more-staff-request.md` — step-by-step flow
- `20-domain/workflows.md` — more_staff catalog entry
- `20-domain/work-orders.md` — `more_staff_request_id` field
- ADR-0011 — Original more-staff design (this ADR builds on it)
- ADR-0019 — Transfer + temp assignment (placements via transfer/temp also count)
- `10-architecture/permissions-matrix.md` — more_staff permissions
