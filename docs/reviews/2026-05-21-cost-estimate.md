# Cost Estimate — QCP System Rebuild

| Field | Value |
|---|---|
| Date | 2026-05-21 |
| Audience | David Aguilar (QCP Ownership) |
| Purpose | High-level cost estimate for the unified system rebuild |
| Confidence | Mid (±20%) — based on locked spec; not yet a fixed quote |

---

## How to read this

This is a **planning estimate**, not a fixed quote. The numbers reflect the locked specification across all 21 ADRs and 62+ spec documents. They will firm up once we begin Phase 1 and discover real-world details.

Ranges (low–high) reflect normal estimation uncertainty. The midpoint is the most likely outcome.

Each section's cost = engineering time × blended rate. **Hosting, mail, and storage are listed separately at the end.**

---

## Assumptions

- **One senior Laravel engineer** at $100/hr blended (covers their time, employer overhead, taxes, benefits if a contractor)
- 40-hour work week (some weeks include planning + demos + client check-ins; that's normal)
- 80% productivity ratio baked in (1 week ≈ 32 productive hours)
- Estimates already include unit + feature testing time
- **Does NOT include:** design (UI/UX mockups), client management, hosting, ongoing maintenance, or post-launch support

If you staff with a smaller team of 2-3, calendar weeks compress by ~40% but total cost is similar (more parallel work, slight coordination overhead).

---

## Cost by major section

| # | Section | Time (weeks) | Cost range |
|---|---|---|---|
| 01 | **Foundation** — schema baseline, identity model, domain routing, auth, audit log infrastructure | 2-3 | $8,000 – $12,000 |
| 02 | **Property Bible** — properties, departments, positions, rates with effective dates, contracts (v1 minimal model), expiration alerts | 3-4 | $12,000 – $16,000 |
| 03 | **Time tracking + Invoicing** — work orders, payroll periods, time entries + summaries, bucketing (regular/OT/holiday/training), staged timesheet approval, invoice freeze + void/reissue, PDF generation, Excel exports | 4-5 | $16,000 – $20,000 |
| 04 | **Workflow engine + Inventory/Supply** — generalized workflow engine + first 8 workflows (PTO, termination, transfer, temp assignment, supply request, more-staff, pay-increase, change-info); inventory with 3 categories, contractor charge schedules with 1-4 split, Manual Stock Out, equipment assignments | 3-4 | $12,000 – $16,000 |
| 05 | **Imports** — Excel parsing wizard, contractor matching by external ID, rate conflict resolution, auto-generate timesheets + invoices, void/re-import | 1-2 | $4,000 – $8,000 |
| 06 | **Field check-in flows** — contractor QR clock-in (GPS + selfie); recruiter floating button check-in/out with geofence; tablet flow stays as backup | 2-3 | $8,000 – $12,000 |
| 07 | **Role dashboards** — per-role landing pages (Recruiter, PM, Office Manager, Front Desk, HR, Payroll, Contractor, Admin) with live widgets, queues, alerts | 2-3 | $8,000 – $12,000 |
| 08 | **PTO + KB + Applicants + Public site integration** — full PTO system with tier accrual, hire-anniversary cycles, Admin/HR approval; Knowledge Base port (versioning, role-gated articles); public application form endpoint + job postings | 3-4 | $12,000 – $16,000 |
| 09 | **Reports + Materialized rollups** — pre-aggregated summary tables, report catalog UI, Excel/PDF exports, dashboard analytics | 2-3 | $8,000 – $12,000 |
| 10 | **Data migration + Cutover** — migration scripts for QC Minute → new, QCP CRM → new; parallel-run validation; planned cutover day; legacy retirement | 3-4 | $12,000 – $16,000 |
| 11 | **Project management + QA buffer + iteration** (~15% overhead) | — | $15,000 – $20,000 |

**Subtotal: 25-35 weeks · $115,000 – $160,000**

---

## Items deferred for later (parked, not yet detailed)

The following are flagged in `docs/90-open/parking-lot.md` to revisit when you have business clarity:

| Item | Estimated additional cost | Notes |
|---|---|---|
| **Contracts data model** (richer than v1 minimal) | $4,000 – $8,000 | We'll build the minimal version (name, type, dates, file, notes) in Phase 2. Richer structured fields (rate floors, scope clauses, etc.) deferred. |
| **Applicant-to-contractor promotion** (full detailed flow) | $3,000 – $6,000 | Concept exists; detailed UI + edge cases pending |
| **Recruiter-to-property bulk transfer** | $3,000 – $6,000 | Concept exists; bulk reassignment UX pending |

**Deferred subtotal: $10,000 – $20,000**

---

## Total

| | Low | Mid | High |
|---|---|---|---|
| **Core build (sections 1-11)** | $115,000 | $137,500 | $160,000 |
| **Deferred items** (when revisited) | $10,000 | $15,000 | $20,000 |
| **Grand total (everything)** | **$125,000** | **$152,500** | **$180,000** |

---

## Calendar timeline

| Team size | Calendar weeks | Months |
|---|---|---|
| 1 engineer | 25-35 weeks | 6-8 months |
| 2 engineers | 16-22 weeks | 4-5 months |
| 3 engineers (max practical parallelism) | 12-17 weeks | 3-4 months |

Going faster than 3-4 months adds coordination overhead disproportionate to speed gain. The Property Bible (Phase 2) and Time/Invoicing (Phase 3) are sequential — they can't fully parallelize because Phase 3 depends on Phase 2's rates.

---

## What's included

- All ADR-decided architecture (21 ADRs)
- All domain models (people, properties, work orders, time entries, timesheets, invoices, adjustments, charge schedules, workflows, inventory, KB, audit/PII, field visits, PTO)
- All 10 workflows: PTO, termination, transfer (permanent), temporary assignment, supply request, more-staff request, pay-increase, recruiter-to-property transfer, change-personal-info, applicant-to-contractor promotion
- Contractor QR clock-in with GPS + selfie
- Recruiter floating-button check-in/out
- Tablet clock-in (backup flow)
- Both domain routes (`qcminute.com` + `backoffice.qcpstaffing.com`)
- Excel imports + exports
- Materialized rollup reports
- Spatie permissions per the full matrix (~100+ permissions across 10 roles)
- Public site integration (job posting endpoint + application endpoint)
- Activity log + retention + legal hold + anonymization
- Data migration from both legacy systems
- Parallel-run validation period
- Cutover day

## What's NOT included

| Item | Why excluded | Estimated separate cost |
|---|---|---|
| **UI/UX design** (mockups, design system, branding) | Often a separate contractor; can use existing Vuexy template or hire designer | $5,000 – $20,000 if hiring designer |
| **Hosting setup** (Laravel Forge, Cloud, or self-hosted) | Per-environment infrastructure | One-time: $500 – $2,000 |
| **Native mobile app for contractors** | Browser-based works for v1 | $25,000 – $50,000 if added later |
| **Two-factor authentication** | Not in v1 scope | $3,000 – $5,000 |
| **Push notifications (web/mobile)** | Browser broadcast handles real-time for v1 | $5,000 – $10,000 |
| **Public marketing site redesign** | Out of scope per ADR-0001 (stays as-is) | varies — own project |
| **Bookkeeping / GL integration** | Beyond invoice generation | $10,000+ depending on integration |
| **Ongoing maintenance / support** | Post-launch contract | $2,000 – $5,000/month |
| **Custom training videos / docs for end users** | Documentation effort beyond developer docs | $2,000 – $8,000 |

---

## Recurring costs (separate from build)

| Service | Estimated monthly | Notes |
|---|---|---|
| Application hosting (Forge / Cloud / VPS) | $50 – $200 | Single-server fits for v1; scale as needed |
| Database (managed MySQL — RDS or equivalent) | $100 – $300 | Includes backup retention |
| Redis (queue + cache) | $25 – $75 | Managed |
| S3 storage (files, selfies, attachments) | $20 – $100 | Grows with usage |
| Postmark (transactional email) | $15 – $50 | Per ~10K emails |
| Pusher (real-time broadcast) | $50 – $200 | Per concurrent connections |
| Domain + SSL (per year) | $50 – $150/year | Cheap |
| **Total infrastructure** | **$250 – $1,000 / month** | Scales with usage |

---

## Risk factors (could push costs higher)

| Risk | Impact | Mitigation |
|---|---|---|
| Data migration uncovers bad data in legacy systems | +1-2 weeks | Migration phase is allocated buffer; complex cases logged |
| Open items resolve into bigger scope (e.g. contracts model becomes elaborate) | +$5K-15K | Quoted separately when revisited |
| Hosting choice requires ops work beyond minimal | +$1-3K one-time | Decided in Phase 1 |
| Client adds significant new requests during build | Could be 10-30% more | Change-request process; not currently factored |
| Multiple engineers slow each other down (vs. solo) | +5-10% | Use a small team; defined module ownership |
| Testing reveals deeper bugs | Buffer (~15%) covers most | If exceeded, mid-project re-estimate |

---

## How to use this estimate

1. **Decide team size.** One engineer = lower cost, longer calendar. Multiple engineers = same/slightly higher cost, shorter calendar.
2. **Pick a target ship date.** That drives team size.
3. **Set a budget envelope.** The mid-point ($152K) is the working assumption.
4. **Lock the items deferred to "circle back later"** before Phase 4 begins (where their work would land).
5. **Reserve 10-15% contingency** for unknowns we'll discover during the build.

---

## Next steps

1. Discuss this estimate with David
2. Adjust scope if there's appetite to expand or trim
3. Lock a budget envelope
4. Commission Phase 1 (Foundation) — the smallest investment to confirm the foundation works before committing to the full build
5. Re-estimate after Phase 1 with actual velocity data

---

## Related

- `00-context/vision.md` — what we're building
- `80-plan/roadmap.md` — full phased plan with deliverables
- `docs/reviews/2026-05-21-client-review.md` — full feature-by-feature review
- `70-decisions/` — 21 ADRs with the architecture decisions
- `90-open/parking-lot.md` — items deferred to revisit
