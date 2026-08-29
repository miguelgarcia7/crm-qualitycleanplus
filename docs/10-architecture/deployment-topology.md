# Deployment Topology

| Field | Value |
|---|---|
| Status | Accepted |
| Last updated | 2026-08-24 |
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

## Production environment variables

A managed host (Laravel Cloud or equivalent) injects `DB_*`, `REDIS_*` and the
object-storage credentials when you attach those resources. Everything below is
set by hand.

```
APP_ENV=production
APP_DEBUG=false
APP_KEY=                                  # php artisan key:generate --show
APP_URL=https://qualitycleanplus.com

# Both hostnames must be attached to the SAME application — one deployable
# serves both surfaces (ADR-0024).
DOMAIN_MAIN=qualitycleanplus.com
DOMAIN_QCMINUTE=qcpstaffing.com

DB_CONNECTION=mysql
FILESYSTEM_DISK=s3
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=database
SESSION_DOMAIN=null                       # two registrable domains cannot share a cookie
BROADCAST_CONNECTION=reverb

MAIL_MAILER=postmark
POSTMARK_API_KEY=
MAIL_FROM_ADDRESS=                        # must be a verified Postmark sender signature
MAIL_FROM_NAME="QCP Staffing"

REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=
REVERB_PORT=443
REVERB_SCHEME=https
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

### Gotchas

**`FILESYSTEM_DISK` must be `s3` from the first deploy.** Container filesystems
are ephemeral, so on `local` every uploaded contract, signed I-9, ID scan,
clock-in selfie and KB attachment disappears on redeploy. Worse, `files` stores
the disk per row, so anything written while it was `local` keeps pointing at a
disk whose contents are gone.

**`VITE_*` variables are build-time.** They are compiled into the JS bundle and
must be present when `npm run build` runs; setting them afterwards does nothing
until the next deploy.

**Reverb needs a persistent WebSocket process**, a different shape from a
PHP-FPM web container — check the host's support before assuming it. Only the
live timesheet grid (`TimeEntrySaved`) uses broadcasting; `pusher` is a drop-in
alternative and everything else works without it.

**A queue worker is not optional.** `RecomputeTimeSummary` is dispatched on
every clock-out, so without a worker hours never roll up into timesheets.

**`QCP_INVOICER_*` is now only a seed default.** Company identity lives in the
`settings` table and is edited at `/admin/settings/company`; the environment
seeds it on a fresh install and is ignored afterwards. Set it there, or leave
it unset and fill the form after deploying.

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
