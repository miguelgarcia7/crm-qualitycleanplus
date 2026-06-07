# Current Systems

| Field | Value |
|---|---|
| Status | Reference |
| Last updated | 2026-05-21 |
| Owner | Product |

This document describes the **two legacy systems** being replaced and what we're carrying forward from each. It is purely background — no decisions live here. For decisions, see `70-decisions/`.

## QC Minute (legacy)

**Stack:** Laravel 10, PHP 8.2, MySQL 8.1, Redis, Vue 3 + Vuetify (SPA frontend), Sanctum auth, Spatie Permission, Laravel Orion (REST resources).

**Domain:** A time-tracking and invoicing system for contractors placed at hotels.

**Modules (14):** UserModule, PropertiesModule, WorkOrderModule, WorkTimeRecordsModule, PropertyTimeSheetModule, InvoiceModule, AdjustmentItemsModule, ContractorFeeDeductionModule, PropertyExpensesModule, DeviceModule, ReportsModule, AuthorizationModule, ActivitiesModule, BaseModule.

**Domain flow:**
```
Property → WorkOrder → WorkTimeRecord → PropertyTimeSheet → Invoice
```

**Key features:**
- Tablet devices for contractor clock-in/out (Sanctum tokens, property-locked)
- Dual UTC + local timezone capture on every time record
- Hourly bucketing (regular / overtime / holiday / training) computed at report time
- Weekly timesheet approval workflow (currently auto-generated hourly)
- Invoice generation with snapshot freezing (introduced 2026-03)
- 24 named reports (counts, financials, dimensional breakdowns)
- Activity logging across all major models
- Excel exports for timesheets and payroll
- Property Manager dashboard with currently-clocked-in widget (added May 2026)

**Pain points:**
- Hours auto-submitted to PMs without recruiter review
- Multi-tenancy was planned but not implemented; would have been disruptive to add later
- Single `work_time_records` table used as both event log and bill input — imports awkward
- Reports recompute from raw events; slow at scale
- Cross-system data (Property exists in both apps) duplicated and divergent
- No formal contract or rate-history data structure
- Permission system uses `view-all` vs `view-own` patterns inconsistently

## QCP CRM (legacy)

**Stack:** Laravel 11, PHP 8.2, MySQL 8, Blade + Vuexy admin template (Bootstrap 5), Spatie Permission, Maatwebsite/Excel, Postmark mailer.

**Domain:** Internal back-office for QCP — bilingual public marketing site + admin CMS for HR, properties, invoicing (employee-targeted), inventory, PTO, knowledge base.

**Top-level areas:**
- Public marketing site (English + Spanish at `/` and `/es/*`)
- Public job application form
- Employee management (with two-tier identity: `users` for CMS, `employees` for workforce)
- Property management (basic profile only — no departments, no contracts, no rates)
- Polymorphic invoicing (invoices targeted at Employee or Employer)
- Inventory + uniform issuance (Orders increase stock, Purchases decrease)
- PTO (credits, requests, approvals, comments)
- Recruitment (Applicants and Positions, with public job board)
- Testimonials
- Knowledge Base (2026-01 addition — articles, versions, categories, tags, attachments, role-based visibility, polymorphic feedback)

**Pain points:**
- Two parallel identity systems (`users` and `employees`) with no FK linking them
- `Applicant.job_id` declared as int but no FK constraint
- Many hardcoded values (notification recipient email, default password)
- Permission seeder is mostly commented out — controllers gate on permissions that aren't seeded
- Inventory mutations not wrapped in transactions; no negative-stock guards
- Invoices in CRM are about *employees* (legacy use case), not customer billing
- PTO credits don't auto-debit on approval — manual balance management
- Anniversary report is the only Excel export

## What survives the rebuild (concepts to keep)

From QC Minute:
- The work-order-as-rate-carrier model (extended: snapshot per time entry)
- Dual UTC + local timezone on time entries
- Hourly bucketing logic (regular / OT / holiday / training)
- Invoice snapshotting / freezing
- Activity log on all mutable models
- Polymorphic file attachments
- Sanctum tokens for devices (under `/device` path now)
- Property-locked device authentication

From QCP CRM:
- The Knowledge Base structure (articles + versioning + categories + tags + role visibility + polymorphic feedback) — strongest part of the legacy code
- The applicant intake form fields (citizenship, felony, transportation, emergency contact — these are legally required and well-shaped)
- PTO bucket structure (vacation / scheduled / unscheduled absence)
- Polymorphic feedback model

## What gets removed or redesigned

- **The `users` / `employees` split** — collapsed into one `people` table with a status enum (see ADR-0004)
- **Auto-send timesheet pattern** — replaced with the staged approval flow (see ADR-0007)
- **Polymorphic invoicing on Employee/Employer** — invoices are about *properties* (customer billing); the legacy "employee invoice" use case is dropped
- **Manual invoice data table** — folded into `time_entries` with source = `imported` (see ADR-0008)
- **Recomputed-at-query-time bucketing** — replaced with materialized `time_summaries` (see ADR-0008)
- **Multi-tenancy plans** — explicitly dropped (see ADR-0002)
- **Two separate Laravel codebases** — unified into one app with two route-grouped domains (see ADR-0001)
- **Hardcoded notification recipients** — moved to per-property settings
- **PTO that doesn't auto-debit** — debit on approval as part of the workflow engine

## Migration approach

The data in both legacy systems is operational; cutting over requires moving it. Approach:

1. Build the new system to production-ready state, populated only with seed data
2. Run both old systems and new system in parallel during a verification window
3. Migrate data on a planned cutover date with a frozen-old-system window
4. Retire QC Minute and QCP CRM after the new system has run cleanly for N weeks

Detailed migration plan deferred to `80-plan/phase-final-cutover.md` (not yet written).

## Related

- `00-context/vision.md` — what we're building
- `70-decisions/0001-one-app-two-domains.md`
- `70-decisions/0002-no-multi-tenancy.md`
- `70-decisions/0003-fresh-build-laravel-13.md`
- `70-decisions/0004-people-with-status-not-separate-tables.md`
- `70-decisions/0008-time-entries-and-summaries.md`
