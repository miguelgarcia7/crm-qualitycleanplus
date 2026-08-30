# QCP System Atlas — Overview & Module Breakdown

| Field | Value |
|---|---|
| Status | Living document — regenerate when modules change |
| Last updated | 2026-08-30 (repo at `ab07e53`) |
| Owner | Engineering |
| Companion | `vision.md` (why), `10-architecture/` (how), `80-plan/roadmap.md` (when) |

Quality Cleaning Plus places cleaning contractors at hotels and commercial
properties. This system tracks every billable hour from the moment a contractor
scans a QR code at a property to the moment a frozen invoice reaches the client —
and runs the internal operations around it.

It replaces two legacy applications, **QC Minute** and the **QCP CRM**, which held
separate databases. A property, a contractor, and a person's identity now exist
exactly once.

**At a glance:** 18 domain contexts · 58 tables · 52 models · 64 actions ·
10 roles · 7 workflows · 4 surfaces · 379 passing tests (1,669 assertions).

---

## Surfaces — four front doors, one database

The domain split is an access and experience boundary, not a data boundary. Every
surface reads and writes the same MySQL database through the same codebase.

| Surface | Host | Stack | Who + what |
|---|---|---|---|
| Back office | `qualitycleanplus.com/admin` | React + Inertia | Where the company runs. Recruiters, office managers, HR, payroll, front desk. |
| QC Minute | `qcpstaffing.com` | React + Inertia | Client-facing. PMs approve timesheets, view invoices, request staffing changes. Contractors sign in here. |
| Marketing + job board | `qualitycleanplus.com` | Blade (server-rendered for SEO) | Public pages, open positions, and the application form — writes an applicant straight into the shared database, no intermediate API. |
| Clock-in endpoints | `/clock-in/{token}`, `/device` | Public token · Sanctum device | A per-property QR link needing no login, plus a front-desk tablet kiosk paired to a property as backup. |

---

## Modules

Backend code is organized by business context rather than technical layer
(ADR-0025). Each module owns its models, its write operations (one Action per
write), and its authorization rules. Dependencies between modules run one way —
where two contexts must meet, a controller composes them.

### Operations

#### Property Bible — `app/Domain/PropertyBible`
The canonical record for every property. Previously lived in spreadsheets, emails,
and people's heads.

- Properties with departments, managers, and decision-maker contacts
- Global position catalog with per-property pay and bill rates, effective-dated
- Contracts with expiration alerts at 30 and 14 days, restricted to ownership and payroll
- Holiday calendar with per-property opt-in, resolved in the property's own timezone
- Geofence coordinates and radius, set on an interactive map
- Per-property job codes carrying the client's own GL codes onto invoices

#### Work Orders — `app/Domain/WorkOrders`
Binds a contractor to a property and a position; the authoritative rate source for
everything billable.

- Rates auto-populate from the Property Bible, with override
- Overtime computed at 1.5× by default, overridable explicitly
- Lifecycle from creation through close, with transfer and temporary assignment

#### Time — `app/Domain/Time`
Every clock event and the arithmetic that turns it into payable and billable hours.

- QR clock-in with GPS and selfie capture; lunch splits into two entries
- Bucketing into regular, overtime, holiday, and training with a 40-hour weekly split
- GPS policy that blocks only a *trusted* fix outside the fence, and flags rather
  than blocks degraded readings
- Live weekly grid, editable by recruiters and read-only for property managers
- Payroll periods materialized ahead by a nightly job

#### Field Visits — `app/Domain/FieldVisits`
Recruiter accountability logging. Deliberately **not** billable time.

- Check in and out from a phone with GPS and selfie
- Property auto-detected by location, with a manual picker fallback
- Prompts on a prior visit left open

#### Devices — `app/Domain/Devices`
The front-desk tablet kiosk, paired to a single property.

- Token-paired to one property, activated by a super admin
- Reuses the same clock actions, with no geofence check

### People & hiring

#### People — `app/Domain/People`
One identity for every human the company touches. In the legacy systems,
contractors and W-2 staff were separate tables with no link between them.

- A single record carries someone from applicant through contractor to staff
- Onboarding checklist gating promotion, with a reversal path
- Invite flow that creates a login and emails a set-password link
- Directory and profile with hours, work orders, adjustments, and history
- Legal hold and soft delete for records that must survive

#### Recruiting — `app/Domain/Recruiting`
Job postings and the applications that come back from the public site.

- Postings advertised on the public board, distinct from the internal position catalog
- One immutable application per applicant, preserving legal attestations as they stood
- Review queue feeding the promotion path

#### Paid time off — `app/Domain/Pto`
W-2 staff only, accruing on each employee's hire anniversary rather than the
calendar year.

- Tier-based accrual by tenure across three separate pools
- Hours deducted at submission, so pending requests cannot be double-spent
- HR can approve but never their own request
- Nightly job handling tenure crossings and anniversaries

#### Marketing — `app/Domain/Marketing`
Leads arriving from the public contact forms — job seeker and business inquiries
captured separately.

### Money

#### Billing — `app/Domain/Billing`
The approval chain that turns approved hours into an invoice, with deliberate human
checkpoints where the legacy system automated them away.

- Recruiter submits, property manager approves or declines with a reason
- Invoices freeze a snapshot of rates and hours at generation and never recompute
- Corrections happen by void and reissue, never by editing a sent invoice (ADR-0006)
- PDF rendering with job codes and a per-position summary

#### Adjustments — `app/Domain/Adjustments`
Manual incentives and deductions applied to a payroll period.

- Billable incentives flow through to the client invoice
- Deductions stay on the payroll side and never reach the client

#### Hour imports — `app/Domain/Imports`
For hotels that keep their own time system and send a spreadsheet.

- Upload, match contractors, resolve unmatched rows, adjust, then commit
- Commit produces time entries, an auto-approved timesheet, and an invoice in one transaction
- Re-import voids and replaces cleanly; full rollback available

#### Reports — `app/Domain/Reports`
Built on pre-aggregated rollups so queries stay fast as years of data accumulate
(ADR-0028).

- Weekly operational and monthly revenue rollups, rebuilt per cell as source data changes
- Revenue by property · gross income vs payouts · hours by position · payouts by contractor
- Excel export on every report; PDF on payouts

### Coordination & support

#### Workflows — `app/Domain/Workflows`
A single engine behind every approval process that used to run on paper or email
(ADR-0026).

- Definitions declared in code, walked step by step by the engine
- Steps assigned to a person, a role, or a relationship
- A shared My Tasks queue collecting whatever is waiting on you

#### Inventory — `app/Domain/Inventory`
One system covering office supplies, uniforms, and equipment rather than three
(ADR-0012).

- Categories, items, and variants with unified stock movements
- Purchase orders and equipment assignments
- Uniform issuance generating a contractor deduction schedule (ADR-0014)
- Deductions cap at available pay, never producing a negative check

#### Knowledge base — `app/Domain/KnowledgeBase`
Internal documentation, readable on both signed-in surfaces.

- Every edit snapshots an immutable prior version
- Hierarchical categories, auto-created tags, full-text search
- Per-article role visibility; no roles means all signed-in readers

#### Dashboards — `app/Domain/Dashboards`
Assembles each role's landing page from widgets drawn across every other module.

- `BackOfficePulse` builds the landing panels: launch blockers, headline figures,
  the hour-to-invoice pipeline, and the queues waiting on a person
- `DashboardMetrics` adds the role-specific widget lists beneath them
- Every panel is permission-gated — a recruiter sees no company-wide money

#### Shared & platform — `app/Domain/Shared`
Polymorphic file storage and feedback; in-app notification center with per-category
mutes on both surfaces; audit log viewer over every recorded action.

---

## Roles

Permissions are seeded from a single matrix (`RolePermissionSeeder`) rather than
assigned ad hoc, so every role's reach is defined in one file and covered by policy
tests.

| Role | Surface | What they do |
|---|---|---|
| `contractor` | QC Minute | Clocks in by QR or tablet. Signs in to see own hours and role-gated articles. |
| `property_manager` | QC Minute | Watches live activity, approves/declines weekly timesheets, views invoices, requests more staff or a pay increase. |
| `recruiter` | Back office | Runs assigned properties and contractors, submits timesheets, sends invoices, checks in from the field. |
| `front_desk` | Back office | Fulfills supply requests, issues uniforms, receives onboarding documents, creates POs once approved. |
| `office_manager` | Back office | Maintains the Property Bible and inventory catalog, runs reports, handles cross-property workflows. |
| `hr` | Back office | Owns applicant→contractor lifecycle, documents, terminations, PTO administration. |
| `payroll` | Back office | Views/edits contracts, processes deduction schedules, closes periods, voids or reissues invoices. |
| `w2_employee` | Back office | Requests time off and changes to their own personal information. |
| `admin` | Either domain | Business ownership. Approves new-item supply requests; broad access. |
| `super_admin` | Either domain | System tier. Impersonation, audit search, legal holds, device pairing. |

---

## Workflows

Each is defined in code as a sequence of steps, so one engine handles routing,
assignment, notification, and the audit trail for all of them.

Supply request · Termination · Transfer · Temporary assignment · Pay increase ·
More staff · Change personal information

---

## Foundations

**Money is never a float.** Every amount is stored as an integer number of cents, so
rounding cannot drift between an hour, a timesheet, and an invoice.

**Rates are snapshotted, not referenced.** A billable event copies the rate that
applied at the time. Changing a rate in the Bible never rewrites history.

**Invoices freeze.** Once generated, an invoice is an immutable financial record. The
only correction path is void and reissue, and the database enforces it with restrict
foreign keys.

**Time is UTC, properties are local.** Timestamps are stored in UTC while each
property carries its own timezone, so a workday and a holiday start when the property
says they do.

**Dependencies run one way.** Modules never form cycles. Where two contexts must meet,
a controller composes them rather than one reaching into the other.

**Judgment stays with people.** The legacy system automated notifications and sends.
Here a recruiter reviews before a manager sees it, and a person presses send on every
invoice.

---

## Status

### Complete — phases 01 through 09e

- Foundation, Property Bible, and the time-to-invoice pipeline
- Workflow engine, inventory, and hour imports
- Dashboards, field check-in, PTO, recruiting, knowledge base
- Reports with rollups, people directory, audit log, notifications
- Pre-cutover hardening: indexes, foreign key rules, migration consolidation

### Blocking launch

- **Phase 10 cutover** and data migration has no plan document yet.

Mail delivery now works: invoices are emailed through Postmark with a link to
the invoice on QC Minute, and an invoice is marked sent only once delivery
succeeds. Production still needs `POSTMARK_API_KEY`, `MAIL_MAILER=postmark`,
and a verified sender signature — see `10-architecture/deployment-topology.md`.

### Known gaps — deferred and not yet built

- Contractor self-service on QC Minute, deferred twice and still showing static link cards
- Field-visit reports, punted from Phase 07 into a report catalog that shipped without them
- Adjustment catalog has no management screen
- Two-factor is enabled and wired but has no enrollment panel
- Gas and mileage deductions remain undesigned

---

*Figures drawn from the domain contexts, migrations, seeded role matrix, workflow
definitions, and the passing test suite at commit `ab07e53`.*
