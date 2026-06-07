# Deployment Topology

| Field | Value |
|---|---|
| Status | Accepted |
| Last updated | 2026-06-06 |
| Owner | Engineering |

## What gets deployed

**One thing** goes to production — this unified app — serving two domains:

| Deployable | Codebase | Database | Purpose |
|---|---|---|---|
| **The app** (`qualitycleanplus.com` + `qcpstaffing.com` + `/device/*`) | This repo | This DB (MySQL) | Everything — marketing, back office, QC Minute, device clock-in |

The marketing site is **no longer a separate deployable** — it moved into this codebase as a Blade surface (ADR-0023, ADR-0024).

## Domain map

Two production domains, config-driven (local uses Herd `.test` equivalents):

| Domain · path | Surface | Render | Roles allowed |
|---|---|---|---|
| `qualitycleanplus.com/` | Marketing | Blade | Anyone (no auth) |
| `qualitycleanplus.com/admin` | Back office | React/Inertia | Super Admin, Admin, Office Manager, Front Desk, HR, Payroll, Recruiter, W-2 Employee |
| `qcpstaffing.com/` | QC Minute | React/Inertia | Property Manager, Contractor, Admin, Super Admin |
| `qcpstaffing.com/device/*` | Tablet clock-in | — | Devices (Sanctum token, property-locked) |

Local equivalents (Herd): `qcpminute.test` (= `qualitycleanplus.com`) and `qcminute.test` (= `qcpstaffing.com`).

## How marketing integrates with the rest

Because marketing is in the same codebase and database, there's **no cross-app API**:

1. **Job listings** render directly from the shared DB (the active `job_postings`, read in-process).
2. **Applications** — the public form POSTs in-app (CSRF-protected, no pre-shared key) and creates a `person` with status = `applicant`, triggering a recruiter notification.

This removes the previous separate-app integration (the old public JSON endpoint + pre-shared-key POST are gone).

## Infrastructure (suggested, TBD on hosting choice)

- **Application server:** PHP-FPM behind Nginx
- **Queue worker:** at least one, separate process from web
- **Scheduler:** standard Laravel `schedule:run` cron
- **Database:** MySQL 8, managed (RDS / equivalent) for production
- **Redis:** managed (Elasticache / equivalent), for queue + cache
- **Storage:** S3 bucket for documents, KB attachments, contractor photos, contracts
- **Mail:** Postmark
- **CDN:** for static assets (Vite-built JS/CSS) — Cloudfront or equivalent

Three environments:
- **local** — Docker compose, all-in-one
- **staging** — production-like, used for cutover rehearsal and demos
- **production**

## Device authentication path

Tablets at hotels authenticate differently from human users:

```
Tablet at Hotel X
  │
  │ One-time pairing (admin enters 6-digit code from device)
  ▼
POST /device/activate → returns persistent Sanctum token
  │
  │ Token stored in browser local storage
  ▼
All subsequent device requests carry Bearer <token>
  │
  ▼
Token is tied to property_id — device cannot serve other properties
```

- Tokens never expire (revocable by admin)
- Devices can be force-reloaded remotely (broadcast notification on a device channel)
- Devices broadcast clock-in/out events; clock-out can happen from a different device if needed (admin override)

See `40-flows/clock-in-out.md` for the operational flow.

## CI/CD

| Stage | Action |
|---|---|
| On PR | Run Pest tests, Pint formatter, static analysis |
| On merge to main | Auto-deploy to staging |
| On tag | Manual-approve deploy to production |

One pipeline serves both domains because they're one codebase. The public marketing site has its own pipeline (untouched).

## Related

- `10-architecture/overview.md` — the system shape
- `10-architecture/domain-routing.md` — Laravel routing details
- ADR-0001 — One app, two domains
