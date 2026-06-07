# ADR-0007: Staged Timesheet Approval

| Field | Value |
|---|---|
| Status | Accepted |
| Decided | 2026-05-21 |
| Owner | Product (David, Miguel) |
| Supersedes | — |
| Superseded by | — |

## Context

In the existing QC Minute system, timesheets are generated hourly by a scheduled job, and the system automatically notifies the property manager when a week closes. The PM approves through the UI, and on approval an invoice is auto-generated and the PM is auto-notified.

This auto-everything flow has caused friction:

- Recruiters can't review the timesheet before the PM sees it
- Property managers receive notifications at unpredictable times
- There's no opportunity to add adjustments or fix errors before submission
- Invoice notifications can't be timed (e.g., batched and sent Friday)
- Some hotels want recruiter validation as a quality gate before the PM ever sees the data

The new system needs explicit human checkpoints between weekly time data and the final invoice notification, while keeping the path frictionless for the common case (no errors, approved on first submission).

## Decision

The timesheet lifecycle is a state machine with three human-triggered transitions and one automatic transition.

```
                                ┌──── PM clicks "Decline" ────┐
                                │       (+ required reason)    │
                                ▼                              │
draft ──── recruiter clicks ──→ pending_approval           declined
           "Send for Approval"        │                        │
                ▲                     │                        │
                │                     │ PM clicks "Approve"    │
                └─── recruiter edits, │                        │
                     clicks "Send for │                        │
                     Approval" again ─┴────────────────────────┘
                                      │
                                      ▼
                                  approved
                                      │
                                      │ (automatic) invoice generated,
                                      │ frozen, snapshotted
                                      ▼
                                  invoiced
                                      │
                                      │ recruiter clicks
                                      │ "Send Invoice to Property"
                                      │ (optional, on demand)
                                      ▼
                                invoice_sent
```

### Key rules

- **Only the recruiter** can transition `draft` → `pending_approval`. There is no automatic submission.
- **Decline is whole-timesheet**, not per-line. The PM provides a required free-text reason and an optional category.
- **Invoice generation is automatic** on `approved`. The invoice is frozen and snapshotted in the same transaction as the timesheet state change. There is no separate "generate invoice" action.
- **Invoice notification is decoupled** from invoice generation. The invoice exists as `invoiced` but `notification_sent_at IS NULL` until a recruiter explicitly clicks "Send Invoice to Property."
- **PMs have a live read-only view of the week in progress.** They can see who is clocked in and the day-by-day grid throughout the week, but they cannot take action until the timesheet is formally submitted.

## Consequences

### Positive

- Recruiter has a final review opportunity before the PM sees anything official
- Errors and adjustments can be applied before submission, reducing decline cycles
- Invoice notification timing is under operational control
- PM has live transparency (no "is this final?" confusion) without premature action surface
- Each transition has a clear audit row (who, when, why)
- The state machine is shared across all clock-in properties; import-only properties use a parallel simpler flow that skips `pending_approval`

### Negative

- More clicks for the recruiter (estimated 1–2 per timesheet per week — acceptable)
- Timesheets and invoices can sit in `approved` or `invoiced` indefinitely if no one acts. Recruiter dashboard must surface these so they don't fall through the cracks
- Edge case: recruiter never sends a timesheet for approval → property is unbilled. Mitigation: "stale draft timesheets" dashboard widget; optional auto-reminder after N days
- Edge case: PM never logs in and recruiter never sends invoice notification → invoice exists but the PM doesn't know. Decision: this is acceptable; recruiter dashboard counter is the safety net

### Implementation requirements

Schema:

- `timesheets.status` enum: `draft | pending_approval | declined | approved | invoiced | invoice_sent | voided`
- `timesheets.sent_for_approval_at`, `sent_for_approval_by`
- `timesheets.approved_at`, `approved_by`
- `timesheets.declined_at`, `declined_by`, `decline_reason` (text, required when declined), `decline_category` (nullable enum)
- `invoices.notification_sent_at`, `notification_sent_by`, `notification_recipient`

Dashboards:

- Recruiter: "Approved invoices awaiting notification" (count + $ value)
- Recruiter: "Declined timesheets awaiting your action" (count + age)
- Recruiter: "Draft timesheets — overdue" (count, oldest age)
- PM (QC Minute): "Timesheets awaiting your approval" (count)

Audit:

- Every state transition writes an `activity_log` row with the causer, timestamp, old state, new state, and reason if applicable

## Alternatives considered

### A. Auto-send after N hours of week closing (current QC Minute behavior)

Rejected. Removes the recruiter's quality gate. Multiple hotels have asked for human-in-the-loop validation before the PM sees the data.

### B. Per-line decline (PM rejects specific contractor rows)

Rejected for v1. Adds significant state complexity (a single timesheet would carry mixed approved/declined lines requiring separate re-submission). Whole-timesheet decline with required text reason is simpler and forces clearer communication between PM and recruiter. Reconsider if usage shows it's needed.

### C. Auto-send invoice notification after approval

Rejected. Some hotels want invoices batched (e.g., all weeks of a month sent on the 1st), some want specific recipients, and at least one wants no email at all (PM logs in to download). A manual trigger handles all cases without per-property configuration.

### D. Multi-step approval chain (PM → assistant GM → GM)

Rejected for v1 — no client has requested it. The generalized workflow engine being built (see `20-domain/workflows.md`) supports multi-step approval as a generic capability, so this can be added later for specific timesheet types without re-architecture.

## Related

- `20-domain/timesheets.md` — state machine, transitions, validations
- `20-domain/invoicing.md` — generation, freeze, snapshotting, void/reissue
- `40-flows/timesheet-approval.md` — full step-by-step flow with screen references
- `40-flows/invoice-generation.md` — what happens at the `approved` → `invoiced` transition
- `50-ui/qc-minute-pm.md` — PM dashboard surfaces (live grid, pending queue, history)
- ADR-0006 — Invoice freeze + void/reissue (referenced by automatic invoice generation)
- ADR-0008 — Time entries and summaries (the data the timesheet aggregates)
