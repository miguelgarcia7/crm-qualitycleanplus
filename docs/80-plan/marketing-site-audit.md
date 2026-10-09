# Marketing site audit — legacy vs rebuild

| Field | Value |
|---|---|
| Status | ✅ Code work done; open items are decisions, sign-offs and go-live steps — see **What's left** |
| Last updated | 2026-10-08 |
| Owner | Engineering |
| Compares | Legacy `~/Code/www.qualitycleanplus.site` (Laravel 11, public site at `/` + `/es/*`) vs this app's marketing surface (`routes/marketing.php`, `resources/views/site/`, ADR-0023) |

## Summary

At audit time (2026-10-04) the rebuild was a port of only the **English** half of the
legacy site: leads weren't delivered, there was no spam protection, Spanish and
testimonials were missing, and legacy bugs had been copied over. As of 2026-10-08 every
code item from this audit is done (see **Done**). What's left is decisions and sign-offs
for Miguel and the go-live steps in the cutover runbook — listed in **What's left**.

Bundle layout is settled: back office + QC Minute share the React/Inertia bundle; the
marketing site is the only separate bundle (`vite-site.config.ts`), Blade-rendered for SEO.
ADR-0024 is amended to say so (2026-10-08).

## What's left (2026-10-08)

**Decisions and sign-offs for Miguel**
- **Production lead recipients.** `MARKETING_JOB_SEEKERS_TO` / `MARKETING_BUSINESS_TO`
  point at Miguel on staging; legacy sent leads to d.aguilar@. Set at go-live (cutover
  runbook env-var table).
- **Home `<title>`** says "Quality Cleaning Experts for Residential and Commercial" while
  the hero says "The Hospitality Experts". Pick the message Google should show
  (`lang/{en,es}/site/home.php`, `meta.title`).
- **Privacy Policy / Terms of Use sign-off** (`/privacy-policy`, `/terms-of-use`, Spanish
  twins; text in `lang/{en,es}/site/{privacy,terms}.php`, English is the reference). Not
  legal advice — have counsel review. Confirm these statements are true:
  - Applicant details may be shared with **client businesses** where we might place them.
  - Applicant/employee records kept **up to seven years** after closing (see the retention
    follow-up below); contact messages "as long as needed".
  - We **don't sell** personal information or share it for targeted advertising (holds
    while GA's Google Signals / ads features stay off).
  - Requests to see, correct or delete information go through the Contact page / phone /
    mailing address — or name a privacy email address to list instead.
  - Terms: employment is **at will**, Quality Cleaning Plus is an **equal opportunity
    employer**, Texas law with **Dallas County** courts.
- **Native-speaker review of the Spanish**: the legal pages, and six job terms with no
  legacy wording — "Mozos de limpieza" (Houseman), "Ayudantes de mesero" (Bussers),
  "Montaje de banquetes" (Set Up), "Auxiliares de cocina" (Stewarding), "Empaque"
  (Packaging), "Meseros" (Servers) — `lang/es/site/services.php`.
- **Google for Jobs** — parked until pay can be published (minimum or range); see the
  parked section at the end.

**Go-live** (the steps live in `phase-final-cutover.md`, runbook step 6 and the env-var
table): drop "(New)" from `APP_NAME`; attach `qualitycleanplus.com` **and**
`www.qualitycleanplus.com`; `APP_ENV=production`, `DEMO_MODE` off; production lead
recipients; run `RolePermissionSeeder` (testimonials + inquiries permissions); submit the
sitemap in Search Console; check a staff email-signature image under `/images/emails/`
still loads on the www host.

**Follow-ups outside the marketing site**
- **Retention purge isn't built.** The privacy policy's "up to seven years" matches the
  period `docs/20-domain/audit-and-pii.md` designs for `people`, but there is no
  `RetentionPurge` job or `config/retention.php` yet, so nothing is removed
  automatically.

## Decisions (2026-10-04)

| # | Topic | Decision |
|---|---|---|
| D1 | Spanish | **Required at launch.** Full `/es/*` parity with the English pages. |
| D2 | Testimonials | **Bring back**, managed in the back office. Data **migrated from the `qualitycleanplus` DB** (phase-2 selective import). |
| D3 | Spam protection | **Required.** Google reCAPTCHA v3 (free tier), as legacy uses today. |
| D4 | `/partners/baseball` | **Do not port.** |
| D5 | Home hero video | **Do not port.** Replace the `<video>` hero with a static image. |
| D6 | Contact-form recipients | **`.env`-configured, one list per form** (`MARKETING_JOB_SEEKERS_TO`, `MARKETING_BUSINESS_TO`, comma-separated). **Spanish forms use the same recipients as English** (no separate a.aguilar route). Blank = stored but not emailed. |

## Must fix before cutover

1. ~~**Contact leads go nowhere.**~~ Fixed 2026-10-04: `ContactInquiryReceived` (queued)
   emails each stored inquiry to `MARKETING_JOB_SEEKERS_TO` / `MARKETING_BUSINESS_TO`
   (comma-separated; Spanish shares them), reply-to the visitor, sent from
   `MAIL_FROM_ADDRESS`, with the reCAPTCHA verdict as a "Spam check" line. Needs a queue
   worker and the vars set on Cloud. Still no back-office inbox for `contact_inquiries`.
2. ~~**Draft/closed postings are publicly reachable.**~~ Fixed 2026-10-04:
   `ApplicationController::apply` 404s unless `status->isPublic()`; `store` links only a
   published posting (a posting closed mid-form is dropped, the application still lands
   with the typed position). Covered in `tests/Feature/JobApplicationTest.php`.
3. ~~**No spam protection or throttle** on the 3 public POSTs.~~ Fixed 2026-10-04:
   reCAPTCHA v3 (`App\Domain\Marketing\Support\Recaptcha` via the form requests'
   `ChecksRecaptcha` concern, `site.elements.recaptcha`) on both contact forms
   (action `contact`) and the application (`application`). **Middle ground** (revised
   with #1): rejects only a missing token or a score Google gave below
   `RECAPTCHA_MIN_SCORE`; anything that prevents a verdict (Google error such as
   `browser-error`, unreachable, token for another form/host) is let through, logged,
   and flagged in the lead email. Off while `RECAPTCHA_*` keys are blank. Plus
   `throttle:marketing-forms` (10/min, 60/hour per IP).
   The key's domain list must include every host the forms run on (local `.test`,
   staging, production) or Google returns `browser-error`.
4. ~~**Page titles / `og:site_name` read "Minute".**~~ Fixed locally 2026-10-04:
   `APP_NAME="Quality Cleaning Plus (New)"`. **"(New)" is a deliberate temporary marker**
   to tell the rebuild apart from the live legacy site — **drop it at cutover** (titles and
   `og:site_name` are public/SEO-visible). Set on staging 2026-10-05; `.env.example` still
   says `Minute`. Note one `APP_NAME` also drives the back-office/QC Minute tab titles and
   the session cookie name (changing it logs everyone out once).
5. ~~**Public form can attach to existing staff records.**~~ Fixed 2026-10-05 (option b):
   only a live applicant re-applying is linked automatically. Any other email match
   (staff, contractor, archived) lands on a new applicant with a placeholder email,
   flagged with `matched_person_id`; the recruiter links it ("Same person", restoring an
   archived record and archiving the placeholder) or dismisses it before review starts.
   See people-lifecycle.md, "Email & soft deletes".

## Parity

| Feature | Legacy | Rebuild | Status |
|---|---|---|---|
| Home / Services / About / Contact | ✅ | ✅ same copy & layout | Parity |
| Job openings list | `Position` status=1 | `JobPosting::published()` + empty state | ✅ Better |
| Single posting page (`content`, `pay_range`) | ❌ not shown | ❌ not shown (planned in 08b-i Inc 2, not built) | Gap in both — parked with Google for Jobs |
| Application form | `Applicant::create($request->all())`, trusts hidden `status`, no captcha | `SubmitApplication` → Person(applicant) + JobApplication | ✅ Better (see must-fix 2, 5) |
| Job-seeker contact form | Email to staff | DB + email (`MARKETING_JOB_SEEKERS_TO`) | ✅ Fixed (must-fix 1) |
| Business inquiry form | Email to staff | DB + email (`MARKETING_BUSINESS_TO`) | ✅ Fixed (must-fix 1) |
| Lead inbox in the back office | ❌ email only | ✅ `/admin/inquiries` (Website › Contact Inquiries) | ✅ Done 2026-10-06 |
| reCAPTCHA v3 on contact forms | ✅ | ✅ contact forms + application, plus rate limit | ✅ Fixed (D3) |
| Spanish `/es/*` | ✅ 10 pages (2 broken) | ✅ all 9 pages, shared views + `lang/{en,es}/site/*` | ✅ Done 2026-10-05 (D1) |
| Testimonials carousel | ✅ `testimonials` table, CMS-managed | ✅ `Testimonial` model, `/admin/testimonials` (Website › Testimonials), imported from legacy | ✅ Done 2026-10-05 (D2) |
| Hero video | ✅ | Static still of the same Dallas skyline | ✅ Replaced 2026-10-05 (D5) |
| `/partners/baseball` | ✅ | 301 → `/` | ✅ Not porting (D4) |
| Nav login link + language switcher | ✅ | Switcher to the same page in the other language; no login link | ✅ Done (D1) |
| Google Analytics `G-ZQSBDK2ZRN` | ✅ | ✅ production only (`GOOGLE_ANALYTICS_ID`) | ✅ Done 2026-10-05 |
| Sitemap / hreflang / JSON-LD | ❌ | ✅ `/sitemap.xml` (both languages + open postings), hreflang, EmploymentAgency JSON-LD | ✅ Done 2026-10-05 |
| Privacy / terms pages | ❌ | ✅ `/privacy-policy`, `/terms-of-use` + Spanish, linked from footer and forms | ✅ Drafted 2026-10-05 — sign-off open |
| Branded error pages | ❌ | ✅ 403/404/419/429/500/503 in the site layout, EN/ES (`SiteErrorPage`); expired forms return with answers kept | ✅ Done 2026-10-05 (back office + QC Minute too) |

## Legacy contact routing

All recipients and the sender are **hardcoded** — nothing comes from config or `.env`.

| Form | Recipient | Subject | Code |
|---|---|---|---|
| EN job seekers | `d.aguilar@qualitycleanplus.com` | "Quality Cleaning Plus: Contact Form" | `ContactController.php:41` |
| ES job seekers | `a.aguilar@qualitycleanplus.com` | same | `ContactController.php:105` |
| EN business inquiries | `d.aguilar@qualitycleanplus.com` | "Business Inquiry Web Form" | `ContactController.php:75` |
| ES business inquiries | `d.aguilar@qualitycleanplus.com` (unreachable — page broken) | same | `ContactController.php:139` |
| Job application (EN/ES) | **no email** — DB only (`applicants`) | — | `ApplicantController.php:82,120` |

- Sender is `web@appazul.com` "Website Form" (`app/Notifications/ContactNotification.php:48`,
  `ContactBusinessNotification.php:45`), overriding the app's `MAIL_FROM_*`. A third-party
  domain as sender is a deliverability risk (SPF/DMARC alignment).
- Emails are plain field dumps; no reply-to set to the submitter.

**Rebuild direction:** recipients in config (per form type, per language if still needed),
send through the 09g transactional mail setup, set reply-to to the submitter, and add a
back-office inbox for `contact_inquiries` so leads aren't email-only.

## Spam protection

**Legacy (today):** reCAPTCHA **v3** (score-based, invisible) on both contact forms only.
- `api.js?render={key}` + `grecaptcha.execute(key, {action:'contact_us_form'})` on submit.
- Server rule `app/Rules/ReCaptcha.php`: GET `siteverify`, passes if `success && score > 0.5`.
  Does **not** check `action` or `hostname`.
- Keys via `GOOGLE_RECAPTCHA_KEY` / `GOOGLE_RECAPTCHA_SECRET`, read with `env()` at runtime
  (returns null under `config:cache`).
- Failure message is the placeholder "The validation error message." and no view renders the
  error, so a low score silently reloads the form.
- Application form: **no** captcha.

**Rebuild at audit time:** nothing (built since — see must-fix 3). The rebuild now uses its
own key (`6LfYPJ…`); the legacy key (`6LdE5P…`) stays with the legacy site.

**Free tier:** reCAPTCHA moved into Google Cloud; Classic keys were auto-migrated to Cloud
projects (Q4 2025–Q1 2026). Free tier is **10,000 assessments/month**; beyond that billing
must be enabled or requests error. Our volume (3 low-traffic forms) is far below that.
Before reuse, confirm in Google Cloud console that the legacy key's project exists, that
it's the v3/score type, and that its allowed domains include production and the staging
host.

**Rebuild direction:** v3 on all three public POSTs (contact ×2 **and** application);
keys in `config/services.php`; server check of `success`, `score` threshold, `action` and
`hostname`; a real error message rendered in the form; plus `throttle` middleware on the
marketing POSTs as a second layer. Script only on form pages (keeps the bundle light).

## Spanish (D1) — built 2026-10-05

As built: one set of views; copy in `lang/{en,es}/site/*.php` (layout, forms, home,
services, about, contact, job_seekers, business, jobs, application, thanks — identical key
structure in both languages). `SetSiteLocale` sets Spanish for anything under `/es`;
every page has a `marketing.es.*` route with the legacy slugs below. Views get `$site`
(`App\Domain\Marketing\Support\SiteLocale`) and build every link through it, so Spanish
pages link to Spanish pages and forms post to Spanish routes (validation errors and the
thank-you page come back in Spanish; `lang/es/validation.php` covers the form rules and
field names). The nav switcher and `hreflang` (+ `x-default` = English) point at the same
page in the other language; canonical is now the page's own URL; `og:locale` and
`<html lang>` follow the language. Lead emails stay English for staff, with a
"Language: Spanish" line when sent from the Spanish site. Covered by
`tests/Feature/MarketingSpanishTest.php`.

Spanish wording came from the legacy ES views with accents/typos fixed, register made
consistently formal (usted), and everything legacy left in English translated. Worth a
native-speaker pass on new terms: "Mozos de limpieza" (Houseman), "Ayudantes de mesero"
(Bussers), "Montaje de banquetes" (Set Up), "Auxiliares de cocina" (Stewarding), "Empaque"
(Packaging), "Meseros" (Servers).

Legacy ES URLs (keep these slugs for SEO continuity, or 301 them):

| EN | ES |
|---|---|
| `/` | `/es` |
| `/services` | `/es/servicios` |
| `/about-us` | `/es/quienes_somos` |
| `/contact-us` | `/es/contactenos` |
| `/job-openings` | `/es/ofertas-de-trabajo` |
| `/contact-us/job-seekers` | `/es/contactenos/solicitantes-de-empleo` |
| `/contact-us/business-inquiries` | `/es/contactenos/consultas-para-negocios` |
| `/application`, `/application/{job}` | `/es/solicitud`, `/es/solicitud/{job}` |
| `/application/thank-you` | `/es/solicitud/gracias` |

Needs: language switcher linking to the **equivalent** page (legacy links home only),
`hreflang` alternates, `<html lang>` and `og:locale` per locale, and translations for the
strings legacy left in English (job-table headers, footer hours/copyright, "Get Driving
Directions", thank-you title). Existing ES copy in legacy `resources/views/site/es/` is the
translation source — it has typos to fix ("limpiez", "negocion", "Quienes Nosotros",
"pronoto").

## Testimonials (D2) — built 2026-10-05

- **Model:** `App\Domain\Marketing\Models\Testimonial` — name, quote (≤1000), `source`
  (`TestimonialSource`: google/facebook/instagram/tiktok/twitter — names the badge icon),
  rating 1–5, `is_active`, optional photo as a `File` on the default (private) disk.
- **Back office:** `/admin/testimonials` under a new **Website** nav group; list + side form
  (photo upload/replace/remove), Hide/Show, delete. Permission
  `marketing.testimonials.manage` (admin, office_manager).
- **Site:** home page shows active ones newest first; photos stream from
  `/testimonials/{id}/photo?v={file}` (public cache, active only — the bucket is private).
- **Import:** `php artisan legacy:import-testimonials --media-root=<legacy public/>` over the
  `legacy_qcp` connection (`LEGACY_QCP_DB_*`, default database `qualitycleanplus`).
  Idempotent via `legacy_id_map` (`qcp_testimonial`); a re-run refreshes text/rating/
  visibility from legacy (overwriting edits made here) and copies a photo only once.
  Local run 2026-10-05: 19 imported (16 on site), 3 photos copied.
- The Spanish home page shows the same testimonials (D1, done).

## Ported bugs (present in both) — status 2026-10-08

- ✅ Canonical hardcoded to the www homepage — now each page's own URL.
- ✅ Invalid `twitter:site`/`creator` handle, missing `twitter:card`, malformed
  `DC.date.issued` — fixed (SEO clean-up).
- ✅ Missing `/images/safari-pinned-tab.svg` reference — removed.
- ✅ Contact pages sharing one title/description; empty thank-you description — each has its own.
- ✅ Yes/no radio errors never displayed — fixed (Spanish work). ✅ `position` posted twice;
  unused hidden `application_date`/`status` — fixed 2026-10-05.
- ✅ Business form phone required in HTML, optional on the server — now required on the
  server too (2026-10-05).
  ✅ Inquiry-type `aria-label="Call Back Time"` — fixed.
- ✅ Typos ("Employement", "Jobs Openings", "custruction", "corrrect", "If the answer if
  yes", "eligible able", "Have you work for", "go about and beyond") — fixed.
- ✅ Footer: static year, `&copy` without `;`, address order, Bootstrap 3/4 classes — fixed.
- ✅ `.ui_animate { opacity: 0 }` depends on CDN GSAP — GSAP bundled, CSS fallback reveals
  content if scripts never run (2026-10-05).

## Rebuild-only issues — status 2026-10-08

- ✅ **Nunito never applies** — kept the system font (Miguel's call); the unused Google
  Fonts request is gone (2026-10-05).
- ✅ Bootstrap CSS ^5.3.8 vs CDN JS 5.1.3; jQuery unused — collapse + GSAP bundled from
  npm, jQuery dropped (2026-10-05); Swiper and flatpickr bundled too (2026-10-08). The site
  loads nothing from a CDN.
- ✅ Shared session cookie across `/` and `/admin` — accepted; ADR-0024 amended
  (2026-10-08).
- ✅ `JobPosting::locationName()` on a soft-deleted property — confirmed 500, fixed
  (`property()` includes archived properties, 2026-10-05).
- ✅ Nav active state misses sub-pages — fixed (`SiteLocale::is()` matches sub-pages).
- ✅ `robots.txt` allowed everything — now generated per environment (SEO clean-up).
- Demo seeder publishes "Acme Hotel" postings — visible on the public board in demo (expected).

## Legacy bugs — do not port

- `/es/ofertas-de-trabajo` routes to `CMS\PositionController` (auth + missing method) —
  ES job board is broken in production (`routes/web.php:74`).
- `/es/contactenos/consultas-para-negocios` has two POSTs and no GET; view path missing
  `site.` prefix (`routes/web.php:80-81`, `ContactController.php:113`).
- ES apply pre-fill broken (`{job}` vs `Position $position`).
- `uix_con_todo` email obfuscator assigns undeclared globals under module strict mode.

## Doc drift — corrected 2026-10-08

ADR-0023 (`routes/marketing.php`, `app.scss`, own Vite config), ADR-0024 (amended: two
bundles; shared session cookie), ADR-0001 (route file name), 08b-i (`public/site-build`,
`public/images/`), `overview.md`, `domain-routing.md`, `identity-and-auth.md` (`/login`,
`POST /application`, no recruiter notification) and `deployment-topology.md` now match
the code.

## SEO clean-up — done 2026-10-05

- **robots.txt / sitemap.xml are generated** (`SeoController`; the static
  `public/robots.txt` is gone). `Seo::indexable()` = production and not demo: only then
  does robots.txt allow crawling (minus `/admin` and the sign-in pages) and point at
  `/sitemap.xml`; everywhere else, and always on the QC Minute domain, it disallows all
  and the sitemap 404s. The sitemap lists every page in both languages with hreflang
  alternates, plus each published posting's application page; thank-you pages are
  `noindex` and left out.
- **www → apex 301** keeping path + query (`www.{DOMAIN_MAIN}` catch-all). Legacy links and
  the search index use `www.qualitycleanplus.com`; the host must be attached to the
  Cloud environment at cutover or those links 404.
- **Legacy URLs redirected:** `/partners/baseball` → `/`; numeric posting links
  (`/application/{id}`, `/es/solicitud/{id}`) → the application form.
- **Analytics** only on the real public site; id in `GOOGLE_ANALYTICS_ID`.
- **Head tags:** canonical = the page itself (was always the www homepage), hreflang +
  x-default, `twitter:card`; the invalid twitter handle, malformed `DC.date.issued` and
  the missing `safari-pinned-tab.svg` are gone; share images use this host.
- **Structured data:** schema.org `EmploymentAgency` (address, phone, hours, socials) on
  the home page in both languages.
- **Not done:** Google for Jobs — parked, see below. Home `<title>` says "Residential and
  Commercial" while the hero says "The Hospitality Experts" — a copy decision.

## Error pages — done 2026-10-05

`App\Domain\Marketing\Support\SiteErrorPage`, hooked as an exception `respond` callback
in `bootstrap/app.php`, swaps the finished error response for `site.pages.error` (site
layout, nav + footer, `noindex`) when it's a visitor's page on the main domain: not
`/admin`, not the sign-in screens, not JSON/Inertia. Covers 403, 404, 419, 429 (keeps
`Retry-After`), 500 (only with debug off — the stack trace stays while debugging) and 503
(maintenance). Language from the path (`/es` → Spanish); an unknown URL never reaches the
marketing route group, so it also sets the locale and the site's Vite bundle itself.

A form posted after its session expired (419) doesn't get an error page: it goes back to
the form with the answers kept (minus `_token` / reCAPTCHA token) and a "please send it
again" note by the submit button — the long application isn't lost.

Back office, QC Minute and the sign-in screens (added 2026-10-05): `App\Support\AppErrorPage`
renders the Inertia `error` page (`resources/js/admin/views/error.tsx`), built from the
reference library's error cards (`design-reference/error/*`): logo, gradient status code,
title, message, "Back to Home" (dashboard on the back office, `/` on QC Minute) and "Go
Back"; the maintenance illustration for 503; "Try Again" for 500. A 403 keeps the app's own
reason (e.g. "This account cannot access QC Minute.") and, when signed in, the "Sign out and
use a different account" button the old Blade 403 had (`errors/403.blade.php` removed). A
419 sends the user back for a fresh token. JSON/API clients keep JSON errors; 500 shows the
stack trace while debugging.

## Parked: posting pages for Google for Jobs (2026-10-05)

**Status: on hold — waiting on approval to publish pay** (a minimum or a range). Without
pay on the listing it's probably not worth doing: Google ranks and shows listings
without pay worse, and applicants skip them. Revisit once that's decided.

**What it is.** Google for Jobs builds its job-search results from job pages that carry
schema.org `JobPosting` structured data. Free; for a staffing company often the biggest
applicant source after Indeed. Google requires **one page per job** with the job visible
on it (not allowed on a list page like `/job-openings`), and these fields — required:
title, full description, date posted, hiring organization, job location (an address);
recommended: employment type, **salary**, an end date. Closed jobs must stop claiming to
be open (marked expired / noindex) and leave the sitemap.

**Why we can't today.** `/job-openings` is a list (title, hours, location).
`/application/{slug}` is the form; its sidebar shows title/location/hours but **not**
the description (`content`) or `pay_range`, though both exist in the admin.

**What would be built.**
- `/job-openings/{slug}` and `/es/ofertas-de-trabajo/{slug}`: title, description, pay,
  hours, location, Apply → the form. The job list links to it; the sitemap lists it
  instead of the form pages.
- `JobPosting` JSON-LD from the posting; closed postings show "this job has been filled"
  with a link to current openings, `noindex`, and drop out of the (cached) sitemap.
- About the size of the testimonials work: page, structured data, a few posting fields,
  Spanish, tests.

**Decisions needed before building.**
1. **Pay (the blocker).** Approval to show a minimum or a range. `pay_range` is free text
   ("$15 – $18 / hr"); Google needs structured min / max / unit (hour, year…), so the
   posting form would gain those fields.
2. **Location.** Google needs an address, at least city + state. (a) city/state only —
   recommended, doesn't reveal which client property is staffed; or (b) the linked
   property's full address. Postings without a property only have free-text
   `location_label`, so city/state would become structured fields.
3. **Employment type.** Contractors are 1099 → `CONTRACTOR` (or `TEMPORARY`); same for
   every posting, or per posting?

## Done (2026-10-04 → 2026-10-08)

- **2026-10-04 → 05:** must-fix 1–5; reCAPTCHA v3 + rate limit (D3, flag-not-block,
  badge hidden with Google's notice); lead emails (D6); testimonials (D2) with the legacy
  import; Spanish (D1); SEO clean-up (generated robots/sitemap with caching, www redirect,
  legacy redirects, production-only analytics, structured data); branded error pages for
  the marketing site, back office and QC Minute; staging env vars set; cutover runbook
  env-var table.
- **Small fixes (2026-10-05):** static Dallas hero image (D5); Bootstrap collapse + GSAP
  bundled, jQuery dropped; scroll-reveal fallback; application form leftovers; business
  phone required server-side; archived-property 500 on the job board; unused legacy images
  removed (`images/emails/` kept for staff signatures); `.env.example` APP_NAME; unused
  Nunito request dropped.
- **Contact Inquiries inbox (2026-10-05/06):** Website → Contact Inquiries
  (`/admin/inquiries`, `marketing.inquiries.manage`: admin, office manager, recruiter) —
  filters, search, detail popup, mark handled (who + when), delete spam; the lead email
  links to it; leads store `locale` + `spam_check`.
- **Privacy Policy + Terms of Use drafted (2026-10-05, Cloudflare cookie added
  2026-10-08):** both languages, in the sitemap, linked from the footer and under every
  form; a test keeps every Spanish site-string file in step with its English twin.
  Sign-off still open (above).
- **2026-10-08:** shared session cookie accepted (ADR-0024 amended); Swiper and flatpickr
  bundled as per-page chunks — no CDN left; doc drift corrected; recruiters and office
  managers get an in-app alert for each website application (`ApplicationReceived`,
  mutable "New applications" category).
