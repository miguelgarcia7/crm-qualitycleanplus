# Vision

| Field | Value |
|---|---|
| Status | Accepted |
| Last updated | 2026-05-21 |
| Owner | Product (David, Miguel) |

## What we're building

A unified system for **Quality Cleaning Plus (QCP)** — a commercial cleaning staffing company that places contractors at hotels and other commercial properties.

The system replaces two legacy applications (**QC Minute** and **QCP CRM**) with a single Laravel 13 application accessed through two domains:

- **QC Minute** (`qcminute.com`) — time tracking, timesheets, invoices. Used by property managers and contractors.
- **QCP Staffing back office** (`backoffice.qcpstaffing.com`) — all internal operations. Used by recruiters, office managers, HR, payroll, and super admins.

Both domains read and write to the **same MySQL database** through the **same Laravel codebase**. The domain split is a UX and access boundary, not a data isolation boundary.

The **public marketing site** (`qualitycleanplus.com`) is part of this same codebase, served as a server-rendered **Blade** surface (for SEO) with its own asset bundle. It hosts marketing pages, job listings, and the public job application form — which writes applicants directly to the shared database, no cross-app API. See ADR-0023.

## Why we're rebuilding

The legacy systems work, but they've accumulated friction that limits what QCP can do operationally:

- **Two separate databases** mean data lives in two places. A property exists in both apps. A contractor's employment lifecycle is split across systems. Reports require cross-system joins that don't exist.
- **The Property Bible doesn't exist yet.** Rates, departments, contracts, and decision-maker contacts live in spreadsheets, emails, and people's heads. Mistakes are frequent. Onboarding new staff takes weeks because there's no canonical reference.
- **The timesheet → invoice flow runs on automation that removes human judgment** at the wrong moments. Property managers receive unexpected notifications. Recruiters can't catch issues before they reach the PM. Invoices send before anyone reviews them.
- **There's no workflow engine.** Each business process (termination, transfer, uniform request, pay increase) is one-off or paper-based. Information loss is constant.
- **Contractors and W-2 employees are entangled in the data model** — separate tables, no FK linking them, duplicate identities for people who are both.
- **Reporting computes on the fly** from raw event tables. Adding a new report is slow; aggregating over years is slower.

## Business goals

1. **Eliminate duplicate data entry** between QC Minute, QCP CRM, paper forms, and spreadsheets
2. **Make every property a self-contained source of truth** — contacts, departments, positions, rates, contracts in one place
3. **Reduce billing errors** by anchoring rates in the Property Bible and snapshotting them onto every billable event
4. **Replace paper-based and email-based workflows** with a structured, auditable workflow engine
5. **Give every role a focused dashboard** that surfaces what needs their attention
6. **Track every billable hour** — including hours from hotels that use their own time-tracking systems (via Excel imports)
7. **Build reporting on top of pre-aggregated data** so dashboards and exports stay fast as data grows

## Who uses what

| Role | Surface | What they do |
|---|---|---|
| Contractor | QC Minute | Clocks in/out at hotels via QR code on their phone (GPS + selfie verified) OR via tablet device on `/device` as backup. Logs in to see own hours, paychecks, uniform balance, role-gated KB articles. |
| Property Manager | QC Minute | Sees their property's live clock-in activity (read-only during week). Approves or declines weekly timesheets. Views and downloads invoices. Submits more-staff and pay-increase requests. |
| Recruiter | Back office | Manages their assigned properties and the contractors at them. Submits timesheets for PM approval. Sends invoices. Initiates workflows. Field check-in via floating button on mobile. |
| Front Desk | Back office | Operational role (per ADR-0013). Fulfills supply requests, issues uniforms, marks onboarding documents received, creates POs after Admin approval, handles termination file-handling tasks. |
| Office Manager | Back office | Maintains Property Bible. Manages inventory catalog and categories. Runs reports. Handles cross-property workflows. Fallback for Front Desk tasks. |
| HR | Back office | Manages applicant → contractor lifecycle, documents, terminations. PTO admin. PTO approvals. |
| Payroll | Back office | Views/edits contracts (restricted role). Processes deduction schedules. Closes payroll periods. Voids/reissues invoices. |
| Admin | Either domain | Business ownership tier (per ADR-0013). Approves all new-item supply requests. Full access currently; in future, narrows as developer-specific things move to Super Admin only. |
| Super Admin | Either domain | Developer / system tier. Permanent full access. Impersonation, audit log search, legal hold management. |
| Public visitor | Public marketing site | Reads about QCP, applies for jobs (creates an applicant record). |

## What this rebuild does NOT include

To stay in scope:

- **No SaaS sale of QC Minute.** Multi-tenancy was once on the roadmap and is now explicitly dropped. See ADR-0002.
- **No native mobile app.** Browser-based responsive UI throughout. The recruiter GPS check-in uses the browser's geolocation and camera APIs.
- **No marketing *redesign*.** The marketing pages move into this codebase as a Blade surface (ADR-0023), but keep their current content and design — we're porting, not redesigning.
- **No payroll processing system.** QC Minute computes payroll figures and produces exports; actual payroll runs in QuickBooks (or whatever the operations team uses externally).
- **No accounting/general ledger integration** beyond invoice generation. Invoices live in this system; bookkeeping is downstream.

## Guiding principles

1. **One database, one codebase.** No sync, no API drift between systems we own.
2. **Snapshot anything that can change.** Rates, property info, invoice line items. Reports stay historically stable.
3. **Human in the loop where it matters.** Don't auto-submit, auto-send, or auto-approve anything that has financial or relationship impact.
4. **Workflows are first-class.** Every request type uses the same engine. Audit trail comes for free.
5. **Soft delete + legal hold by default.** PII never disappears without a deliberate decision.
6. **Pre-aggregate for reports.** Materialized rollups, not live joins, in the hot path.
7. **Permissions, not tenants.** Row-level access via roles and assignments. No tenant_id column anywhere.

## Related

- `00-context/current-systems.md` — what exists today and what we're carrying forward
- `00-context/glossary.md` — terminology
- `10-architecture/overview.md` — technical shape
- `70-decisions/` — the choices that got us here
- `80-plan/roadmap.md` — how we build it
