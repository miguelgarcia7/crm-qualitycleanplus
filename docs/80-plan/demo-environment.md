# Demo Environment

| Field | Value |
|---|---|
| Status | Built; running locally and on the Cloud `staging` environment (2026-10-03) |
| Last updated | 2026-10-03 (QCP Property, MAG Solutions, second recruiter, recruiting) |
| Owner | Engineering |

Self-running demo companies for testing every role and showing the client the system
working: a login for every role, three properties (**Acme Hotel**, **QCP Property**,
**MAG Solutions**), nine contractors with six weeks of history, contractors clocking
in and out live through the working day, and a few job postings and applicants.
For the day-to-day how-to (accounts, roles, commands), see [../demo-guide.md](../demo-guide.md);
this page is the engineering reference.

## What gets seeded

`php artisan demo:reset` = `migrate:fresh` → structural seeders (roles, departments,
positions, holidays, inventory categories, company settings) → `DemoSeeder`.
`SampleDataSeeder` is deliberately not run, so the demo companies are the only ones in
the system. Everything — logins, companies, rate history, contractors, raises — is
declared in `app/Domain/Demo/DemoRoster.php`.

- **Logins** (all share `DEMO_PASSWORD`, default `password`):
  - Back office (`DOMAIN_MAIN/admin`): `super-admin@`, `admin@`, `office-manager@`,
    `front-desk@`, `hr@`, `payroll@`, `recruiter@` (Acme), `recruiter2@` (QCP Property
    and MAG Solutions), `w2@` — all `@example.com`.
  - QC Minute (`DOMAIN_QCMINUTE`): a property manager per company (`pm@`, `pm.qcp@`,
    `pm.mag@`) and the nine contractors.
- **Acme Hotel** — Chicago, Mon–Sun weeks, 6.25% tax, QR clock-in on, the default
  holidays (Labor Day falls inside the history, so one week shows holiday pay), a
  lobby tablet with activation code `ACME01`.
- **Contractors** — five positions, two with negotiated rates above the Bible rate:

  | Contractor | Position | Shift | Punches by | Started |
  |---|---|---|---|---|
  | Maria Lopez | Housekeeper | ~8:00–4:00 | QR (phone GPS) | 22 weeks ago |
  | **James Carter** | Houseman | **~7:00–5:30 → ~50h/week, overtime** | QR | 17 weeks ago |
  | Aisha Brown | Laundry Attendant | ~8:00–4:00 | Lobby tablet | 13 weeks ago |
  | Tom Nguyen | Public Space Attendant | ~8:00–4:00 | Lobby tablet | 9 weeks ago |
  | Sofia Ramirez | Banquet Server | ~8:00–4:00 | QR | 3 weeks ago |

- **QCP Property** (Dallas) and **MAG Solutions** (Carrollton) — two positions each,
  with a Bible rate history (an old rate closed the day before a raise a few months
  ago). Two contractors each, all on the ~8–4 shift; each was hired on an earlier rate
  and got a **pay raise 2–5 weeks ago**: `SupersedeWorkOrder` with source
  `pay_increase`, effective on a Monday, so the weeks before it are punched and
  invoiced at the old rate and the weeks after at the new one. MAG has a tablet
  (`MAG001`); QCP is QR only.
- **Recruiting** — three job postings (two published, one draft) and three applicants
  through `SubmitApplication`: one new, one reviewing with a pending background check,
  one rejected.

  Every shift has a ~30-minute unpaid lunch (four punches a day). The ~8–4 shift nets
  ~7.5h/day and at most ~38.6h/week whatever the jitter, so **only James's work order
  ever crosses 40h**. Overtime is computed per work order per week
  (`RecomputeTimeSummary`), not per person.

## How it stays live

`php artisan demo:simulate` runs every 5 minutes, weekdays 6:00–18:55 Chicago, **only
when `DEMO_MODE=true`** (the schedule entry isn't even registered otherwise, and both
demo commands refuse in production). Each run:

1. **Punches** — `SimulateClock` replays each contractor's planned shift
   (`DemoRoster::shiftsFor`, deterministic per person + date, minutes of jitter)
   through the real `ClockInContractor` / `ClockOutContractor` actions, with a
   placeholder selfie and, for QR, a GPS fix inside the geofence. So entries, selfies,
   summaries, overtime, the live "On the clock" widget and broadcasts all behave as
   in production.
2. **Billing** — `AdvanceDemoBilling` moves finished weeks along a human cadence, so
   every stage is always visible:

   | Step | When (after the week closes Sunday) |
   |---|---|
   | Recruiter submits → PM approval queue | Monday 9:30 |
   | PM approves → invoice frozen | following Monday 11:00 |
   | Invoice emailed | that Tuesday 10:00 |
   | Marked paid (by payroll) | ~3 weeks after close |

Both steps run for each demo company. They stamp each action at its **planned** time,
not the cron tick that noticed it (`TravelsInTime`), and read progress back from the
data — so they're idempotent, a missed run or a sleeping environment catches up on the
next run, and the seeder builds the six-week history by running the very same code
from six weeks ago to now (pausing at each pay raise to supersede the work order). Anything
a viewer does by hand (declining a timesheet, approving early, clocking someone in on
the tablet) is picked up from where it stands rather than overwritten.

Gotcha found building it: while a Carbon test-now is set, Carbon reads Eloquent's
zone-less datetime strings **in the test-now's timezone**. `TravelsInTime` therefore
always sets the moment in the app timezone (UTC); a moment left in Chicago time shifts
every timestamp read back by 5 hours (it showed up as zero-minute durations).

## Running it locally

Set `DEMO_MODE=true` in `.env` first — both demo commands refuse without it. Your Herd
`.env` points at `qcpminute`. Two options:

- **Wipe and use Acme** — `php84 artisan demo:reset`, then `bin/legacy-sync` whenever the
  real data is wanted back (it rebuilds from the `minute` copy).
- **Keep both** — set `DB_DATABASE=qcpminute_demo` in `.env` and run
  `php84 artisan demo:reset` there; flip `DB_DATABASE` back for the legacy data.
  `bin/legacy-sync` rebuilds whichever database `.env` names — harmless for the demo
  (it's reproducible), but run it with `.env` on `qcpminute`.

For live punches keep `php84 artisan schedule:work` running (plus `queue:work` for queued
mail/broadcasts, and Reverb for live grid refresh). `php84 artisan demo:simulate` catches
up by hand at any time.

## Laravel Cloud — the `staging` environment

The Cloud app `crm-qualitycleanplus` has **one environment, `staging`**, which runs the
Acme demo until the real legacy data is loaded. (It was auto-created as "production" and
renamed: nothing is live yet. A real `production` environment, with
`qualitycleanplus.com` / `qcpstaffing.com`, comes at cutover.)

| | |
|---|---|
| Back office | `https://crm-qualitycleanplus-staging-bapqoc.laravel.cloud/admin` |
| QC Minute | `https://demo.qcpstaffing.com` (Cloudflare `qcpstaffing.com` zone, CNAME **DNS only**) |
| Database | `production` MySQL 8.4 cluster — the database name predates the rename |
| File storage | Laravel Object Storage bucket `crm_qualitycleanplus_staging`, private, attached as default disk **`s3`** |
| PHP | 8.4 (matches the project; the environment had defaulted to 8.5) |

### Variables

Cloud injects the database, app key and bucket credentials. The custom variables that
make it the demo:

```
APP_ENV=staging
APP_URL=https://crm-qualitycleanplus-staging-bapqoc.laravel.cloud
DOMAIN_MAIN=crm-qualitycleanplus-staging-bapqoc.laravel.cloud
DOMAIN_QCMINUTE=demo.qcpstaffing.com
DEMO_MODE=true
DEMO_PASSWORD=<shared with the client>
MAIL_MAILER=log
QCP_INVOICER_NAME / _ADDRESS / _CITY / _STATE / _ZIP / _PHONE / _EMAIL
```

- `APP_ENV` must not be `production` while it's the demo — both demo commands refuse it.
- `MAIL_MAILER=log` because the demo emails `@example.com` addresses, which would bounce
  through Postmark. The Postmark key stays in the variables for later.
- **Never** add custom `FILESYSTEM_DISK` or `AWS_*` variables: custom variables override
  what Cloud injects for the bucket. Blank ones copied from a local `.env` caused
  `A "region" configuration value is required for the "s3" service` on the first reset.
- The **Scheduler** toggle on the App cluster must be on for live punches.

### Reset or refresh the demo

Commands tab: `php artisan demo:reset --force`. It wipes the database, keeps the
`settings` table (company details), and rebuilds six weeks of history ending now. Deploy
commands stay `php artisan migrate --force` only — never `demo:reset`.

### Switching staging to the real data (when ready)

1. Remove `DEMO_MODE`; set `APP_ENV=production` — that blocks `migrate:fresh` /
   `db:wipe`, keeps the sample seeders out, and enforces strong passwords on real data.
2. Restore real mail (`MAIL_MAILER` back to Postmark) only once you want real emails sent.
3. Redeploy, then run `bin/legacy-sync --upload` from the Mac (needs the database's
   public endpoint temporarily enabled — see phase-final-cutover.md).

### If a separate demo is wanted later

Replicate `staging` into a `demo` environment, give it its **own** database (delete any
copied `DB_*` variables) and its own bucket, move `demo.qcpstaffing.com` to it, and set
the variables above there. Staging can then hold the real data.
