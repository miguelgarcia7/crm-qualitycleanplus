# Phase 09c — Audit log viewer

**Status: ✅ done (as built)**

## Goal

A read-only viewer over the Spatie activity log at `/admin/audit`, retiring the Phase 01
sidebar placeholder. The log was already written everywhere that matters — logins
(AppServiceProvider listener), property changes (`LogsPropertyActivity`), every workflow
action (`LogsWorkflowActivity`), import commit/rollback, applicant promotion/reversal,
final paychecks, invoice voids — it just had no UI.

## Decisions

- **Server-side pagination + filters** (`AuditLogController`) — the first page to do this,
  because the log grows without bound, so the whole table is never shipped to the client.
  The other ledger list pages followed in phase 09f; the policy is ADR-0029. 25/page,
  filters: description search, log name, event, subject type (options read from distinct
  values actually present).
- Route-gated `audit.activity_log.view` (super_admin, admin, office_manager — matrix §Audit).
- Subjects with a detail page (Person → People profile, Property → Property Bible) render as
  links; causers link to their People profile.
- Read-only by design: no editing, no deleting — the audit trail's value is immutability
  (ADR-0010). Legal-hold tooling (`audit.legal_hold.set`) remains separate.

## Out of scope / deferred

Date-range filter, CSV export, per-entry properties/diff drill-down (the `properties` JSON
column is captured but not yet rendered), retention pruning (ADR-0010 covers policy).
