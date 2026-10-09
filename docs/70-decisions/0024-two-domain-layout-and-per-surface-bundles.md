# ADR-0024: Two-Domain Layout + Per-Surface Asset Bundles

| Field | Value |
|---|---|
| Status | Accepted |
| Decided | 2026-06-06 |
| Owner | Product + Engineering |
| Refines | ADR-0001, ADR-0023 |
| Supersedes | — |
| Superseded by | — |

## Context

ADR-0001 established "one app, multiple domains" and illustrated it with three hostnames: `qcminute.com`, `backoffice.qcpstaffing.com`, and a separate `qualitycleanplus.com` marketing app. ADR-0023 then brought marketing into this codebase.

In review, the owner settled the concrete domain layout, which differs from those placeholders:

- The company already owns **`qualitycleanplus.com`** (its cleaning brand) and **`qcpstaffing.com`** (its staffing brand). There's no `qcminute.com` and no `backoffice.` subdomain.
- The **back office is a path (`/admin`)**, not its own subdomain.
- **Marketing + back office share one domain** (`qualitycleanplus.com`), differing by path.
- Each surface should ship as a **separate asset bundle** (owner's explicit ask), so a heavy React bundle never loads on the lightweight marketing pages.

## Decision

**Two production domains, three surfaces, three asset bundles** (as built: two bundles, QC Minute shares `admin` — see **Amendment 2026-10-08 — two bundles**):

| Domain · path | Surface | Render | Bundle | Views |
|---|---|---|---|---|
| `qualitycleanplus.com/` | Marketing | Blade (SEO) | `site` | `resources/views/site/` |
| `qualitycleanplus.com/admin` | Back office | React + Inertia | `admin` | `resources/js/admin/` · `views/admin/` |
| `qcpstaffing.com/` | QC Minute | React + Inertia | `minute` (built: shares `admin`) | `resources/js/admin/views/minute/` |
| `qcpstaffing.com/device/*` | Tablet clock-in | — (Sanctum API) | — | — |

- Domains are **config-driven** (`config/domains.php` ← `DOMAIN_MAIN`, `DOMAIN_QCMINUTE`), so local Herd `.test` hosts and production `.com` hosts both work without code changes.
- **Local (Herd):** `qcpminute.test` = `qualitycleanplus.com`; `qcminute.test` = `qcpstaffing.com`.
- Asset bundles are produced by **Vite multi-entry**, one entry per surface.

## Consequences

### Positive

- Uses domains the company already owns; no new registrations
- Marketing pages stay lightweight (own small `site` bundle; no React)
- One domain fewer to manage; back office rides the brand domain under `/admin`
- Config-driven domains make local/staging/prod identical in code

### Negative

- Marketing and back office share a domain, so they share one session cookie (path `/`) — see **Amendment 2026-10-08** below
- Two bundles (as built) mean two Vite configs: `vite.config.ts` (`admin`) and `vite-site.config.ts` (`site`)

### Implementation requirements

- `config/domains.php` + `DOMAIN_MAIN` / `DOMAIN_QCMINUTE` env vars
- Route files: `marketing.php` (`/`), `backoffice.php` (`/admin`), `qcminute.php` (`/`), `device.php` (`/device/*`)
- Middleware `allowed_on_backoffice` (on `/admin`) and `allowed_on_qcminute`
- Vite: `vite.config.ts` → `resources/js/admin/app.tsx` (back office + QC Minute); `vite-site.config.ts` → `resources/{css,js}/site/` (marketing, built to `public/site-build`)
- See `10-architecture/domain-routing.md`

## Amendment 2026-10-08 — two bundles

QC Minute never got its own `minute` bundle. Its pages live in the `admin` React app (`resources/js/admin/views/minute/`) and load through the same `resources/js/admin/app.tsx` entry; phase 01 deferred the split as a build optimization, and the owner has since confirmed the shared bundle is the intended layout. The point of the decision — the heavy React app never loads on the marketing pages — holds: marketing is the only separate bundle (`vite-site.config.ts` → `public/site-build`, applied by the `UseMarketingVite` middleware).

## Amendment 2026-10-08 — one session cookie for the whole main domain

The original text said the back office would be "path-scoped under `/admin`". It isn't, and the owner accepted that rather than changing it:

- The session and `XSRF-TOKEN` cookies use path `/` (`SESSION_PATH=/`), so they cover the marketing pages and `/admin` alike. Sign-in is Fortify's `/login`, `/two-factor-challenge` and `/forgot-password`, outside `/admin`, which a `/admin`-scoped cookie would not reach.
- Effect: public visitors get a session cookie (the contact and application forms need one for CSRF anyway), and a signed-in staff member's cookie is also sent on public pages. Nothing on the marketing side reads the user or session beyond CSRF and form flashes.
- Why it's acceptable: the cookie is `HttpOnly`, `SameSite=Lax`, and `Secure` on Laravel Cloud (checked on staging 2026-10-08), and the marketing site renders no user-specific content. `robots.txt` and `sitemap.xml` skip the session entirely.
- Scoping it to `/admin` would mean moving every auth route under `/admin` and giving the marketing forms their own cookie, for no practical security gain.
- `SESSION_DOMAIN` stays unset: the main domain and the QC Minute domain are different registrable domains and can't share a cookie anyway.

## Alternatives considered

### A. Three subdomains (the ADR-0001 placeholders: qcminute.com / backoffice.qcpstaffing.com)

Rejected. Requires domains the company doesn't use, and a separate subdomain for the back office adds DNS/cert overhead for no benefit — a `/admin` path is simpler.

### B. One shared bundle for all surfaces

Rejected. Would load the full React/Inertia app (and its weight) on public marketing pages, hurting SEO/performance. Per-surface bundles keep each surface minimal.

## Related

- ADR-0001 — One app, multiple domains (this fixes the concrete domain layout)
- ADR-0023 — Marketing site in-monorepo as a Blade surface
- ADR-0022 — Frontend stack: React + Inertia + Fortify
- `10-architecture/overview.md`, `10-architecture/domain-routing.md`, `10-architecture/deployment-topology.md`
