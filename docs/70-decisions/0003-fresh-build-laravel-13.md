# ADR-0003: Fresh Build on Laravel 13

| Field | Value |
|---|---|
| Status | Accepted |
| Decided | 2026-05-21 |
| Owner | Engineering |
| Supersedes | — |
| Superseded by | — |
| Note | Frontend-stack assumptions in this ADR (Blade + admin template, Breeze, no SPA) were revised — see ADR-0022. The Laravel-13 greenfield decision stands. |

## Context

We are consolidating two legacy applications into one (see ADR-0001):

- **QC Minute** runs on Laravel 10 with a Vue 3 SPA
- **QCP CRM** runs on Laravel 11 with Blade + Vuexy admin template

Both have accumulated technical debt that would make extending either painful:

- QC Minute's modular structure is elegant but `work_time_records` does too many jobs
- QCP CRM has commented-out permission seeders, hardcoded values, and an unclear User/Employee identity split
- Neither has the Property Bible, workflow engine, or unified time-tracking architecture we want

The question is whether to:
- (A) Extend QC Minute, port QCP CRM features into it
- (B) Extend QCP CRM, port QC Minute features into it
- (C) Greenfield build on Laravel 13, port data on cutover

## Decision

**Greenfield build on Laravel 13.** New repo. New schema. Data from the two legacy systems is migrated on cutover day; both legacy systems are retired afterward.

Stack:

- PHP 8.3+ (required by Laravel 13)
- Laravel 13
- MySQL 8
- Redis (queue + cache)
- Blade + a chosen admin template (TBD which — Vuexy, AdminLTE, or custom). No SPA.
- Spatie Permission 7+
- Pest 4 for testing
- Pint for formatting
- Laravel Breeze for auth scaffolding
- Sanctum for device API tokens

## Consequences

### Positive

- No legacy code to refactor, debug, or work around
- Schema can be designed from scratch around current needs (time_entries + time_summaries, payroll_periods, etc.)
- Identity model rebuilt cleanly (one `people` table, no User/Employee split)
- Test suite built from day one with current understanding
- Latest Laravel features available (Folio, Volt, improved Pest integration, etc.)
- No need to incrementally migrate the old apps' users while features change underneath them

### Negative

- Higher upfront cost than incrementally extending one of the existing apps
- Both legacy systems remain in production during the build (operational complexity for the duration)
- Cutover is a real event with a real risk of data migration bugs (mitigated by parallel running + verification window)
- Loss of incremental delivery — value isn't realized until the full system replaces both legacy systems

### What we carry forward

We don't reinvent everything. Patterns and code that worked well in the legacy systems will be ported:

- QC Minute's invoice snapshot freezing model
- QC Minute's UTC + local timezone capture on time records
- QC Minute's hourly bucketing logic (regular / OT / holiday / training)
- QC Minute's Sanctum device authentication pattern
- QC Minute's activity logging with Spatie
- QCP CRM's Knowledge Base structure (versioning, role-based visibility, polymorphic feedback)
- QCP CRM's applicant intake form fields (legal requirements are stable)
- QCP CRM's PTO bucket structure (vacation / scheduled / unscheduled)

## Alternatives considered

### A. Extend QC Minute

Rejected. QC Minute's modular structure makes extension feasible, but the schema needs reshaping (split WTR into entries + summaries, kill modular boundaries we no longer need, add Property Bible from scratch, replace SPA frontend with Blade). The amount of demolition before construction approaches the cost of greenfield.

### B. Extend QCP CRM

Rejected. QCP CRM is older Laravel, has worse internal hygiene, and lacks the more recent improvements in QC Minute (timezone handling, snapshot freezing, activity logging). Building on it is the worst foundation of the three options.

### C. Build incrementally, replace one feature at a time

Rejected. Requires keeping data in sync between two databases during the transition, which is the same problem we're trying to eliminate. Also extends the build timeline considerably.

## Implementation note

The greenfield path requires a **planned cutover** with:

1. Build to feature parity + new features
2. Run both old systems and new in parallel for N weeks (read-only on old)
3. Migration scripts to move data from QC Minute DB + QCP CRM DB → new DB
4. Cutover day: freeze old, migrate, validate, point traffic
5. Retire old systems after N weeks of clean running on new

This plan is documented separately in `80-plan/phase-final-cutover.md` (TBD).

## Related

- ADR-0001 — One app, two domains
- ADR-0002 — No multi-tenancy
- `00-context/current-systems.md` — what we're carrying forward
- `80-plan/roadmap.md` — phase sequencing
