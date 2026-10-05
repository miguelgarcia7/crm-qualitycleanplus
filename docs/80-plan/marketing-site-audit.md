# Marketing site audit — legacy vs rebuild

| Field | Value |
|---|---|
| Status | 📋 Audit complete; decisions recorded, fixes not started |
| Last updated | 2026-10-04 |
| Owner | Engineering |
| Compares | Legacy `~/Code/www.qualitycleanplus.site` (Laravel 11, public site at `/` + `/es/*`) vs this app's marketing surface (`routes/marketing.php`, `resources/views/site/`, ADR-0023) |

## Summary

The rebuild's marketing surface is a faithful port of the **English** half of the legacy
site, and its application flow is stronger than legacy (Person + JobApplication, visible
at `/admin/applicants`). It is **not cutover-ready**: contact-form leads are no longer
delivered to anyone, there is no spam protection, Spanish and testimonials are missing,
and a number of legacy bugs/typos were ported verbatim.

Bundle layout is settled: back office + QC Minute share the React/Inertia bundle; the
marketing site is the only separate bundle (`vite-site.config.ts`), Blade-rendered for SEO.
ADR-0024's three-bundle wording is stale on that point.

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
   reCAPTCHA v3 (`App\Rules\Recaptcha`, `site.elements.recaptcha`) on both contact forms
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
   `og:site_name` are public/SEO-visible). Still to do: set it on the Cloud staging env and
   `.env.example`. Note one `APP_NAME` also drives the back-office/QC Minute tab titles and
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
| Single posting page (`content`, `pay_range`) | ❌ not shown | ❌ not shown (planned in 08b-i Inc 2, not built) | Gap in both |
| Application form | `Applicant::create($request->all())`, trusts hidden `status`, no captcha | `SubmitApplication` → Person(applicant) + JobApplication | ✅ Better (see must-fix 2, 5) |
| Job-seeker contact form | Email to staff | DB only | ❌ **Regression** |
| Business inquiry form | Email to staff | DB only | ❌ **Regression** |
| reCAPTCHA v3 on contact forms | ✅ | ❌ stripped | ❌ Regression (D3) |
| Spanish `/es/*` | ✅ 10 pages (2 broken) | ❌ | ❌ Required (D1) |
| Testimonials carousel | ✅ `testimonials` table, CMS-managed | View exists, always empty collection | ❌ Required (D2) |
| Hero video | ✅ | Broken reference (`public/media` absent) | Remove (D5) |
| `/partners/baseball` | ✅ | ❌ | Not porting (D4) |
| Nav login link + language switcher | ✅ | ❌ stripped | Switcher needed for D1 |
| Google Analytics `G-ZQSBDK2ZRN` | ✅ | ✅ but loads in every env incl. demo | Gate to production |
| Sitemap / hreflang / JSON-LD | ❌ | ❌ | SEO gap in both (hreflang needed for D1) |
| Privacy / terms pages | ❌ | ❌ | Gap — the application collects DOB + felony data |
| Branded 404 / 419 | ❌ | ❌ (only `errors/403`) | Gap in both |

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

**Rebuild (today):** nothing. The legacy site key survives in a Blade comment
(`resources/views/site/pages/contact_us_employees.blade.php:109-112`) and is the same key
legacy uses. No `RECAPTCHA` env vars, config, or rule exist.

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

## Spanish (D1) — scope

Legacy builds Spanish by **duplicating** views and `es_*` controller methods with hardcoded
copy; `lang/` is unused. Do not port that pattern — use lang files + a `/es` route prefix
sharing one set of views.

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

## Testimonials (D2)

- Legacy table `testimonials`: `name`, `quote`, `photo`, `social_media`, `rating`, `status`
  (public: `status = 1`, newest first). Photos under legacy `public/media/YYYY/MM/`; social
  icons `/images/icons/social-media-{facebook,google,instagram,tiktok,twitter}.svg`.
- Rebuild view `resources/views/site/components/testimonials.blade.php` already expects
  those fields; `PageController::home` passes `collect()`.
- Work: model + migration + back-office CRUD + public query; import step from the
  `qualitycleanplus` DB with photos copied to object storage. Show on ES home too (legacy
  omitted it).

## Ported bugs (present in both)

- Canonical hardcoded to `https://www.qualitycleanplus.com/` on every page
  (`site/elements/meta_data.blade.php:4`); rebuild's main domain is configured without `www`.
- `twitter:site`/`creator` `@QualityCleaningPlus` exceeds the 15-char handle limit; no
  `twitter:card`. `DC.date.issued` malformed (`:17`).
- `/images/safari-pinned-tab.svg` referenced, missing.
- Contact, job-seekers and business pages share one title + description; thank-you has none.
- Application form: yes/no radio errors never display (`.invalid-feedback` without
  `.is-invalid`); `position` posted twice when a job is set; unused hidden
  `application_date`/`status`.
- Business form: phone required in HTML, nullable on server; inquiry-type select has
  `aria-label="Call Back Time"`.
- Typos: "Employement", "Jobs Openings", "custruction", "corrrect", "If the answer if yes",
  "eligible able", "Have you work for", "go about and beyond".
- Footer: static "© 2022", `&copy` without `;`, address order "Dallas, Texas 75235, Suite
  126", Bootstrap 3/4 classes (`text-right`, `col-xs-12`).
- `.ui_animate { opacity: 0 }` depends on CDN GSAP; if it fails, content stays invisible.

## Rebuild-only issues

- **Nunito never applies** — `_variables.scss` is imported after Bootstrap
  (`resources/css/site/app.scss`), so `$font-family-sans-serif` is ignored; built CSS has no
  "Nunito".
- Bootstrap CSS ^5.3.8 vs CDN JS 5.1.3; jQuery loaded, unused; Swiper loads on home with no
  testimonials to show.
- Shared session cookie across `/` and `/admin` (path `/`, name from `APP_NAME`); Fortify
  login at root `/login`. Contradicts ADR-0024's "path-scoped under `/admin`".
- `JobPosting::locationName()` reads `$this->property->name`; a published posting on a
  soft-deleted property likely 500s `/job-openings` (unverified).
- Nav active state misses sub-pages (`/contact-us/*`, `/application*`).
- `robots.txt` allows everything (no `/admin` disallow, no `Sitemap:`); demo env relies on
  the `NoIndexInDemoMode` header.
- Demo seeder publishes "Acme Hotel" postings — visible on the public board in demo (expected).

## Legacy bugs — do not port

- `/es/ofertas-de-trabajo` routes to `CMS\PositionController` (auth + missing method) —
  ES job board is broken in production (`routes/web.php:74`).
- `/es/contactenos/consultas-para-negocios` has two POSTs and no GET; view path missing
  `site.` prefix (`routes/web.php:80-81`, `ContactController.php:113`).
- ES apply pre-fill broken (`{job}` vs `Position $position`).
- `uix_con_todo` email obfuscator assigns undeclared globals under module strict mode.

## Doc drift to correct

- ADR-0023 names `routes/public.php` / `app.css`; code uses `routes/marketing.php` /
  `app.scss`.
- ADR-0024 describes three bundles; actual is two (see Summary).
- 08b-i says build dir `public/build/site` and images in `resources/images/site/`; actual
  `public/site-build`, images in `public/images/`.
- `identity-and-auth.md:75` says `POST /apply` (actual `POST /application`) and line 80 /
  `deployment-topology.md:37` say applications notify recruiters (not implemented).
- `domain-routing.md:105`, `identity-and-auth.md:38` say `/admin/login`; actual `/login`.

## Suggested order

1. Must-fix 2, 4 and the Nunito import (small, isolated).
2. reCAPTCHA v3 + throttle (D3) — needs Google Cloud key check first.
3. Contact delivery: config recipients, notification, back-office inbox (D6).
4. Testimonials model + admin + import (D2).
5. Spanish (D1) — largest; do after copy fixes so translations start from corrected EN.
6. SEO pass: per-page canonical, hreflang, sitemap, robots, twitter card, production-only GA.
7. Remove hero video + baseball/partner assets (D4, D5); doc drift.
