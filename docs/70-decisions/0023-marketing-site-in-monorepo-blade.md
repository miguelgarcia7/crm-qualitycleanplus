# ADR-0023: Marketing Site In-Monorepo as a Blade Surface

| Field | Value |
|---|---|
| Status | Accepted |
| Decided | 2026-06-06 |
| Owner | Product + Engineering |
| Refines | ADR-0001 |
| Supersedes | — |
| Superseded by | — |

## Context

ADR-0001 ("One App, Two Domains") and `00-context/vision.md` stated that the **public marketing site** (`qualitycleanplus.com`) would **stay a separate Laravel app, out of scope** for this rebuild.

Product has since decided to bring the marketing site **into this codebase**. The reasoning:

- It's only **a few pages** — not worth a separate app, deploy pipeline, and test suite.
- It must be **server-rendered HTML for SEO**. An Inertia/React SPA renders poorly for crawlers, so the app surfaces (React/Inertia) are the wrong tool for marketing.
- Keeping it in-repo means **one deployment** and **shared data** with no cross-app API: the public **job-application form can write directly** to a `person` with `status='applicant'`, and job postings are read straight from the same DB.

This does not change the rest of ADR-0001 (one app, shared DB, domain-routed surfaces) — it adds marketing as a third surface rather than an external app. Hence "Refines ADR-0001", not "Supersedes".

## Decision

The marketing site is a **third surface in this application**, served from `qualitycleanplus.com` via **server-rendered Blade**.

- Routes: `routes/public.php`, mounted on the `qualitycleanplus.com` domain, **no auth**.
- Views: `resources/views/site/*` Blade templates with their own layout.
- Assets: its **own bundle** (`resources/css/site/app.css` + `resources/js/site/app.js`), compiled separately from the React/Inertia app bundles via Vite multi-entry — so marketing pages ship minimal CSS/JS.
- The job-application form **POSTs in-app**, creating a `person` with `status='applicant'` (no external API hop).

## Consequences

### Positive

- Real **SEO** — crawlable server-rendered HTML, lightweight per-page assets
- **One deployment**, one codebase, no API drift; shared data with the rest of the system
- Application form and job postings read/write the canonical DB directly

### Negative

- The app now mixes **Blade and Inertia** rendering — mitigated by a hard surface boundary (separate routes file, layout, and asset bundle)
- Marketing deploys are **coupled** to app deploys (acceptable — same team, same release train)

### Implementation requirements

- `routes/public.php` on the `qualitycleanplus.com` domain (no auth middleware)
- `resources/views/site/` Blade views + a dedicated marketing layout
- Vite multi-entry adds the `site` bundle (`resources/css/site/`, `resources/js/site/`)
- Public application endpoint creates `person` (`status='applicant'`)

## Alternatives considered

### A. Keep it a separate Laravel app (original ADR-0001 stance)

Rejected for the marketing piece. A separate app means a second deploy/test pipeline and no shared data — the application form would need a cross-app API to create applicants, reintroducing the drift this rebuild exists to remove.

### B. Server-side render the React app for marketing (Inertia SSR)

Rejected. Heavier — still ships a React runtime for a handful of static pages. Blade is simpler and lighter for SEO content, and keeps the marketing bundle tiny.

## Related

- ADR-0001 — One app, two domains (this refines it; marketing becomes a third surface)
- ADR-0022 — Frontend stack: React + Inertia + Fortify
- `10-architecture/overview.md`
- `00-context/vision.md`
