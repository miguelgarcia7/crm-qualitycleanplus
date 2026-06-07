# ADR-0013: Add `admin` and `front_desk` Roles

| Field | Value |
|---|---|
| Status | Accepted |
| Decided | 2026-05-21 |
| Owner | Product (David) + Engineering |
| Supersedes | — (partial scope change for the Receptionist parking-lot item and `0012` fulfillment assignments) |
| Superseded by | — |

## Context

Earlier role inventory had six back-office roles: `super_admin`, `office_manager`, `hr`, `payroll`, `recruiter`, `w2_employee`. Two gaps surfaced during design review:

1. **Ownership vs. system administration are conflated.** `super_admin` was used to mean both "the business owner who has full access" and "the developer with full system access." Over time these diverge — developers should retain backstage access (debug tools, impersonation, raw audit), while the business owner doesn't need that and arguably shouldn't have it.

2. **Receptionist work has a clear owner.** The previous decision (parking-lot) folded receptionist tasks into Office Manager. As workflows expanded (supply requests, uniform issuance, onboarding doc receipt, termination file-handling), a dedicated operational role makes more sense — both for permission scoping and for dashboard ergonomics.

## Decision

**Add two new roles: `admin` and `front_desk`.**

### `admin` — business ownership

- Currently has identical permissions to `super_admin`
- Over time, `super_admin` retains everything; `admin` loses access to developer-y capabilities (impersonation, raw audit log search, system configuration, schema migrations, retention purge management)
- In conversations and docs going forward, "Admin" means this role. "Super Admin" means the developer/system tier.
- Initial seed assignment: David (Ownership) gets `admin`; engineering team members get `super_admin`. Some people may hold both during transitional periods.

### `front_desk` — operational role at the office

Owns the day-to-day physical office work:

- **Supply request fulfillment** (primary) — handles incoming supply requests across all categories; takes items from supply room, hands to requester, marks complete
- **Uniform issuance** (primary) — picks up uniform from supply room, gives to contractor, confirms delivery (triggers payroll deduction schedule)
- **Onboarding document receipt** — marks each onboarding checklist item as documents physically arrive (ID, W-9, I-9, signed agreement)
- **Stock receipt** — receives PO arrivals; marks them in the system
- **Stock corrections** — performs count-corrections with reason
- **Equipment return** — processes equipment returns to stock
- **Purchase order creation** — places POs after Admin approves new-item requests, AND can create POs directly for routine reorders
- **Physical-task workflow steps** — any workflow with a "physical office task" step (e.g. termination's "move file from active cabinet to HR retention") routes to Front Desk by default

Front Desk does NOT:

- Approve anything (no `*.approve_*` permissions)
- Edit Property Bible (rates, contracts, departments)
- Edit contractor or staff records beyond the onboarding checklist
- View contracts (Ownership + Payroll only)
- See financial reports

### Tasks shifted from other roles to Front Desk

| Capability | Was | Now (primary) | Now (fallback) |
|---|---|---|---|
| Fulfill supply requests | Office Manager | **Front Desk** | Office Manager |
| Issue uniforms + trigger deductions | Office Manager | **Front Desk** | Office Manager |
| Mark onboarding doc receipt | HR | **Front Desk** | HR |
| Receive stock from POs | Office Manager | **Front Desk** | Office Manager |
| Stock corrections | Office Manager | **Front Desk** | Office Manager |
| Create POs (routine + approved-new-item) | Office Manager | **Front Desk** | Office Manager |
| Termination file-handling step | Office Manager | **Front Desk** | Office Manager |

### Approval routing for new-item supply requests

**Before** (now superseded):
- < $50 → Office Manager approves
- ≥ $50 → Office Manager OR Super Admin

**Now:**
- ALL new-item requests → **Admin** approves (no threshold)
- The $50 rule is removed entirely

After Admin approval, the request appears on Front Desk's "Ready to Purchase" list. Front Desk places the order; receives the items; original supply request is then fulfilled.

## Consequences

### Positive

- Clear separation between business ownership (Admin) and system administration (Super Admin) sets up the future devolution cleanly
- Front Desk gets a clearly-scoped role matching real operational work
- Workflow steps that previously had to be assigned to "Office Manager (or someone)" now have an obvious owner
- Office Manager retains all permissions as fallback — Front Desk absence doesn't block operations
- Approval routing simplified: no $50 threshold logic to maintain

### Negative

- Two more roles to seed, permission, and document
- Office Manager scope narrows somewhat (still substantial — Property Bible, categories, item management, approvals other than new-item, reports)
- Need to think carefully about which "everything" capabilities to start removing from Admin in v2+

### Implementation requirements

- Seed `admin` and `front_desk` Spatie roles
- Update permission matrix in `10-architecture/permissions-matrix.md` (two new columns; many rows touched)
- Identity-and-auth doc updates
- Inventory + supply-request docs: actor name changes
- Workflows doc: termination workflow's file-handling step assigned to Front Desk
- People-lifecycle doc: onboarding checklist work owned by Front Desk
- Add `front_desk` to the back-office route group middleware
- Add new permission: `people.applicants.onboarding_checklist.edit` (narrow scope for Front Desk)

### Future devolution (for reference)

Capabilities that will likely move out of Admin over time and stay with Super Admin only:

- Impersonation (`admin.impersonate`)
- Activity log raw search (`audit.activity_log.view`)
- Setting/clearing legal holds (`audit.legal_hold.*`)
- Triggering retention purge override
- Direct database operations / schema migrations (not a permission per se, but operationally restricted)

Not removing these from Admin in v1. ADR will be written when the devolution actually happens.

## Alternatives considered

### A. Keep Office Manager doing everything, no Front Desk role

Rejected. The Office Manager scope was getting too broad to ergonomically dashboard. A dedicated operational role with its own queue is cleaner.

### B. Make Admin = renamed Super Admin (one role total)

Rejected. The future devolution intent is real — developers need different access than business owners. Naming them differently from day one avoids a rename when devolution happens.

### C. Skip Front Desk, build Receptionist later

Rejected. Receptionist work exists today and is being designed for in workflows (termination, uniform issuance). Naming the role now and assigning workflows correctly avoids a rework.

## Related

- ADR-0012 — Unified inventory with three categories (supply request fulfillment actor updated)
- ADR-0011 — More-staff request creates search task (unchanged)
- `10-architecture/identity-and-auth.md` — role list updated
- `10-architecture/permissions-matrix.md` — full matrix updated
- `20-domain/inventory.md` — Front Desk as primary fulfiller
- `20-domain/people-lifecycle.md` — onboarding checklist owner
- `20-domain/workflows.md` — termination workflow's file-handling step
- `40-flows/supply-request.md` — actor names and approval routing
- `90-open/parking-lot.md` — Receptionist item resolved by this ADR
