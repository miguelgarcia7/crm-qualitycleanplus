# Documentation

This folder holds the **complete specification** and **implementation plan** for the rebuilt QCP system — a unified Laravel application replacing the legacy QC Minute and QCP CRM apps.

If you're new here, read in this order:

1. `00-context/vision.md` — what we're building and why
2. `00-context/glossary.md` — vocabulary used everywhere (recruiter vs contractor vs PM vs front desk, etc.)
3. `10-architecture/overview.md` — the technical shape
4. `80-plan/roadmap.md` — phase-by-phase build sequence
5. `reviews/2026-05-21-client-review.md` — client-facing summary (most accessible read for non-engineers)

Then dip into the per-area folders as needed.

## Folder map

```
docs/
├── 00-context/        ← background: what + why + glossary + current legacy systems
├── 10-architecture/   ← technical shape: stack, routing, auth, permissions
├── 20-domain/         ← what the system models: people, properties, workflows, etc.
├── 30-schema/         ← tables, columns, ERDs, naming conventions
├── 40-flows/          ← step-by-step end-to-end workflow specs
├── 50-ui/             ← screen-by-screen UI specs (added per phase — currently empty)
├── 60-reports/        ← reporting strategy and catalog (added per phase — currently empty)
├── 70-decisions/      ← ADRs — immutable records of architectural choices (29 to date)
├── 80-plan/           ← phased implementation plan + roadmap
├── 90-open/           ← unresolved questions, deferred items
└── reviews/           ← dated client reviews + cost estimates
```

## Conventions

### Frontmatter

Every doc opens with a small status table:

```markdown
| Field | Value |
|---|---|
| Status | Accepted / Draft / Open / Reference |
| Last updated | YYYY-MM-DD |
| Owner | Product / Engineering / specific person |
```

### Cross-linking

Files reference each other liberally. Paths are relative to `docs/`. Some references point to files not yet written (e.g. `30-schema/people-tables.md`) — those will fill in as we build each phase.

### ADRs (Architecture Decision Records)

ADRs in `70-decisions/` are **immutable**. If a decision changes, write a new ADR (e.g. `0022-...`) that says "Supersedes ADR-XXXX" and update both ADRs' frontmatter accordingly. Never edit the historical decision in place.

ADR format:

- Title, status, decision date, owner
- Context (what's the situation)
- Decision (what we chose)
- Consequences (positive + negative + implementation requirements)
- Alternatives considered (with reasons for rejection)
- Related (links to other docs and ADRs)

### Open items

Files in `90-open/` represent things we know we need to revisit. Each has:

- What we know
- What's open
- What blocks the decision
- A provisional v1 shape if needed before resolution

When an open item is resolved, move it to the appropriate place (an ADR for a decision, a domain file for a definition) and mark the open file as resolved with a reference to where the decision lives.

### Plan docs

`80-plan/roadmap.md` is the high-level phase sequence. Per-phase docs (`phase-01-foundation.md`, etc.) are written just-in-time as we approach each phase, not all upfront. They're living documents that change as we build.

### Reviews

Dated artifacts in `reviews/` are point-in-time client-facing summaries. They capture state at a specific moment for stakeholder conversations.

## ADRs at a glance (29 total)

| # | Title | Decided |
|---|---|---|
| 0001 | One app, two domains | 2026-05-21 |
| 0002 | No multi-tenancy | 2026-05-21 |
| 0003 | Fresh build on Laravel 13 | 2026-05-21 |
| 0004 | One `people` table with status, not separate tables | 2026-05-21 |
| 0005 | Rate snapshots on time entries | 2026-05-21 |
| 0006 | Invoice freeze + void/reissue | 2026-05-21 |
| 0007 | Staged timesheet approval | 2026-05-21 |
| 0008 | Time entries + summaries (split schema) | 2026-05-21 |
| 0009 | Payroll periods as first-class | 2026-05-21 |
| 0010 | Legal hold on PII | 2026-05-21 |
| 0011 | More-staff request creates a search task | 2026-05-21 |
| 0012 | Unified inventory with three categories | 2026-05-21 |
| 0013 | Admin and Front Desk roles | 2026-05-21 |
| 0014 | Contractor charge schedule (rename + broaden) | 2026-05-21 |
| 0015 | Manual Stock Out | 2026-05-21 |
| 0016 | PTO tier-based accrual | 2026-05-21 |
| 0017 | Field check-in flows (contractor QR + recruiter floating button) | 2026-05-21 |
| 0018 | Termination workflow | 2026-05-21 |
| 0019 | Transfer + Temporary Assignment workflows | 2026-05-21 |
| 0020 | Pay Increase workflow | 2026-05-21 |
| 0021 | More Staff Request workflow | 2026-05-21 |
| 0022 | Frontend stack: React + Inertia + Fortify | 2026-06-06 |
| 0023 | Marketing site in-monorepo as a Blade surface | 2026-06-06 |
| 0024 | Two-domain layout + per-surface asset bundles | 2026-06-06 |
| 0025 | Backend domain-driven structure | 2026-06-07 |
| 0026 | Workflow engine (hybrid) | 2026-06-07 |
| 0027 | Preline in a React/Inertia app — hybrid component policy | 2026-06-07 |
| 0028 | Report rollups | 2026-06-11 |
| 0029 | List pagination — server-side for ledgers, client-side for config | 2026-09-05 |

## Current status (snapshot)

As of 2026-06-06:

- **Spec coverage:** complete across all 11 workflows, both domains, all role definitions, time tracking, invoicing, inventory, PTO, field check-in, audit/PII
- **64+ documentation files** across 9 folders
- **29 ADRs** locking in every architectural decision
- **10 flow files** covering the major end-to-end processes
- **Open items:** 1 (Contracts data model — deferred per David's direction)
- **Parked items:** 1 (Recruiter-to-property bulk transfer). Applicant-to-contractor promotion was built — see `PromoteApplicantToContractor` + `OnboardingChecklist`.
- **Phase plans:** Roadmap only; per-phase plans pending
- **Schema docs:** ERD + conventions; per-table specs come with each phase
- **Cost estimate** produced — `reviews/2026-05-21-cost-estimate.md`
- **Client review pack** produced — `reviews/2026-05-21-client-review.md`

## How to add to these docs

- **New decision?** Add an ADR in `70-decisions/`. Increment the number.
- **New concept that affects the data model?** Add a file in `20-domain/`.
- **New workflow?** Add a file in `40-flows/`.
- **New screen design?** Add to `50-ui/` (per surface).
- **New question without an answer?** Add a file in `90-open/`.

For PHP code changes (when we get to building), follow the project's main CLAUDE.md / boost guidelines, not these docs.

## Why we wrote these docs first

Two reasons:

1. **Future Claude sessions and developers need context.** This conversation lives in chat memory only. When a new session starts (or a new dev joins), they need a written reference — this is it.

2. **Writing surfaces gaps.** The act of writing each domain doc exposed questions that hadn't come up in conversation. Discussing in chat is exploratory; writing is committing.

The docs are not exhaustive — they're "enough to anchor everything when the build starts." Many files will grow during their relevant phase as we discover edge cases.
