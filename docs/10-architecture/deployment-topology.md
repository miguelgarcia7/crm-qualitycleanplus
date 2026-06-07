# Deployment Topology

| Field | Value |
|---|---|
| Status | Accepted |
| Last updated | 2026-05-21 |
| Owner | Engineering |

## What gets deployed

Three things go to production:

| Deployable | Codebase | Database | Purpose |
|---|---|---|---|
| **Main app** (qcminute.com + backoffice.qcpstaffing.com + /device/*) | This repo | This DB (MySQL) | Everything we're building |
| **Public marketing site** | Existing legacy repo (`www.qualitycleanplus.site`) | None (or its own small one) | Marketing pages, job listings, public application form |
| **Job listings feed** | Endpoint on the main app | This DB | The public site fetches active job postings via JSON to render them |

## Domain map

| Domain | Hosts | Roles allowed |
|---|---|---|
| `qualitycleanplus.com` | Main app, marketing route group (Blade surface, own asset bundle) | Anyone (no auth) |
| `qcminute.com` | Main app, QC Minute route group | Property Manager, Contractor, Super Admin |
| `backoffice.qcpstaffing.com` | Main app, back office route group | Super Admin, Office Manager, HR, Payroll, Recruiter, W-2 Employee |
| `qcminute.com/device/*` | Main app, device route group (Sanctum) | Devices (token-authenticated, property-locked) |

Final domain names TBD. The structure stands regardless of the exact names chosen.

## How the public site stays integrated

The marketing site is a separate codebase and database, but it needs to:

1. **Display current job listings.** It fetches them via a public JSON endpoint on the main app:
   ```
   GET https://backoffice.qcpstaffing.com/api/public/job-postings
   ```
   Returns active postings (title, description, location, posted date). No auth.

2. **Submit applications.** The application form POSTs to the main app:
   ```
   POST https://backoffice.qcpstaffing.com/api/public/applications
   ```
   Creates a `person` row with status = `applicant`. Returns success page redirect URL.

No tighter coupling. The public site has no other access to the main database.

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
