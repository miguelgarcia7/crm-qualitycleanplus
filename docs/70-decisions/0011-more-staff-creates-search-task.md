# ADR-0011: "More Staff" Request Creates a Search Task

| Field | Value |
|---|---|
| Status | Accepted |
| Decided | 2026-05-21 |
| Owner | Product |
| Supersedes | — |
| Superseded by | — |

## Context

When a property manager needs more contractors (e.g. seasonal demand, banquet event, replacement for terminated worker), the existing process is informal — phone calls, texts, emails to the recruiter. Information gets lost.

The new system needs to formalize this as a workflow. The question is: what happens *after* the PM submits the request?

Three options were considered:

- **Notify only** — recruiter gets an alert, deals with it however they normally do
- **Auto-create a structured search task** — request lands in a "Talent Needs" queue with structured data (position, count, by-date)
- **Auto-post a job to the public site** — system generates a public job posting

## Decision

**Submitting a "more staff" request creates a structured search task in the recruiter's "Talent Needs" queue.**

The task carries:

- Property (set automatically from the PM's identity)
- Position (dropdown of positions active at that property)
- Quantity requested
- By-date (when staff is needed by)
- Notes (free text from the PM)
- Status: `open | in_progress | fulfilled | cancelled`
- Assigned recruiter (auto-set to the property's recruiter)
- Audit fields (initiator, created_at, fulfilled_at, fulfilled_by)

The recruiter sees the task on their dashboard, can update status, and marks `fulfilled` when staff is placed.

**Job posting to the public site is NOT automatic.** Posting publicly is a separate, deliberate decision by the recruiter (who may already have someone in their pipeline). If the recruiter decides to post, they do so manually through the back-office job postings UI.

## Consequences

### Positive

- PM requests are tracked, not lost
- Recruiter has a structured queue, not a chaotic inbox
- Audit trail captures every request and resolution
- Reporting becomes possible: "average time to fulfill a more-staff request," "open requests by property"
- The workflow engine handles state transitions and notifications uniformly with other workflows

### Negative

- Slightly more friction than "just text the recruiter" — requires PM to log in and fill a form
- Doesn't automate the actual sourcing (which is still the recruiter's judgment + relationships)
- A request that's never fulfilled lingers in the queue (mitigated by dashboard aging indicators)

### Implementation requirements

This is one instance of the workflow engine (see `20-domain/workflows.md`). Specific to this workflow:

- Workflow type: `more_staff_request`
- Initiator role: `property_manager` (own property only)
- Approver role: none (no approval — recruiter just acts on it)
- Action on completion: status → `fulfilled` (with optional list of contractor IDs placed)
- Audit log entries on creation, status change, fulfillment

Dashboard:

- PM dashboard: "My open requests" widget
- Recruiter dashboard: "Talent needs" queue widget

UI:

- PM "Request Staff" button on property page → modal with position, quantity, by-date, notes
- Recruiter task page: queue, filter, update status, add notes, link to fulfilled contractors

## Alternatives considered

### A. Notify only

Rejected. Loses tracking. Recruiter can't see request history. Reporting impossible.

### C. Auto-post to public site

Rejected as default. Posting is a public commitment with cost (recruiter time, hotel time interviewing). PMs sometimes need staff who are already in QCP's bench, not new hires. Forcing a public post on every request would be wrong. Manual post-from-task remains an option for the recruiter.

## Related

- `20-domain/workflows.md` — the generalized workflow engine
- `40-flows/more-staff-request.md` — step-by-step flow
- `10-architecture/permissions-matrix.md` — `workflows.more_staff.*` permissions
