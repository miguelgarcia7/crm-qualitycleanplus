# Demo Environment — Acme Hotel

| Field | Value |
|---|---|
| Status | Built; verified locally on MySQL (scratch DB `qcpminute_demo`). Cloud `demo` environment not yet created |
| Last updated | 2026-10-03 |
| Owner | Engineering |

A self-running demo company for testing every role and showing the client the system
working: one login per role, one property (**Acme Hotel**), five contractors with six
weeks of history, and contractors clocking in and out live through the working day.
For the day-to-day how-to (accounts, roles, commands), see [../demo-guide.md](../demo-guide.md);
this page is the engineering reference.

## What gets seeded

`php artisan demo:reset` = `migrate:fresh` → structural seeders (roles, departments,
positions, holidays, inventory categories, company settings) → `AcmeHotelSeeder`.
`SampleDataSeeder` is deliberately not run, so Acme is the only company in the system.

- **Logins** (all share `DEMO_PASSWORD`, default `password`), defined in
  `app/Domain/Demo/DemoRoster.php`:
  - Back office (`DOMAIN_MAIN/admin`): `super-admin@`, `admin@`, `office-manager@`,
    `front-desk@`, `hr@`, `payroll@`, `recruiter@`, `w2@` — all `@example.com`.
  - QC Minute (`DOMAIN_QCMINUTE`): `pm@example.com` and the five contractors.
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

Both steps stamp each action at its **planned** time, not the cron tick that noticed it
(`TravelsInTime`), and read progress back from the data — so they're idempotent, a
missed run or a sleeping environment catches up on the next run, and the seeder builds
the six-week history by running the very same code from six weeks ago to now. Anything
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

## Laravel Cloud environments

The app (`crm-qualitycleanplus`) runs one Cloud environment per purpose. Each has its
own database, so the legacy data and the demo can never overwrite each other.

| Environment | Holds | `APP_ENV` | `DEMO_MODE` | Domains |
|---|---|---|---|---|
| **staging** | The legacy data, loaded by `bin/legacy-sync --upload` — real people and invoices | `production` | not set | its `*.laravel.cloud` address |
| **demo** | Acme Hotel, reset with `demo:reset` | `demo` | `true` | its `*.laravel.cloud` address (back office) + `demo.qcpstaffing.com` (QC Minute) |
| **production** | Not created yet — made at cutover with the real domains | `production` | not set | `qualitycleanplus.com`, `qcpstaffing.com` |

Staging keeps `APP_ENV=production` on purpose: the Cloud *name* is just a label, while
`APP_ENV=production` is what blocks `migrate:fresh` / `db:wipe`, stops `db:seed` adding the
sample companies, and enforces strong passwords — all wanted on real data. The demo
commands also require `DEMO_MODE=true`, so they can't run on staging whatever `APP_ENV` says.

### 1. Rename the existing environment to `staging`

The environment Cloud created first is named `production`, but it holds the legacy
rehearsal data, not a live system.

1. Environment → **Settings → General → Name**: `staging`. Save, then **redeploy**.
2. Its free address changes (from `crm-qualitycleanplus-production-2vodwt.laravel.cloud`
   to a `…-staging-….laravel.cloud` one). Update `APP_URL` and `DOMAIN_MAIN` to it in the
   environment's variables, and redeploy again.
3. Locally, update `APP_URL` and `DOMAIN_MAIN` in `.env.rehearsal` to match.
   `bin/legacy-sync` only uses that file's database credentials, and the database
   doesn't change with the rename, so syncs keep working.
4. Keep `APP_ENV=production` (see above) and leave `DEMO_MODE` unset.
5. Network → Domains: **detach `demo.qcpstaffing.com`** — it moves to the demo
   environment in step 2.

### 2. Create the `demo` environment

1. **Replicate** `staging` → name `demo`, branch `main`. Before the first deploy, confirm
   it created a **new** database (and bucket / WebSocket server) — never staging's,
   which holds the real data. Scale-to-zero Flex compute is fine.
2. **Domains** — the environment's free `*.laravel.cloud` address serves the back office;
   QC Minute needs its own host. In the **demo** environment's Network settings add
   `demo.qcpstaffing.com` (wildcard: no; Cloudflare DNS: yes; proxied: no). In
   Cloudflare (`qcpstaffing.com` zone) make sure the records match what Cloud shows,
   **DNS only (grey cloud)**. Refresh until Connected. Don't make it the primary domain.
3. **Environment variables** (Replicate copied staging's). First, delete any custom
   `DB_*` variables that came across — custom variables override the credentials Cloud
   injects for the demo's own database, and would point the demo at staging's real data.
   Then set:
   ```
   APP_ENV=demo
   APP_URL=https://<demo-env>.laravel.cloud
   DOMAIN_MAIN=<demo-env>.laravel.cloud
   DOMAIN_QCMINUTE=demo.qcpstaffing.com
   DEMO_MODE=true
   DEMO_PASSWORD=<shared with the client>
   MAIL_MAILER=log
   ```
   `APP_ENV` must not be `production` (the demo commands refuse it). `MAIL_MAILER=log`
   keeps approval/invoice mail from going anywhere.
4. **Scheduler** — enable the Scheduler toggle on the App cluster. Deploy commands stay
   `php artisan migrate --force` only — never `demo:reset`, which would wipe on each deploy.
5. **Company details** — add `QCP_INVOICER_NAME`, `_ADDRESS`, `_CITY`, `_STATE`, `_ZIP`,
   `_PHONE` and `_EMAIL` to the variables (and redeploy) before seeding: the seed copies
   them into Settings → Company, and the demo's invoices print them. (Or seed first, fill
   in Settings → Company as Admin, then run `demo:reset` again — it keeps them.)
6. **Seed** — Commands tab: `php artisan demo:reset --force`. Re-run it any time for a
   clean slate (the history is always "the last six weeks" from that moment; company
   details are kept).
7. **Share** the two URLs, the account list `demo:reset` prints, and `DEMO_PASSWORD`.
   `NoIndexInDemoMode` adds `X-Robots-Tag: noindex` on both hosts (Cloud only does that
   for `*.laravel.cloud`).
