# Glossary

Terms used throughout these docs. When the same word means different things in different industries, the definition here is the one this project uses.

## People

| Term | Meaning |
|---|---|
| **Person** | A row in the `people` table. Every applicant, contractor, recruiter, office manager, HR staff, etc. is one row. Status changes (e.g. applicant → contractor) happen on the same row, not by creating a new one. |
| **Applicant** | A person who has submitted a job application via the public site but has not yet been moved to contractor status. |
| **Contractor** | A person who works at hotel properties under QCP placement. Paid as a 1099 contractor (the majority of the workforce). May clock in via QC Minute, or have hours imported from a hotel's external system. |
| **Recruiter** *(also: Employee Manager)* | W-2 QCP staff who places and manages contractors. Each recruiter owns one or more properties and all the contractors working at those properties. |
| **Office Manager** | W-2 QCP staff with broad back-office access. Manages Property Bible data, oversees workflows. |
| **HR / HR Personnel** | W-2 QCP staff handling employee lifecycle, documents, PTO admin. |
| **Payroll** | W-2 QCP staff handling pay processing, deduction schedules, contracts (view only). |
| **Property Manager (PM)** | Works at the hotel, not for QCP. Logs into QC Minute. Sees their property's contractors, approves timesheets, views invoices. |
| **W-2 Employee** | Any QCP staff member (recruiters, office managers, HR, payroll, others). Can request PTO, access the KB, receives notifications. |
| **Admin** | Business ownership role (David). Currently has full access; over time, loses access to developer-only capabilities (impersonation, raw audit, system config). See ADR-0013. |
| **Super Admin** | Developer / system tier. Permanent full access. Cross-domain — can log in to either domain. |
| **Front Desk** | Operational office role (per ADR-0013). Owns supply request fulfillment, uniform issuance, onboarding doc receipt, stock corrections, PO creation, physical workflow tasks. Office Manager retains all same permissions as fallback. |
| **Primary recruiter** | Each contractor has one assigned recruiter (`primary_recruiter_id` field). They're the recruiter who "owns" the contractor for roster counts and accountability. Changes only on permanent transfer (not on temporary assignment). See ADR-0019. |

> **Recruiter ≠ Contractor.** If the same human takes both roles, they're two separate `people` rows with two identities. This is intentional for legal and audit clarity.

## Time tracking

| Term | Meaning |
|---|---|
| **Time entry** | A single raw record of work — either a clock-in/out event or an imported weekly total. One row in `time_entries`. |
| **Time summary** | A pre-computed weekly rollup per (contractor, work order, week) with hours already bucketed into regular / overtime / holiday / training. Materialized table populated by background jobs. Reports read from here, not from `time_entries`. |
| **Payroll period** | A property's pay week, with an explicit start, end, and status (open / closed / invoiced). First-class table; controls whether time entries in that window can be edited. |
| **Bucketing** | The act of splitting a contractor's weekly hours into regular (≤40), overtime (>40, ×1.5), holiday (on a property holiday, ×1.5), and training (separate fixed rate). |
| **Clock event** | A time entry with source = `clock_event`. Has start and end timestamps in both UTC and the property's local timezone. |
| **Imported total** | A time entry with source = `imported`. Has no start/end times, only a duration (the weekly total from the hotel's payroll export). |

## Work orders & rates

| Term | Meaning |
|---|---|
| **Work order (WO)** | The assignment that links a contractor to a property at a specific position with specific pay/bill rates. Required for every contractor — whether they clock in or are import-only. |
| **Pay rate** | What QCP pays the contractor per hour. |
| **Bill rate** | What QCP charges the property per hour. |
| **OT pay rate / OT bill rate** | Overtime equivalents (typically pay × 1.5 and bill × 1.5, but can vary by contract). |
| **Rate snapshot** | The pay/bill rate values frozen onto a time entry at the moment it's created. Subsequent rate changes do NOT retroactively rewrite past entries. |
| **Rate change effective date** | When a rate change takes effect — always at the next payroll period boundary, never mid-week. |

## Property concepts

| Term | Meaning |
|---|---|
| **Property** | A hotel or site that QCP services. Has its own profile, departments, positions, contracts, rates, and payroll periods. |
| **Property Bible** | The complete record for a property in QCP Staffing: profile, departments, positions & rates, contracts. Acts as the *reference* source for rates; the work order is the *authoritative* source. |
| **Department** | A grouping within a property — Housekeeping, Banquets, Food & Beverage, Public Spaces, Kitchen / Culinary. Carries the department manager's name and phone. |
| **Position** | A role title — Housekeeper, Banquet Server, etc. Globally defined; rates are per (property, position, contract). |
| **Contract** | A document + structured metadata defining the terms QCP works under at a property. Has effective and expiration dates. Restricted to ownership and payroll. |
| **External ID** *(import-only properties)* | The contractor's identifier in a hotel's own payroll system. Used to match rows during weekly hour imports. One contractor may have different external IDs at different hotels. |

## Timesheets & invoicing

| Term | Meaning |
|---|---|
| **Timesheet** | The weekly aggregation of a property's time summaries, with an approval lifecycle (see ADR-0007). One per (property, week). |
| **Invoice** | The billing artifact generated automatically when a timesheet is approved. Frozen at creation — corrections are made by voiding and reissuing, never by editing. |
| **Freeze** | The act of locking an invoice's line items, totals, rates, and property/invoicer info into snapshot columns so future data changes don't alter past invoices. |
| **Void / reissue** | The mechanism for correcting an invoice: mark the original as voided (treated as $0 in reports), create a new corrected invoice referencing the original. |
| **Send Invoice to Property** | The manual recruiter action that notifies the PM (or other contact) that an invoice is ready. Decoupled from invoice generation. |
| **`pending_termination` status** | Intermediate person status: termination workflow is initiated and the person is no longer working, but the file-move step hasn't completed. Set on initiation (or scheduled effective_date), flips to `terminated` on file-move task completion. See ADR-0018. |

## Adjustments

| Term | Meaning |
|---|---|
| **Adjustment item** | A reusable template — name, default value, type (incentive / deduction), billable flag. |
| **Adjustment** | An applied adjustment on a specific contractor's hours — uses an adjustment item template and a value. |
| **Incentive** | A positive adjustment (e.g. perfect-attendance bonus). May or may not be billable to the property. |
| **Deduction** | A negative adjustment (e.g. equipment damage). Never billable to the property — always payroll-only. |
| **Billable flag** | Whether an adjustment flows into the invoice (charged to the property) or stays in payroll only (affects contractor pay only). |
| **Source type** | Discriminator on `time_entry_adjustments` indicating where the row came from: `manual` (recruiter added), `supply_request` (contractor charge schedule), `import` (during import wizard), `other`. See ADR-0014. |
| **Contractor charge schedule** | A multi-period payment plan for items issued to a contractor with a charge (uniforms, name tags, etc.). 1-4 split payments. Generates `time_entry_adjustment` rows at each scheduled period. Renamed from legacy `uniform_deduction_schedule` per ADR-0014. |

## Workflows

| Term | Meaning |
|---|---|
| **Workflow** | A structured request with one or more approval/action steps. All workflows run on the same engine. See `20-domain/workflows.md`. |
| **Termination workflow** | Multi-step lifecycle for ending a contractor or W-2 staff relationship. Two-step status: `pending_termination` → `terminated` (on file-move task completion). Per ADR-0018. |
| **Transfer (permanent)** | Workflow that closes old WO + opens new WO at different property (or same property different position). May update `primary_recruiter_id`. Per ADR-0019. |
| **Temporary assignment** | Concurrent assignment workflow — contractor works at another property for a defined period with a required `end_date`. Home WO stays open. `primary_recruiter_id` UNCHANGED. Auto-closes at end_date. Per ADR-0019. |
| **Supply request** | Unified workflow for requesting items across all categories (Office Supplies, Uniforms, Equipment). Per ADR-0012. |
| **More-staff request** | PM-initiated workflow asking for additional contractors. Lands in recruiter's "Talent Needs" queue. Placements link via `more_staff_request_id` on work_orders. Per ADR-0021. |
| **Pay increase workflow** | Form for PM to request an increase amount (not a target rate); recruiter sets actual new pay + bill rates. Effective next pay period boundary. Per ADR-0020. |
| **Manual Stock Out** | Stock movement type for items leaving storage outside the supply_request workflow (e.g. Admin grabs shirts informally). No charge, no deduction. Per ADR-0015. |

## Inventory

| Term | Meaning |
|---|---|
| **Item category** | Grouping for inventory items: `Office Supplies`, `Uniforms`, `Equipment` (seeded by default). Renamable; cannot be deleted while items exist. New categories can be added. Per ADR-0012. |
| **Stock movement** | Any change to `current_stock` — typed: `purchase_order_receipt`, `direct_receipt`, `count_correction`, `issuance`, `manual_issuance`, `return`. Per ADR-0012 and ADR-0015. |
| **Equipment assignment** | Record linking an item variant to a person who currently holds it (laptops, monitors). Returned on termination via Recover Equipment workflow step. Per ADR-0012. |

## Workflows

| Term | Meaning |
|---|---|
| **Workflow** | A structured request with one or more approval/action steps. Examples: PTO request, termination, transfer, uniform request, more-staff request, pay-increase request. |
| **Workflow engine** | The generalized system that handles all workflows uniformly — initiator, approver(s), action on completion, audit log entry. See `20-domain/workflows.md`. |
| **Request** *(informal)* | Casual word for "a workflow instance someone initiated." |

## Inventory

| Term | Meaning |
|---|---|
| **Uniform request** | Recruiter-initiated workflow to issue a uniform to a contractor. Triggers a receptionist task. On receptionist completion, automatically schedules payroll deductions. |
| **Issuance** | The act of physically giving a uniform to a contractor — done by the receptionist, recorded in inventory and triggers deductions. |
| **Deduction schedule** | A series of scheduled payroll deductions over multiple pay periods, created from a uniform issuance. |

## Surfaces

| Term | Meaning |
|---|---|
| **QC Minute** | The public-facing time/invoicing surface. Domain: `qcminute.com` (TBD final name). Property managers and contractors log in here. |
| **QCP Staffing back office** | The internal admin surface. Domain: `backoffice.qcpstaffing.com` (TBD final name). All QCP staff log in here. |
| **Public marketing site** | `qualitycleanplus.com`. A **Blade** surface within this same codebase (server-rendered for SEO, own asset bundle). Hosts the marketing pages, job listings, and the job application form. See ADR-0023. |
| **/device path** | The endpoint hierarchy for tablet devices that contractors clock in on. Sanctum-authenticated, property-locked tokens. Lives within the QC Minute domain but is a distinct route group. Legacy backup flow (per ADR-0017). |
| **QR clock-in** | Modern primary contractor clock-in flow (per ADR-0017). Contractor scans static QR code at the property, enters phone number, picks work order, GPS + selfie captured, time_entry created. Browser-based; no app or tablet required. |
| **Field visit** | Recruiter's check-in at a property (per ADR-0017). Captures GPS + selfie via Back Office mobile's floating action button. Operational logging only — not paid time, not billable. Lives in `field_visits` table, separate from `time_entries`. |
| **Floating action button (FAB)** | Persistent button in the bottom corner of Back Office mobile UI, used for recruiter check-in/out. Context-aware — shows Check In if no open visit, Check Out if one exists. |
| **Geofence** | Radius (default 300m) around a property's lat/lng. Used to block contractor clock-in outside the radius and to flag (not block) recruiter visits outside it. |

## Data & lifecycle

| Term | Meaning |
|---|---|
| **Snapshot** | A frozen copy of data at a point in time, stored on the row that depends on it (e.g. rates on a time entry, property info on an invoice). Protects against retroactive change. |
| **Soft delete** | Marking a row as deleted (`deleted_at` set) without physical removal. Default behavior for PII-bearing models. |
| **Legal hold** | A flag on a person/record that blocks both soft and hard deletion until cleared. Used during active legal matters. |
| **Anonymization** | Replacing PII fields with placeholder values (name → "Former Employee", email → null) while keeping the row, used to honor "right to be forgotten" without breaking referential integrity in invoices/timesheets. |
| **Retention purge** | Scheduled job that hard-deletes soft-deleted rows older than the retention period (typically 7 years for employment records), skipping anything under legal hold. |

## Legacy terms (avoid in new code)

| Old term | New term |
|---|---|
| `work_time_record` / WTR | `time_entry` |
| `property_time_sheet` | `timesheet` |
| `manual_invoice_data` | Imported `time_entry` (single weekly entry, source = `imported`) |
| `Employee` (in legacy QCP CRM) | `person` (with appropriate status) |
| `User` (when meaning a QCP staff member) | `person` (with appropriate role) |
