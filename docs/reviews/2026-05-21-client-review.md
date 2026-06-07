# Client Review — QCP System Rebuild

**Date:** 2026-05-21
**Audience:** David Aguilar (QCP Ownership)
**Purpose:** Confirm the design before we start building.

---

## How to read this document

This is your operational view of what we're building. It's organized into:

1. **At a glance** — the shape of the new system in one page
2. **By business area** — 11 sections, one per domain, with concrete examples
3. **Cross-cutting decisions** — six big choices that affect everything
4. **Open decisions I need from you** — three items still waiting on your input
5. **What you'll see and when** — phased timeline
6. **Questions for our meeting** — talking points

Read sections 1, 4, and 5 first if you have limited time. Sections 2 and 3 are reference material to dip into per area.

If you want technical detail, the system specification lives in `docs/` alongside this file. You don't need to read it.

---

## 1. At a glance

We're replacing two existing systems (QC Minute and QCP CRM) with **one unified application**.

The unified app has two doorways:

- **QC Minute** (`qcminute.com`) — where Property Managers and Contractors log in.
  - PMs approve timesheets and download invoices
  - Contractors see their hours, paychecks, profile, uniform balance
- **QCP Staffing Back Office** (`backoffice.qcpstaffing.com`) — where the QCP team logs in.
  - Admin (Ownership), Office Manager, HR, Payroll, Recruiters, Front Desk, W-2 employees, Super Admin (developers)
  - Everything internal: Property Bible, workflows, dashboards, reports, KB, etc.

Behind both doorways: **one database**. No more duplicate data between systems, no more drift, no more "the property name is different in QC Minute vs CRM."

**Public site** (`qualitycleanplus.com`) stays exactly as it is — same marketing pages, same job application form. It just talks to the new database underneath.

### What's structurally new

Three big additions to what exists today:

1. **The Property Bible** — every property becomes a single source of truth for departments, positions, rates, contracts. No more spreadsheets and emails.
2. **The Workflow Engine** — every request type (PTO, termination, transfer, supply request, pay increase, etc.) runs on one consistent system with audit trail and notifications.
3. **The Unified Supply System** — replaces the uniform-only approach with three categories: Office Supplies, Uniforms, Equipment. Recruiters and staff request items, **Front Desk fulfills** (a new role added for this purpose), inventory updates automatically.

---

## 2. By business area

### 2.1 Property Bible

**What this is:** A complete profile for every property QCP services. Contacts, departments, positions, rates, contracts — all in one place.

**What's different from today:**
- Rates live in the system (today: spreadsheets and memory)
- Department managers and phone numbers are searchable (today: emails and texts)
- Contracts are uploaded with dates, viewable only by Ownership and Payroll
- Alerts 30 and 14 days before contract expiration
- Job codings tracked per property per position

**Who does what:**

| Role | Can do |
|---|---|
| Office Manager | Edit profile, departments, positions, rates |
| Payroll | View / edit / download contracts; edit positions and rates |
| Ownership (you) | Everything, including contracts |
| Recruiter | View their assigned properties; edit department contacts |
| PM | View their property (read-only) |

**Example scenario:**

> A new property comes on board — Marriott Downtown Phoenix. Office Manager opens the Bible, creates the property profile, adds the five departments (Housekeeping, Banquets, F&B, Public Spaces, Kitchen) with their manager contacts, defines the four positions QCP staffs there (Housekeeper, Banquet Server, Dishwasher, Janitor) with their pay/bill rates. Payroll uploads the signed MSA with effective date 2026-06-01 and expiration 2027-05-31. Done — every other system pulls from this.

**Open item:** the exact structured data fields for contracts (beyond name/type/dates/notes) is still waiting on your input. See section 4.

---

### 2.2 People — Applicants, Contractors, Staff

**What this is:** One unified system for every human in QCP's world — applicants, contractors, recruiters, office manager, HR, payroll.

**What's different from today:**
- No more "user table vs employee table" confusion — one record per person
- Application date is preserved forever (when they first applied)
- The onboarding checklist (ID, W-9, I-9, agreement) lives on the contractor profile
- Lifecycle is explicit: Applicant → Contractor Active → Contractor Inactive → Terminated
- Rehires keep their history (status transitions, not new records)
- If the same person is both a recruiter and (occasionally) a contractor, they're tracked as two separate records — clean audit, no confusion
- For import-only hotels, contractors carry an external ID per hotel for matching weekly imports

**Who does what:**

| Role | Can do |
|---|---|
| HR | Manage all contractor lifecycle, documents, terminations, change requests |
| Recruiter | Manage their assigned contractors; promote applicants; initiate transfers |
| Office Manager | Edit staff records |
| Contractor | View own profile (read-only); request changes via workflow |

**Example scenario:**

> Maria applies through the website. Her record is created with `status=applicant`, application date locked. Jane (recruiter) reviews the application, schedules an interview, collects ID and W-9 — checking each item off the onboarding checklist. When the checklist is complete, Jane clicks "Promote to Contractor." Maria's status becomes `contractor_active`. Her original application data remains visible on her contractor profile forever. Two years later, Maria leaves. Status becomes `terminated`. Six months later, Maria wants to come back — Jane reactivates her (same record), status returns to `contractor_active`. Full history preserved.

---

### 2.3 Work Orders + Time Tracking

**What this is:** The engine for tracking every billable hour, whether contractors clock in via QC Minute or hours are imported from a hotel's payroll system.

**What's different from today:**
- Rates are snapshotted onto every time entry — past hours never get reprice silently when rates change
- Imports and clock-in events go into one unified "time entries" table
- A new "Time Summaries" layer pre-computes weekly buckets (regular/OT/holiday/training) so reports are fast
- Payroll periods are first-class — each week is a record with open/locked/invoiced status
- Live read-only weekly grid for PMs at QC Minute hotels — they can watch the week unfold
- Pay increases create new work orders effective next pay period (no mid-week math)

**Who does what:**

| Role | Can do |
|---|---|
| Contractor | Clock in/out via tablet at hotel |
| Recruiter | View their properties' grid; add manual entries; add adjustments |
| PM | View live grid for their property (read-only during the week) |
| Office Manager | Override anything |

**Example scenario:**

> Maria works at Marriott DT. Tuesday at 9 AM she scans the QR code posted at the front desk on her phone, enters her phone number, picks her work order (Housekeeper at Marriott DT), takes a selfie, and is clocked in. The system records start time in both UTC and Phoenix local time, captures her GPS coordinates (verified inside the property's geofence), and stores the selfie. She works until 5 PM, scans the QR again to clock out (GPS + selfie captured again). The system creates a "time entry" with her rate of \$20/hr (snapshotted from her work order). Later that week, the recruiter sees Maria's hours on Wednesday were off by 30 minutes — adds a manual correction entry. End of week, the system rolls up her hours into a time summary: 39 regular + 1 overtime (because Thursday she crossed 40h), $830 total billable. That summary feeds the timesheet, which feeds the invoice.
>
> **Note:** the legacy tablet flow at the front desk still works for contractors without smartphones. Per ADR-0017, QR is the primary modern flow; tablets are a backup.

---

### 2.4 Timesheets — The Staged Approval Flow

**What this is:** Weekly timesheets get reviewed by the recruiter, submitted to the PM for approval, and on approval, the invoice generates automatically.

**What's different from today:**
- **No more auto-submission.** A recruiter explicitly clicks "Send for Approval" — gives them a chance to fix mistakes first
- **PM can decline** with a required reason, recruiter edits, resubmits
- **Whole-timesheet approval**, not per-line (simpler, forces clearer communication)
- **PM gets live read-only view** during the week, doesn't have to wait until Friday
- **Invoice generation is automatic** on approval — but PM is NOT auto-notified
- **Recruiter manually clicks "Send Invoice to Property"** to notify the PM (or PM can log in and find it themselves)

**Who does what:**

| Role | Can do |
|---|---|
| Recruiter | Review, edit, submit for approval; send invoice notification |
| PM | View live grid; approve or decline timesheet; view invoices |
| Office Manager / Super Admin | Override anywhere |

**Example scenario:**

> Sunday morning. Jane (recruiter) reviews Marriott DT's week. She adds a $50 incentive for Maria (perfect attendance) and a \$25 deduction for Carlos (broke a vacuum). She clicks "Send for Approval." John (PM at Marriott) gets a notification, logs into QC Minute Monday morning, sees the timesheet. He notices Maria's Tuesday hours look high — clicks "Decline" with reason "Maria's Tuesday hours need verification." Jane gets the notification, calls Maria, confirms hours were entered correctly, adds a note explaining, resubmits. John approves Tuesday afternoon. System generates Invoice #INV-2026-001234. Jane sees it on her dashboard: "1 invoice ready to send." She reviews, clicks "Send Invoice to Property" with billing@marriott.com as the recipient. Done.

---

### 2.5 Invoicing — Freeze and Void/Reissue

**What this is:** Once an invoice is generated, it's frozen. No edits. If wrong, you void it and issue a corrected one referencing the original.

**What's different from today:**
- Every invoice freezes its rates, hours, property info, totals at the moment of generation
- "Voided" is a real status — invoice stays in the system but shows $0 in reports
- Corrections are a "replacement" invoice that links back to the voided original
- Both invoices stay in the audit trail forever

**Who does what:**

| Role | Can do |
|---|---|
| Recruiter | View, send to property, export Excel |
| Payroll | View, void, reissue |
| Ownership | All of the above |
| Office Manager | Send, export |
| PM | View their property's invoices |

**Example scenario:**

> Two weeks after sending Invoice #INV-2026-001234, Marriott calls — they see a \$200 error. Investigation reveals Maria's Wednesday hours were double-counted. Payroll opens the invoice, clicks "Void" with reason "Wednesday hours double-counted." Invoice #INV-2026-001234 is now voided (shows $0 in revenue reports). Recruiter goes back to the timesheet, corrects Wednesday's entries, the system generates Invoice #INV-2026-001289 as a replacement (linked to the voided one). Sent to Marriott. Audit trail shows everything.

---

### 2.6 Adjustments — Incentives and Deductions

**What this is:** One-off or scheduled additions/subtractions to a contractor's pay or the property's bill.

**What's different from today:**
- Cleaner separation between manual adjustments (gas pickup, bonuses) and structured ones (uniform deductions, name tags, other contractor-charged items)
- The catalog of templates (Pickup Fee Gas, Perfect Attendance Bonus, Equipment Damage, etc.) is managed by Office Manager / Payroll
- Recruiters pick from this catalog when adding manual adjustments during the week or during imports
- **Uniforms are NOT in this catalog** — they only enter through the supply request workflow
- All adjustments flow to the same place: visible on the timesheet, invoice (if billable), and contractor's deduction ledger

**Who does what:**

| Role | Can do |
|---|---|
| Office Manager / Payroll | Manage the catalog |
| Recruiter | Add manual adjustments to their properties' timesheets |
| Contractor | See their adjustment history in their profile |

**Example scenario:**

> Carlos drove a co-worker home from a shift (recruiter approved it). Wednesday on the live grid, Jane clicks "Add Adjustment," picks "Pickup Fee (Gas)" from the catalog, enters \$15 incentive, billable. Maria found bed bugs at a property and reported them — Jane adds a "Bed Bug Bonus" of $25, non-billable (QCP eats this cost). Both adjustments appear on the timesheet, on the contractor's payroll, and in their profile's "Recent Adjustments" view.

---

### 2.7 Imports — For Hotels Not Using QC Minute Clock-In

**What this is:** Excel import flow for hotels that have their own timekeeping system but where QCP still bills.

**What's different from today:**
- A guided wizard: upload → review matches → resolve unmatched → add adjustments → commit
- Auto-creates work orders for new (contractor, property) pairs
- Auto-generates the timesheet (no PM approval needed since no PM in system)
- Auto-generates the invoice
- Same downstream flow as clock-in hotels — recruiter still manually sends the invoice notification

**Who does what:**

| Role | Can do |
|---|---|
| Office Manager / Payroll / Recruiter | Run imports for their assigned properties |

**Example scenario:**

> Hilton Phoenix sends a weekly Excel export every Monday. Office Manager opens "New Import," selects Hilton Phoenix, selects payroll period (defaults to last week), uploads the file. System parses 32 rows: 28 match existing contractors by external ID, 3 are new contractors (system shows their names + IDs), 1 row has a pay rate different from the existing work order. Office Manager creates new records for the 3 new contractors (or skips them), accepts the rate change for the 4th (system creates a new work order at the new rate effective this week). Optionally adds an attendance bonus for two of the contractors. Clicks Commit. System creates 30 time entries + 1 timesheet + 1 invoice in one transaction. Invoice appears on the dashboard, ready to send.

---

### 2.8 Supply & Inventory (the big update from Email 4)

**What this is:** One unified system tracking all items QCP holds in stock and issues — Office Supplies, Uniforms, Equipment.

**What's different from today:**
- **Three categories** by default (Office Supplies, Uniforms, Equipment); can add more
- **Anyone** in the back office can request items — pens, notebooks, uniforms, laptops
- **Two paths:** existing item (**Front Desk** fulfills from stock) OR new item (Admin approval → Front Desk creates PO → receive → fulfill)
- **All new-item requests** route to Admin for approval (no $50 threshold). Office Manager does not approve new items.
- **Reorder thresholds** trigger dashboard alerts (Low Stock yellow, Out of Stock red)
- **Uniform requests** still trigger payroll deduction schedules — but only via this workflow, never via manual adjustment
- **Equipment is "checked out" not "consumed"** — laptop assignments tracked; returned on termination
- **Purchase Orders** are minimal v1 (no vendor tracking, no expected dates) — just track "what we need to order" and "received"

**Who does what:**

| Role | Can do |
|---|---|
| Anyone | Request items via the "Create Request" button |
| Front Desk | Fulfill requests (primary), receive stock, create POs after Admin approves, mark uniform issuance complete (triggers payroll deduction), process equipment returns, stock corrections |
| Office Manager | Manage inventory catalog (items, variants, categories), create POs, oversight; fallback for fulfillment when Front Desk is out |
| Admin | Approve all new-item requests; access everything |
| Super Admin | Same as Admin; full access |

**Example scenario 1 — Recruiter needs pens:**

> Jane needs more pens. She clicks "Create Request" on her dashboard, picks "Office Supplies," picks "Ballpoint Pens (Box of 12)," quantity 2, reason "running low at my desk." Front Desk sees it in their Pending Requests queue, walks to the supply cabinet, hands her the pens, clicks "Mark Completed." Stock decrements from 8 to 6.

**Example scenario 2 — Recruiter requests uniform for contractor:**

> Maria starts at a new property and needs a Housekeeping Polo. Jane clicks "Create Request," picks "Uniforms," beneficiary "Contractor → Maria," picks "Housekeeping Polo size M," quantity 2, charge amount \$30 total, split into 3 payments. Front Desk takes the polos from the supply room, hands them to Maria, clicks "Mark Completed." Stock decrements by 2. System auto-creates a 3-week deduction schedule: \$10 from Maria's check this week, next week, and the week after. Maria can see this in her QC Minute profile: "Uniform Deduction: $10 of 3 payments, \$20 remaining."

**Example scenario 3 — Office Manager needs a new label printer:**

> Office Manager submits "Request New Item" with description "Brother PT-D610BT label printer for filing labels," estimated cost \$120, reason "filing system needs better labels." All new-item requests route to Admin for approval. You approve from your dashboard. The item appears on Front Desk's "Ready to Purchase" list. Front Desk creates a PO, orders from Amazon, item arrives, marks "Received" — item is now in inventory, original request marked completed, label printer is on Office Manager's desk.

**Example scenario 4 — Recruiter's laptop returns on termination:**

> Recruiter is leaving QCP. HR initiates the Termination workflow. A "Recover Equipment" step appears in the workflow, listing the laptop and monitor assigned to him. Front Desk checks the boxes "Returned" for both, adds notes "good condition." Stock increments by 2. Assignments closed. A separate step routes to Front Desk to move the recruiter's physical file from the active cabinet to HR retention.

---

### 2.9 Workflows — The Engine That Connects Everything

**What this is:** A single, generalized system that powers every multi-step request: PTO, termination, transfer, supply requests, pay increases, more-staff requests, recruiter property transfers, and personal-info changes.

**What's different from today:**
- One unified queue: "My pending tasks" — every workflow you owe an action on shows up here
- Same audit trail format across all workflow types
- Same notification pattern across all workflow types
- New requests are easy to add later (build them as workflow definitions, not new code)

**The current workflow catalog:**

| Workflow | Initiated by | Approver / Fulfiller |
|---|---|---|
| PTO request | W-2 employee | Their manager |
| Termination | Recruiter (own) / HR / Admin | Front Desk (equipment + file), Payroll (final check + QuickBooks). Two-step status: `pending_termination` → `terminated`. See ADR-0018. |
| Transfer (permanent) | Recruiter / HR / Admin | Closes old WO, opens new WO. Updates primary recruiter if changing. Handles property change AND same-property position change. |
| Temporary Assignment | Home recruiter / HR / Admin | Contractor temporarily works at another property under another recruiter. Primary recruiter UNCHANGED (counts stay with home recruiter). Auto-closes at end_date. |
| Supply request | Anyone | Office Manager (+ ownership for new items $50+) |
| More-staff request | Property Manager (own property) | Lands in Recruiter's "Talent Needs" queue. Recruiter places contractors (new hire / transfer / temp) and links each placement to the request. quantity_fulfilled auto-tracks; PM gets per-placement updates. Urgency level drives queue sort. By-date is informational. See ADR-0021. |
| Pay-increase request | Property Manager (own property) or Recruiter (own contractor) | PM specifies increase amount (not target rate); Recruiter approves with actual new pay + bill rates. Bill rate increase always honors PM's offer. Effective next pay period (or +1). See ADR-0020. |
| Recruiter-to-property transfer | Ownership / Office Manager | Auto-applied |
| Change personal info request | Contractor / W-2 | HR verifies and applies |

**Example scenario:**

> John (PM at Marriott) needs Maria's rate increased from $20 to $22. He logs into QC Minute, clicks "Request Pay Increase" on Maria's profile, fills the form (new rate, reason "exceptional performance"). Workflow created, routes to Jane (recruiter for Marriott). Jane gets a notification, reviews, approves with effective date next Monday. System closes Maria's old work order (effective end of this week) and creates a new one with the new rate (starting next Monday). Audit trail captures everything: request, who approved, when, what was the old rate, what's the new rate.

---

### 2.10 Dashboards — What Each Role Sees on Login

**What this is:** Role-tailored landing pages that surface what each person needs to act on.

**Recruiter dashboard:**
- Properties they're assigned to
- Contractors at those properties
- Timesheets awaiting their action (drafts, declined)
- Approved invoices ready to send
- Pending workflow requests they need to act on
- Currently clocked in (live)
- Documents expiring soon

**Property Manager dashboard:**
- Live grid of their week
- Timesheets awaiting their approval
- Invoice history

**Office Manager dashboard:**
- Pending supply requests
- Inventory alerts (Low Stock, Out of Stock)
- Expiring contracts
- Workflows requiring their action

**HR dashboard:**
- Applicant pipeline
- Onboarding checklists in progress
- Terminations in progress
- Personal info change requests

**Payroll dashboard:**
- Period closures pending
- Contracts expiring soon
- Voided/reissued invoices to review
- Active contractor charge schedules (uniforms, name tags, etc.)

**Contractor dashboard (in QC Minute):**
- Their hours for the current week + history
- Past paychecks
- Profile (read-only)
- Uniform/deduction balance
- KB articles relevant to them

**Super Admin / Ownership dashboard:**
- Cross-cutting health view
- Audit search
- Quick access to any role's view

---

### 2.11 Knowledge Base + Reports + PTO + Audit

**Knowledge Base** — articles with versioning, categories, tags, attachments. Role-gated visibility (some articles only Recruiters see, some only Contractors, etc.). Carried forward from current CRM mostly unchanged.

**Reports** — financial (revenue, payouts, gross income by property/position/month/year), operational (hours by week, hours by position, currently clocked in), payroll (deductions, contracts expiring). Built on pre-computed summary tables so they load fast. Excel export everywhere.

**PTO** — for W-2 employees only (recruiters, office manager, front desk, HR, payroll, other W-2 staff). Three separate buckets: vacation, scheduled absence, unscheduled absence. Tier-based accrual by tenure (0/0/0 during 30-day probation → 20/20/20 at Day 30 → 30/30/30 at 6 months → 40/40/40 at 1 year, stays forever). Hire-anniversary cycle; no rollover; no termination payout. Approval by Admin or HR only (HR cannot self-approve — those route to Admin). Submission-time balance check + pending hours deducted immediately. See ADR-0016.

**Audit + PII** — everything is logged (who did what when). PII is soft-deleted with retention rules. Legal hold blocks deletion during active matters. Anonymization available for "right to be forgotten" requests without breaking referential integrity.

---

## 3. Key architectural decisions (all locked)

These ripple through the whole system. All are confirmed via 21 ADRs in the spec. Here's the digest organized by area.

### Architecture & deployment

- **One application, two domains.** Replaces the previous plan of two apps. `qcminute.com` and `backoffice.qcpstaffing.com` share one database, one codebase. (ADR-0001)
- **No multi-tenancy.** QC Minute is not being sold as SaaS. Simpler architecture; revisit only if business pivots. (ADR-0002)
- **Fresh build on Laravel 13.** Greenfield; data migrates from QC Minute + QCP CRM on cutover. (ADR-0003)
- **Public marketing site stays as-is.** Out of scope; integrates via small JSON endpoints for job postings + applications.

### Identity & people

- **One `people` table with status.** No more separate users/employees/applicants. Status enum captures lifecycle: applicant → contractor_active → pending_termination → terminated. (ADR-0004)
- **Two records for dual-role humans.** A recruiter who occasionally contracts gets two `people` records. Clean audit, clean payroll separation.
- **`primary_recruiter_id` on contractors.** Each contractor has one primary recruiter who owns them for roster-count purposes. Updates only on permanent transfer. (ADR-0019)
- **New roles added: Admin and Front Desk.** Admin = business ownership tier (David). Super Admin = developer/system tier with permanent full access. Front Desk = operational office role for supply fulfillment, uniform issuance, onboarding docs. (ADR-0013)

### Time tracking & invoicing

- **Snapshot rates on every billable hour.** Past hours never re-price; reports stay stable. (ADR-0005)
- **Time entries + summaries (split schema).** Raw events in `time_entries`; pre-aggregated weekly buckets in `time_summaries` for fast reports. (ADR-0008)
- **Payroll periods as first-class entities.** Per (property, week) with explicit status. Drives edit gates. (ADR-0009)
- **Invoices freeze + void/reissue.** Audit-clean; every correction documented. (ADR-0006)
- **Staged timesheet approval, manual invoice send.** No auto-submit; recruiter controls each step. (ADR-0007)

### Inventory & supply chain

- **Unified inventory with three categories.** Office Supplies, Uniforms, Equipment. Customizable. (ADR-0012)
- **Contractor charge schedule.** Single mechanism for uniforms, name tags, and any future contractor-charged items. 1-4 split payments. Unified balance view on contractor profile. (ADR-0014)
- **Manual Stock Out.** Path for items leaving storage without a request or charge (Admin grabs shirts). (ADR-0015)
- **$50 approval threshold removed.** ALL new-item supply requests route to Admin. Front Desk creates POs after Admin approval.

### Workflows

- **Generalized workflow engine.** Single engine handles PTO, termination, transfer, temporary assignment, supply request, more-staff, pay-increase, change-personal-info, recruiter-to-property transfer.
- **PTO tier-based accrual.** 0/0/0 (probation) → 20/20/20 → 30/30/30 → 40/40/40 by tenure. Hire-anniversary cycle, no rollover, no termination payout. Admin/HR approve (HR can't self-approve). (ADR-0016)
- **Termination workflow.** Two-step status: `pending_termination` → `terminated` (on file-move completion). Conditional contractor vs W-2 paths. (ADR-0018)
- **Transfer + Temporary Assignment.** Two distinct workflows. Permanent transfer closes old WO + may update primary_recruiter; temp assignment keeps home WO open with end_date, primary_recruiter unchanged. (ADR-0019)
- **Pay Increase workflow.** PM specifies increase amount (not target rate); recruiter sets actual new pay + bill rates. PM never sees pay rate. Effective next pay period boundary. (ADR-0020)
- **More-Staff request.** PM submits → recruiter's "Talent Needs" queue → placements linked to request via `more_staff_request_id`. Recruiter declines with reason if not fulfillable. (ADR-0021)
- **More-staff creates a search task, not auto-job-posting.** Public posting is a separate, deliberate decision. (ADR-0011)

### Field check-in

- **Contractor QR clock-in.** Browser-based, GPS-verified + selfie. Replaces tablets as primary; tablets remain as backup for contractors without smartphones. (ADR-0017)
- **Recruiter floating-button check-in.** GPS + selfie on Back Office mobile. Separate `field_visits` table — not billable, not paid hours. (ADR-0017)

### PII & audit

- **Legal hold + retention + anonymization.** PII deletion follows a schedule; legal hold blocks deletion during active matters; anonymization handles "forget me" requests without breaking historical records. (ADR-0010)
- **Activity log is immortal.** Even when subjects are anonymized, audit history remains.

---

## 4. Open / parked items

Items deliberately deferred. None blocks Phase 1 of the build; each will be revisited before the relevant phase begins.

### 4.1 Contracts data model (genuinely open)

**What we know:** Contracts have name, type, effective date, expiration date, uploader, notes. Restricted to Ownership and Payroll. 30/14-day expiration alerts.

**What's open:** Beyond the basics, what structured data should we capture (vs. just storing the PDF)?
- Per-position rate floors or ceilings the contract enforces?
- Scope of service fields?
- Specific clauses (indemnity, exclusivity, payment terms) as structured fields?
- Or just metadata + PDF, period?

**Why it matters:** Affects Phase 2 (Property Bible). Can build the rest of the Bible without this; just defer the Contracts section. v1 minimal shape (name, type, dates, file, notes) is documented in `90-open/contracts-data-model.md` as the working assumption.

**Decision needed by:** Before Phase 2 starts (or accept the minimal v1 shape).

### 4.2 Applicant-to-contractor promotion (detailed flow — parked)

Concept and lifecycle are locked (per `20-domain/people-lifecycle.md`). The detailed step-by-step flow doc with onboarding choreography hasn't been written. Will be filled in during Phase 4/8 build.

### 4.3 Recruiter-to-property bulk transfer (detailed flow — parked)

When Recruiter A leaves or changes roles, all their property assignments + contractor ownership transfer to Recruiter B. Concept is locked; detailed UI + edge cases (pending workflows, mid-transition states) deferred. Phase 4 work.

### Previously resolved (for reference)

| Item | Resolution | Where it lives |
|---|---|---|
| PTO policy details | Tier-based accrual locked | ADR-0016, `20-domain/pto.md` |
| Uniform deduction on rehire | Final-paycheck consolidation + cap-at-zero | ADR-0014, `20-domain/inventory.md` |
| Front Desk role definition | New role added, replaces "Receptionist" concept | ADR-0013 |
| Manual Stock Out path | New stock_movement type added | ADR-0015 |
| Contractor QR clock-in | Browser-based, GPS + selfie verification | ADR-0017 |
| Temporary assignment | New workflow distinct from transfer | ADR-0019 |
| Pay increase mechanics | Increase amount (not target rate); recruiter sets actual rates | ADR-0020 |
| More-staff request fulfillment | Linked placements via `more_staff_request_id` | ADR-0021 |

---

## 5. What you'll see and when

Phase-by-phase, with what you'll be able to do at the end of each phase. Times assume a single engineer; cut significantly with a team.

| Phase | Duration | What you can do at the end |
|---|---|---|
| **01 — Foundation** | 2-3 weeks | Log in to either domain. Empty system. Nothing operationally useful yet but the rails are laid. |
| **02 — Property Bible** | 3-4 weeks | Create properties with full profile, departments, positions, rates. Upload contracts. Expiration alerts work. |
| **03 — Time tracking + invoicing** | 4-5 weeks | Full clock-in / clock-out / timesheet / invoice loop end to end. The core money pipeline works. |
| **04 — Workflow engine + supply system** | 3-4 weeks | All 8 workflows live. Inventory + supply requests + contractor charge schedules (uniforms/name tags/etc.) + equipment assignment. |
| **05 — Import flow** | 1-2 weeks | Bring in import-only hotels' weekly hours. Auto-generates timesheets + invoices. |
| **06 — Dashboards** | 2-3 weeks | Every role has a tailored dashboard. Live widgets, queues, alerts. |
| **07 — Field check-in flows** | 2-3 weeks | Recruiters check in at properties via floating button (GPS + selfie). Contractors clock in via QR code with their phone (GPS + selfie). Tablets remain as backup. Per ADR-0017. |
| **08 — KB + applicants + job postings + PTO** | 3-4 weeks | Knowledge Base operational. Applicants from public site flow in. PTO workflow live. |
| **09 — Reports** | 2-3 weeks | Reports catalog with materialized rollups. Excel exports. Fast. |
| **10 — Cutover** | 3-4 weeks | Data migrated from QC Minute + QCP CRM. Legacy retired. Parallel run during validation window. |

**Total: 24-34 weeks** for a single engineer — call it 6-8 months. With a team of 2-3 engineers, more like 4-5 months.

---

## 6. Questions for our meeting

Most architecture is locked. Remaining topics worth discussing:

1. **Contracts data model** (open item 4.1) — do you want to scope out the structured fields now, or accept the minimal v1 (just metadata + PDF) and design richer later?
2. **Team size and timeline** — given the cost estimate ($125K-$180K total), do you have a target ship date or budget that drives team sizing? (Solo: 6-8 months; 2-3 engineers: 3-5 months.)
3. **Phase 1 kickoff** — comfortable starting with Foundation (schema baseline, identity, domain routing, auth)? It's the smallest investment ($8K-$12K) to confirm the foundation works before committing further.
4. **Roadmap sequencing** — any phase you'd want to move earlier? E.g. Field check-in (Phase 7) could move sooner if recruiter visit tracking is a near-term priority.
5. **Public marketing site integration** — the existing `qualitycleanplus.com` stays as-is; we just need to confirm who handles the small JSON endpoint integration (passing job postings + receiving applications).
6. **Hosting choice** — Laravel Cloud / Forge / self-hosted? Affects setup timeline in Phase 1.
7. **Anything I missed?** This is the moment to surface anything that didn't come up in our chats. Quirks of specific properties, recurring issues that bug you, "we should really have X" wishlist items.

---

## Appendix — Where to find the technical detail

If you or anyone else wants to dig deeper, the full system specification lives in this same repo:

- **`docs/00-context/vision.md`** — what we're building and why
- **`docs/20-domain/*`** — 11 files, one per business area
- **`docs/40-flows/*`** — step-by-step walkthroughs of key flows
- **`docs/70-decisions/*`** — 12 ADRs documenting every cross-cutting choice
- **`docs/80-plan/roadmap.md`** — the full phased build plan
- **`docs/90-open/*`** — the unresolved items

These are technical documents, not meant for client reading. They're here when an engineer needs the precise rules.
