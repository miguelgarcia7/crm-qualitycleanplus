# Phase 03 — Work Orders + Time Tracking + Invoicing (core pipeline)

| Field | Value |
|---|---|
| Status | 🚧 In progress |
| Last updated | 2026-06-07 |
| Owner | Engineering |

Just-in-time plan for Phase 03 of `80-plan/roadmap.md` — the **money pipeline**. Builds on Phase 02 (Bible rates auto-fill work orders) and Phase 01 (identity/roles). Backend follows ADR-0025 (`app/Domain/<Context>`): new contexts **`WorkOrders`**, **`Time`**, **`Billing`**.

**Acceptance (core slice):** recruiter creates a WO (rates auto-filled from the Bible, overridable) → enters time for the week → live grid reflects it → recruiter sends the timesheet for approval → PM approves on QC Minute → a frozen, numbered invoice is generated → recruiter sends it (PDF). Decline path returns it for edits.

## Scope decisions (locked with owner)

- **Time input = manual entry** (recruiter grid) + a **seeder** for sample data. Device/QR/tablet/Sanctum clock-in → Phase 07.
- **Reverb** set up now; time-entry changes broadcast to the live grid.
- **Invoice PDF** via `barryvdh/laravel-dompdf`.
- **Core pipeline only.** Deferred to **Phase 03b**: invoice void/reissue; adjustments (catalog, applied, invoice adjustments, contractor charge schedules); Excel export; holiday-calendar bucketing; device clock-in.

## Anchored ADRs

ADR-0005 (rate snapshots on time entries) · ADR-0006 (invoice freeze) · ADR-0007 (staged timesheet approval) · ADR-0008 (time_entries + time_summaries split) · ADR-0009 (payroll periods first-class) · ADR-0025 (app/Domain).

## Increments

0. **Deps + infra** — this doc; `barryvdh/laravel-dompdf`; `install:broadcasting` (Reverb + Echo + pusher-js); `config/qcp.php` (invoicer + bucketing config); `.env.example` Reverb vars; Echo wired into `resources/js/admin/app.tsx`.
1. **Work Orders** (`app/Domain/WorkOrders`) — `work_orders` table; model + `WorkOrderStatus`/`WorkOrderSource`; policy; Create/Update/Close actions (rate-edit guard); controller + admin UI with Bible rate auto-fill; sidebar link; seeder (contractors + WOs).
2. **Time** (`app/Domain/Time`) — `payroll_periods`, `time_entries`, `time_summaries`; models + enums; `EnsurePayrollPeriods` command (scheduled); manual-entry actions + controller; `RecomputeTimeSummary` job (regular/OT/training buckets; holiday=0 v1); live grid UI; sample-time seeder.
3. **Reverb live updates** — `TimeEntrySaved` on private `property.{id}`; channel auth; grid subscribes via Echo.
4. **Timesheets** (`app/Domain/Billing`) — `timesheets` + `TimesheetStatus` state machine; Submit (recruiter) / Approve+Decline (PM on QC Minute); both-surface UI; notifications.
5. **Invoicing** (`app/Domain/Billing`) — `invoices` + `invoice_items`; `GenerateInvoice` (transaction, snapshots, `INV-YYYY-NNNNNN`); `SendInvoice` (dompdf PDF + notify); invoice UI.
6. **Tests + docs** — Pest across the pipeline; pint/stan/test/build green; update this doc + roadmap.

## v1 simplifications

- Holiday bucket always 0 (no holiday calendar until Phase 08).
- Training minutes paid at `pay_rate_snapshot`, not billed.
- Live grid refetches on broadcast (no granular cell patching).
- Invoice void/reissue columns exist on the table but the flow is Phase 03b.

## Verification

`composer require` + `install:broadcasting` ok → `migrate:fresh --seed` (sample property/contractors/WOs/time; summaries populated) → create WO (rates pre-fill) → manual entry recomputes summary, live grid updates via Reverb → Send for Approval → PM approves on `qcminute.test` → invoice generated (frozen, numbered, taxed) → Send Invoice (PDF) → decline reopens period → `composer test` + `composer stan` + `npm run build`/`types` green.

## Related

- `20-domain/{work-orders,time-tracking,timesheets,invoicing,adjustments}.md`
- ADR-0005/0006/0007/0008/0009, ADR-0025
- `80-plan/roadmap.md`, `80-plan/phase-02-property-bible.md`
