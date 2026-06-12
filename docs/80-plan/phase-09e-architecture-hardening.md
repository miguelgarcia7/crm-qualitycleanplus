# Phase 09e — Pre-cutover architecture hardening

| Field | Value |
|---|---|
| Status | ✅ Done (Pest green; Pint + Larastan clean, build + types clean, `migrate:fresh --seed` clean; indexes + FK rules verified on MySQL via information_schema). |
| Last updated | 2026-06-12 |
| Owner | Engineering |

## Context

A full architecture + schema audit (2026-06-12, pre-cutover) confirmed the overall design
is sound — money as integer cents with frozen snapshots, acyclic domain contexts, UTC +
per-property timezones, consistent enums/polymorphics — and produced a short hardening
list. This phase executes items 1–5. **Because the app is pre-live and every gate runs
`migrate:fresh`, the original migrations were edited in place** rather than stacking
fix-up migrations (also sidesteps SQLite's inability to drop FKs in tests).

## As built

1. **Hot-path composite indexes** (edited into the original create migrations):
   - `time_entries`: `[person_id, payroll_period_id]`, `[property_id, payroll_period_id]`
     (per-person / per-property period rollups).
   - `invoices`: `[property_id, status]` (property invoice lists).
   - `notifications`: `[notifiable_type, notifiable_id, read_at]` (the bell's per-user
     unread count).
   - `activity_log`: `created_at` (audit viewer pages newest-first over unbounded growth).

2. **Restrict FKs on financial/audit records** (cascade → restrict):
   - `invoices.timesheet_id` — an invoice is an immutable financial record; voiding is
     the flow (ADR-0006). A timesheet hard-delete can no longer silently take an invoice.
   - `termination_records.workflow_id` — the termination record is the durable audit
     trail; deleting the workflow can no longer take the record with it.
   - Verified: nothing in app/tests hard-deletes timesheets or workflows.

3. **Email-unique-across-soft-deletes is now a documented decision** (people-lifecycle.md
   "Email & soft deletes" + migration comment): one identity per human; rehire reuses the
   row. Two code paths verified/fixed to match:
   - Profile validation already checks the whole table (`Rule::unique` ignores the
     soft-delete scope) — clean validation error, matching the DB constraint. ✓
   - **Bug fixed:** public application intake (`SubmitApplication::resolvePerson`) looked
     up email *excluding* trashed rows, then created — a soft-deleted person re-applying
     would 500 on the unique constraint. Now `withTrashed()` + restore. Regression test
     added. Noted for the future anonymization build: `people.email` must become nullable
     (spec clears it; column is currently NOT NULL).

4. **Surface-aware TopBar** — new shared Inertia prop `surface`
   (`backoffice`/`qcminute`); the user dropdown now renders **My Info → `/my-info`** on
   QC Minute instead of the hardcoded `/admin/settings/profile` (which 404'd for PMs).
   Asserted in NotificationTest on both surfaces.

5. **Dead code removed** — `routes/settings.php` (orphaned starter-kit file, never
   mounted; live settings routes are in backoffice.php) and
   `Settings/TwoFactorAuthenticationController` (referenced only by that file; Fortify's
   2FA backend is unaffected, the deferred settings UI will bring its own controller).

## Second pass — migration consolidation (same day)

All 12 add-column/alter migrations were folded into their create migrations (owner call:
pre-live, every refresh is `migrate:fresh --seed`, so migration history has no value yet).
Now **one file per table**, with three deliberate exceptions where a constraint must wait
for a later table:

- **people ↔ files** (circular: `files.uploaded_by → people`): the six file-pointer
  columns on people (avatar, ID front/back, I-9, W-9, agreement) are declared
  unconstrained in create_people; create_files promotes them to real FKs.
- **timesheets ↔ invoices** (circular): `timesheets.invoice_id` unconstrained in
  create_timesheets; promoted in create_invoices.
- **work_orders → more_staff_requests** (forward reference):
  `work_orders.more_staff_request_id` is added by create_more_staff_requests.
- create_people_external_ids was renumbered after create_properties so its property FK
  could be declared inline.

**Verified equivalent**: information_schema snapshot (1,329 facts — every column
type/nullability/default, FK delete rule, and index column order) diffed before vs after —
byte-for-byte identical. `migrate:fresh --seed` clean; full suite green on SQLite.

## Deferred from the audit (deliberately)

Activity-log retention policy (decide before production data accumulates); queueing
notifications/PDF/Excel (volume doesn't warrant it yet); ImportController/ReportController
extraction (post-launch refactor); authorization-style consistency sweep (route `can:` vs
policies vs inline — all paths verified guarded, consistency is a nice-to-have); live-grid
polling fallback when Reverb is down (runbook note for cutover instead).

## Related

`phase-09d-notifications.md`; `20-domain/people-lifecycle.md`; `20-domain/invoicing.md`
(ADR-0006 voiding); `80-plan/phase-10` cutover prep.
