# Build Roadmap

| Field | Value |
|---|---|
| Status | Living document — updates as we build |
| Last updated | 2026-06-10 (through Phase 09) |
| Owner | Product + Engineering |

The phase sequence for building the new unified system. Each phase is a coherent deliverable. Phases ship in order — later phases depend on earlier ones.

Estimates are calendar-week ranges assuming one engineer working steadily. Adjust for team size + part-time work.

## Phase summary

| # | Phase | Estimate | Status |
|---|---|---|---|
| 01 | Foundation: schema, identity, domain routing | 2-3 weeks | ✅ Done (see `phase-01-foundation.md`) |
| 02 | Property Bible | 3-4 weeks | ✅ Done (see `phase-02-property-bible.md`) |
| 03 | Work orders + time tracking + invoicing | 4-5 weeks | ✅ Done — core pipeline (see `phase-03-work-orders-time-invoicing.md`); 03b deferrals noted |
| 04 | Workflow engine + first workflows (incl. unified supply_request) | 3-4 weeks | ✅ Done — 04a (engine + inventory + supply request); 04b-i (transfer + temp + pay-increase) `phase-04b-wo-lifecycle-workflows.md`; 04b-ii (termination) `phase-04b-ii-termination.md`; 04b-iii (more-staff + change-personal-info) `phase-04b-iii-requests.md`. PTO deferred to Phase 08 |
| 05 | Import flow | 1-2 weeks | ✅ Done (see `phase-05-import.md`) |
| 06 | Dashboards | 2-3 weeks | ✅ Done (see `phase-06-dashboards.md`) |
| 07 | Field check-in flows (recruiter + contractor QR) | 2-3 weeks | ✅ Done — 07a contractor QR (`phase-07a-contractor-clock-in.md`) + 07b recruiter visits (`phase-07b-recruiter-visits.md`) + 07c tablet/Sanctum device (`phase-07c-tablet-clock-in.md`) |
| 08 | KB + Applicants + Job postings + PTO | 3-4 weeks | ✅ 08a PTO (`phase-08a-pto.md`); 08b-i marketing + public job board + applications (`phase-08b-i-marketing-applications.md`); 08b-ii back-office recruiting (`phase-08b-ii-backoffice-recruiting.md`); 08c knowledge base (`phase-08c-knowledge-base.md`) |
| 09 | Reports + materialized rollups + exports | 2-3 weeks | ✅ Done — `phase-09-reports.md` (rollup layer per ADR-0028, report catalog, Excel/PDF exports, timesheet history; subscriptions deferred) |
| 09b | People directory + person profiles | 1 week | ✅ Done — `phase-09b-people-directory.md` (contractor/staff directory, person profile w/ work orders, hours, adjustments, PTO, history; recruiter own-scoping via PersonPolicy) |
| 10 | Cutover: data migration + parallel run + retire legacy | 3-4 weeks | Not started |

**Total estimate: 25-35 weeks** (~6-8 months solo, much less with team).

## Phase 01 — Foundation

**Goal:** Stand up the empty app with the right structure so every later phase builds on solid ground.

**Deliverables:**
- Laravel 13 skeleton with proper directory structure
- MySQL database, Redis, S3 (or local equivalents)
- Multi-domain route grouping (marketing, QC Minute, back office, device) per `10-architecture/domain-routing.md`
- `people` table + Spatie roles + permissions seeded per matrix
- Authentication on the app domains (Fortify + custom domain middleware)
- Sanctum for `/device/*` token path
- Activity log integration (Spatie)
- Soft delete + legal_hold trait
- Base layouts: React/Inertia for the app surfaces (Paces/Minute theme); a Blade layout for the marketing surface
- CI pipeline (Pest + Pint + static analysis)
- Local dev environment documented

**Schema migrations:** users (→ people), roles, permissions, sessions, password_resets, personal_access_tokens, activity_log, devices.

**Acceptance:** A super admin can log in to either domain, see an empty dashboard, navigate to a "Settings" page. Activity log records the login.

## Phase 02 — Property Bible

**Goal:** The central source-of-truth feature. Properties, departments, positions, rates, contracts.

**Deliverables:**
- `properties` table + CRUD
- `property_departments` per-property structure
- `positions` global catalog
- `property_position_rates` with effective dates
- `contracts` upload + structured metadata (decided shape from `90-open/contracts-data-model.md` once resolved)
- Contract expiration alert (30/14 day scheduled job)
- `property_assignments` (recruiters and PMs to properties)
- Bible UI in back office (per-section tabs)
- Permission gating per matrix

**Acceptance:** Office manager can create a property, add departments, add positions with rates, upload a contract. Recruiter can see properties they're assigned to. Contracts are visible only to ownership + payroll.

## Phase 03 — Work orders + time tracking + invoicing

**Goal:** The core money pipeline. Contractors get assigned, time accumulates, gets approved, becomes invoices.

**Deliverables:**
- `work_orders` table + CRUD (bible-driven rate auto-fill, can override)
- `payroll_periods` with auto-creation scheduled job
- `time_entries` + `time_summaries`
- Bucketing service (regular / OT / holiday / training)
- `RecomputeTimeSummary` background job
- Device clock-in / clock-out endpoints
- Live weekly grid view (read-only for PMs, editable for recruiters)
- `timesheets` state machine
- Recruiter "Send for Approval" → PM "Approve / Decline" → invoice generation flow
- `adjustment_items` catalog + `time_entry_adjustments` apply path
- Invoice freeze + PDF rendering + "Send Invoice to Property"
- Invoice void + reissue

**Acceptance:** End-to-end happy path: device clock-in, recruiter submits, PM approves, invoice generated, recruiter sends invoice. Decline path works. Adjustments flow through to invoice + payroll.

## Phase 04 — Workflow engine + first workflows

**Goal:** Generalized engine for all approval/task flows, including the unified supply request system covering office supplies, uniforms, and equipment.

**Deliverables:**
- `workflow_definitions`, `workflows`, `workflow_steps` tables
- Engine that walks steps based on definition
- Step types: approval, action, notification
- Assignment by specific person, by role, by relationship
- Dashboard surface ("my pending tasks")
- **Unified inventory system** (per ADR-0012):
  - `categories`, `items`, `item_variants` tables
  - `stock_movements` unified table (PO receipt, direct receipt, count correction, issuance, return)
  - `purchase_orders` + `purchase_order_items` (minimal v1)
  - `equipment_assignments`
  - `contractor_charge_schedules` + entries (see ADR-0014; covers uniforms, name tags, any contractor-charged item)
  - Browse Inventory page (office manager + super admin)
  - Inventory dashboard widgets (low stock, pending requests, approvals)
- First workflows ported/built:
  - PTO request (W-2 staff)
  - Termination (recruiter / HR initiates; includes Recover Equipment step)
  - Transfer (recruiter)
  - **Supply request** (unified — handles Office Supplies, Uniforms, Equipment; existing-item + new-item paths)
  - More-staff request (PM → recruiter task)
  - Pay-increase request (PM → recruiter → new WO)
  - Recruiter-to-property transfer (admin)
  - Change-personal-info request (contractor/W-2 → HR)

**Acceptance:** Workflow engine handles each of the 8 workflows end-to-end. Supply request flow exercises both existing-item and new-item paths across all three default categories. Uniform completion correctly generates deduction schedule. Equipment completion creates assignment. Termination recovers equipment.

## Phase 05 — Import flow

**Goal:** Excel-based hour import for non-clock-in hotels.

**Deliverables:**
- Import wizard UI (upload → match → resolve → adjust → commit)
- `import_batches` + `import_batch_rows` tables
- File parsing (maatwebsite/excel)
- Contractor matching by external_id
- WO auto-creation on first import
- Rate conflict resolution
- Adjustment step
- Commit transaction: time entries + WO + timesheet (auto-approved) + invoice
- Re-import (void + reissue) and rollback

**Acceptance:** Office manager uploads an Excel file, resolves unmatched rows, commits, gets a generated invoice. Re-import voids and replaces cleanly.

## Phase 06 — Dashboards

**Goal:** Role-specific dashboards that surface what each person needs to see.

**Deliverables:**
- Recruiter dashboard: assigned properties, contractors, draft timesheets, approved invoices awaiting notification, pending workflows, open uniform requests, expiring documents, currently clocked in
- PM dashboard: live grid, pending approvals, invoice history
- Office manager dashboard: property-level overviews, workflow queue, import batches, expiring contracts
- HR dashboard: applicant pipeline, onboarding tasks, terminations in progress, change-info requests
- Payroll dashboard: contracts expiring, period closures, deduction schedules, void/reissue queue
- Super admin: cross-cutting health view, audit search, impersonation entry
- Contractor dashboard (QC Minute): own hours, paychecks, profile, uniform balance, KB

**Acceptance:** Each role sees a dashboard tailored to their work. Widgets are live where appropriate. Permission gating verified.

## Phase 07 — Field check-in flows (recruiter + contractor QR)

**Goal:** Two parallel browser-based check-in flows — recruiter visit tracking (operational) and contractor QR clock-in (billable). Both use GPS + selfie. Per ADR-0017.

**Deliverables:**
- `field_visits` table for recruiter check-ins
- Extensions to `time_entries`: `clock_method`, GPS coords, selfie file refs
- `properties.latitude`, `longitude`, `geofence_radius_meters` columns + geofence math
- **Recruiter flow:**
  - Floating action button (FAB) on Back Office mobile
  - Browser geolocation + camera capture
  - Auto-detect property via GPS / fallback to picker
  - "Forgot to check out" handling (explicit prompt, late_close flag)
  - Visit history per recruiter and per property
- **Contractor QR flow:**
  - QR generator per property (static URL with property_id)
  - Phone-number-based contractor lookup
  - Work order picker (handles multi-WO contractors at same property)
  - GPS geofence validation (block if outside)
  - Selfie required on every clock event
  - Lunch break = two separate time_entries (per ADR-0017)
- Tablet flow continues to function as backup (no change to existing Sanctum tablet path)
- Reports: Recruiter Activity, Stale Open Visits, Off-Geofence Visits, Late-Closed Visits

**Acceptance:**
- Recruiter on phone can check in (GPS + selfie captured), navigate to next property and be prompted about prior open visit, check out
- Contractor scans property QR, enters phone, picks work order, GPS verified, selfie captured, time_entry created
- Outside-geofence clock-in blocked with clear message
- All flows degrade gracefully when GPS or camera permissions denied
- Reports show visit/clock patterns; off-geofence cases flagged

**Estimate:** 2-3 weeks (was 1-2; expanded to cover both flows)

## Phase 08 — KB + Applicants + Job postings + PTO

**Goal:** Port the legacy back-office concerns that aren't core to time/money.

**Deliverables:**
- KB authoring + reading (port from legacy QCP CRM)
- KB role-based visibility
- KB versioning + categories + tags + attachments + feedback
- Applicants table + intake form fields
- Public job postings endpoint (consumed by marketing site)
- Public application endpoint (creates `person` with status = applicant)
- Applicant → contractor promotion workflow (uses Phase 04 engine)
- **PTO system** (per ADR-0016) — tier-based accrual, three buckets (vacation/scheduled/unscheduled), hire-anniversary cycles, no rollover, Admin/HR approval (HR cannot self-approve), submission-time balance deduction
- PTO scheduled job for tenure crossings + anniversaries
- PTO admin views (balance management, manual adjustments)
- "My PTO" dashboard widget for W-2 employees

**Acceptance:** Articles can be authored, published, role-gated, and viewed by appropriate users including contractors. Applicants flow from public form to recruiter inbox to contractor. PTO requests submit (deduct from available immediately), Admin/HR approve, balances refresh on hire anniversary, tier crossings auto-grant.

## Phase 09 — Reports + materialized rollups + exports

**Goal:** Best-in-class reporting on top of pre-aggregated data.

**Deliverables:**
- Materialized rollup tables (daily property, weekly position, monthly revenue)
- Background jobs that maintain them
- Report catalog UI (back office)
- Standard reports: gross income, payouts, revenue, by property, by position, by week, by month, by year
- Excel exports
- PDF exports (for invoices, payroll)
- Report subscriptions (optional — scheduled emails)

**Acceptance:** Office manager pulls a "revenue by property by month" report; it loads in <1 second; Excel export works. Payroll pulls a "payouts by contractor" report for the current period.

## Phase 10 — Cutover

**Goal:** Migrate data from QC Minute + QCP CRM into the new system; retire legacy.

**Deliverables:**
- Migration scripts: legacy QC Minute → new schema
- Migration scripts: legacy QCP CRM → new schema (where overlap, merge intelligently — properties, people)
- Cutover plan with rollback path
- Parallel-run validation period (read-only on legacy, writes go to new)
- DNS swap / app retirement
- Legacy system archival (read-only copy retained for N years)

**Acceptance:** All operational data present in the new system. Two weeks of clean running. Legacy systems shut down.

## What's NOT in this roadmap

- Native mobile apps (browser only for v1)
- SaaS multi-tenancy (decided no — ADR-0002)
- Marketing *redesign* (the marketing pages move in-codebase as a Blade surface per ADR-0023, but keep current content/design — porting, not redesigning)
- Accounting / GL integration beyond invoices
- Two-factor authentication (v2 candidate)
- Native push notifications (v2 candidate)
- Service requests for office supplies (v2 candidate — mentioned in Email 3)
- Real-time inventory thresholds / reordering (v2 candidate)

## Risk register

| Risk | Likelihood | Mitigation |
|---|---|---|
| Underestimating phase 03 (time tracking is hard) | High | Build feature flags so partial flows can ship; reserve buffer week |
| Workflow engine over-engineering | Medium | Build to current 8 workflows, defer abstractions until pattern emerges |
| Import file shapes vary by hotel | Medium | Build the parser with header detection from day one; expect to add format variants |
| Data migration uncovers undocumented edge cases in legacy | High | Migration script is its own phase (10); allocate explicit time |
| Browser geolocation accuracy varies | Low-medium | Default fence is generous (300m); allow per-property override |
| Permissions misconfiguration leaks data | Medium-high | Permission matrix is locked early; policy tests cover each role × resource |

## Related

- `00-context/vision.md` — what we're building
- `70-decisions/` — decisions that shape each phase
- `80-plan/phase-XX-*.md` — per-phase detailed plans (added as we approach each phase)
- `90-open/` — open items that can block specific phases
