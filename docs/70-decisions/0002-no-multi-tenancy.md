# ADR-0002: No Multi-Tenancy

| Field | Value |
|---|---|
| Status | Accepted |
| Decided | 2026-05-21 |
| Owner | Product (David) |
| Supersedes | — |
| Superseded by | — |

## Context

An earlier design direction had QC Minute becoming a multi-tenant SaaS product, eventually sold to hotels that wanted their own time-tracking system. Multi-tenancy would have meant:

- A `tenant_id` column on every transactional table
- A global Eloquent scope filtering every query by the logged-in user's tenant_id
- A `tenants` table; users belong to one tenant
- Seed QCP as tenant 1; future hotel customers as tenants 2..N

The business has since decided **not to pursue SaaS**. QCP will be the only user. The two-domain split (see ADR-0001) provides the UX and branding separation the SaaS plan was partially serving.

## Decision

**Do not build multi-tenancy. No `tenant_id` column on any table. No global tenant scope. No tenants table.**

Row-level access is instead enforced through:

1. **Permissions** (Spatie roles + capabilities) for *what* a user can do
2. **Role-scoped relationships** for *which rows* they can see (e.g. a recruiter sees their assigned properties via the `recruiter_properties` pivot; a PM sees their property via `property_user`)
3. **Policy classes** that check ownership before allowing an action

This is the pattern QC Minute already uses today (`view-all` vs `view-own` permissions), and it scales fine for a single-tenant system.

## Consequences

### Positive

- Schema is simpler — no `tenant_id` to add to 30+ tables
- Queries are simpler — no global scope to remember
- Reports are simpler — no per-tenant aggregation logic
- New features ship faster — no tenant-awareness to test
- Onboarding new developers is easier — no extra mental model
- No risk of cross-tenant data leak (because there are no tenants)

### Negative

- If QCP changes its mind in N years and wants to sell QC Minute, retrofitting multi-tenancy will require a real migration project (estimated 3-6 months at that point)
- We lose the option to spin up a "demo tenant" for sales calls — though demo data could live in a staging environment instead

### What we still need to be careful about

- **Backups should not accidentally leak data**, e.g. if a contractor needs an export of just their data, that's a per-row query, not a per-tenant query
- **Soft-delete + legal hold** still apply (see ADR-0010); not having tenants doesn't change PII obligations
- **Permission-scoped access is now the only data isolation mechanism**; bugs in policies are correspondingly higher-stakes

## Alternatives considered

### A. Build it anyway "just in case"

Rejected. The cost of building, testing, and maintaining multi-tenancy on every feature is high. The cost of retrofitting it later if the business pivots is bounded and well-understood (we know the pattern). Pay for what you use.

### B. Add `tenant_id` columns but no scope

Rejected. Adds noise to every migration and model for zero benefit until/unless we activate scoping. If we ever do go SaaS, we'll be backfilling the column on existing data anyway.

### C. Single-tenant now, but build the abstractions multi-tenant-ready

Rejected. "Ready" without actually doing it is the worst of both worlds — abstractions that look like they support tenancy but actually don't, leading to leaky implementations when the day comes.

## Related

- ADR-0001 — One app, two domains (the alternative to multi-tenancy for separating PM/contractor UI from back-office UI)
- `10-architecture/overview.md`
- `10-architecture/identity-and-auth.md`
