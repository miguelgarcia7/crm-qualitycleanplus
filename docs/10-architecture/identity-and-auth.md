# Identity and Auth

| Field | Value |
|---|---|
| Status | Accepted |
| Last updated | 2026-05-21 |
| Owner | Engineering |

## One people table

Every human in the system is one row in the `people` table. This includes:

- Applicants (before they're moved to contractor status)
- Active and inactive contractors
- Terminated former contractors
- Recruiters
- Office managers
- HR personnel
- Payroll staff
- Property managers
- Super admins
- Other W-2 employees

Their `status` distinguishes lifecycle position (applicant / active / inactive / terminated). Their `roles` (Spatie) distinguish access level. See `20-domain/people-lifecycle.md` for the full lifecycle model.

> A person who is both a recruiter and (occasionally) a contractor has **two `people` rows** — one with role `recruiter`, one with role `contractor`. This is intentional. See ADR-0004.

## Authentication

| Identity type | Mechanism | Lives where |
|---|---|---|
| Human user (any role) | Session-based, Laravel Fortify | Login on either authenticated domain |
| Device (tablet at hotel) | Sanctum personal access token | `qcpstaffing.com/device/*` |
| Public visitor (marketing) | None — unauthenticated form (CSRF-protected) | `qualitycleanplus.com` |

### Human user login

- Login pages live at `qcpstaffing.com/login` (QC Minute) and `qualitycleanplus.com/admin/login` (back office)
- Both submit to the same backend authenticator
- Successful login + role allowed on that domain → redirect to role-appropriate dashboard
- Successful login + role NOT allowed on that domain → "wrong door" page with link to correct domain
- Password reset uses the standard Laravel Fortify flow (request → emailed token → set new)
- 2FA: not in scope for v1; flagged as a future addition

### Device authentication

A tablet at a hotel uses Sanctum tokens:

```
1. Admin creates a Device record in back office for Property X
   → Device gets a 6-digit one_time_code
2. Tablet at Property X visits qcpstaffing.com/device/activate
   → Enters the 6-digit code
3. Server validates, issues a long-lived Sanctum token
   → Token tied to Property X (property_id stored on the token row)
   → Token never expires (revocable by admin)
4. All future device requests send Bearer <token>
5. Clock-in/out actions automatically use the token's property_id
   → A device for Property X cannot create time entries for Property Y
```

See `40-flows/clock-in-out.md` for the operational details.

### Public site application submission

The marketing site is a Blade surface in **this same codebase** (ADR-0023), so the job-application form posts in-app — no cross-app API, no pre-shared key:

```
POST (qualitycleanplus.com) /apply
Protection: standard CSRF token (same app, same session domain)
Body:
  { first_name, last_name, email, phone, ... full applicant form ... }
```

The endpoint is unauthenticated (it's a public form) but CSRF-protected. Successful submissions create a `person` with status = `applicant` and trigger a notification to recruiters.

## Roles

Spatie roles, seeded on first deploy:

| Role | Allowed domain | Typical actions |
|---|---|---|
| `super_admin` | Either | Everything (developer / system tier; retains full access permanently) |
| `admin` | Either | Business ownership tier. Currently same permissions as super_admin; over time, loses access to developer-y capabilities (impersonation, raw audit, system config). See ADR-0013. |
| `office_manager` | Back office | Property Bible, workflows admin, inventory management (item/category/PO/approvals), reports |
| `front_desk` | Back office | Supply request fulfillment, uniform issuance, onboarding doc receipt, stock receipt/corrections, PO creation, physical office task workflow steps |
| `hr` | Back office | Applicant pipeline, contractor docs, terminations, PTO admin, personal info change requests |
| `payroll` | Back office | Contracts (view/edit/download), deduction schedules, period close, void/reissue invoices |
| `recruiter` | Back office | Their properties, contractors, workflows, timesheet submission, invoice send |
| `w2_employee` | Back office | Own profile, PTO request, KB read |
| `property_manager` | QC Minute | Their property: live grid, approval, invoices |
| `contractor` | QC Minute | Own hours, paychecks, profile (RO), uniform balance, role-gated KB |
| `device` | qcpstaffing.com/device/* | Clock in/out at its assigned property |

A person can hold multiple roles (an admin who's also a recruiter, for instance). Multi-role users get the union of capabilities.

> **Naming convention in docs and conversation:** When David says "Admin," he means the `admin` role. "Super Admin" means the `super_admin` role. Both retain full access in v1; permissions diverge in v2+ per ADR-0013.

## Permission model

Permissions live in code as Spatie permissions, named consistently:

```
{domain}.{resource}.{action}

Examples:
  bible.contracts.view
  bible.contracts.edit
  bible.rates.edit
  bible.properties.edit
  workflows.termination.initiate
  workflows.termination.complete
  timesheets.submit
  timesheets.approve
  timesheets.decline
  invoices.send
  invoices.void
  imports.upload
  imports.commit
  kb.articles.create
  kb.articles.publish
```

Roles get assigned permissions during seeding. See `10-architecture/permissions-matrix.md` for the full matrix.

## Session and security

- Sessions are HttpOnly, Secure, SameSite=Lax cookies
- CSRF protection on all POST/PUT/DELETE routes (Laravel default)
- Sanctum tokens stored hashed in DB
- Audit log records all login attempts (success + failure)
- Repeated failed logins trigger temporary lockout (Laravel default rate limiter)
- Password requirements: 12 chars minimum, complexity rules per Laravel default
- Forgotten-password tokens expire in 1 hour

## Account creation paths

| Created how | Status assigned | Triggered by |
|---|---|---|
| Public job application | `applicant` | Public site form submission |
| Manual entry by HR | varies | Back-office form |
| Promoted from applicant | `contractor_active` | Recruiter action (workflow) |
| Self-registered | (not allowed) | — |

No public registration. All accounts are created either by application or by an authorized internal user.

## Login as another user (impersonation)

Super admins can impersonate any other user for debugging. Impersonation is heavily audited (every action during an impersonation session is logged with both the real and acting user IDs). Banner on the page makes the impersonation obvious. Cannot impersonate another super admin.

## Logging out

- Standard Laravel logout invalidates the session
- Devices: tokens are revoked from the back office, not by the device itself
- Force-logout-all (e.g. on password reset): all sessions for that user invalidated

## Related

- `10-architecture/domain-routing.md` — how domain access is enforced
- `10-architecture/permissions-matrix.md` — full role × capability table
- `20-domain/people-lifecycle.md` — the `people` table and status transitions
- ADR-0004 — one people table with status, not separate tables
