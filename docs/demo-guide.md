# Demo Guide — Acme Hotel

The demo is a fake company, **Acme Hotel**, with one login for every role and five
contractors who clock in and out by themselves through the work week. Use it to test
each role, or to show the system to the client.

How it works under the hood, and how to set up the Laravel Cloud demo environment:
[80-plan/demo-environment.md](80-plan/demo-environment.md).

---

## Where to sign in

The system has two websites. Each account signs in to one of them.

| Website | Local (Herd) | Cloud demo | Who signs in here |
|---|---|---|---|
| **Back office** | `qcpminute.test/admin` | `<demo-env>.laravel.cloud/admin` | QCP office staff |
| **QC Minute** | `qcminute.test` | `demo.qcpstaffing.com` | The hotel's manager and contractors |

**Password for every account:** `password` locally. On Cloud it's whatever
`DEMO_PASSWORD` is set to.

Signed in on the wrong website? You'll see "This account cannot access…". Click
**Sign out and use a different account** on that page.

---

## Accounts

### Back office — QCP staff

| Role | Email | What they do |
|---|---|---|
| **Super Admin** | `super-admin@example.com` | Can do everything, including the super-admin-only tools: tablet kiosks, editing recruiter visits, and cancelling workflows. Also signs in to QC Minute. |
| **Admin** | `admin@example.com` | The business owner. Sees and runs everything: rates, contracts, invoices (paid/void), PTO approvals, company settings, roles. Also signs in to QC Minute. |
| **Office Manager** | `office-manager@example.com` | Runs the office: properties and rates, work orders, time entries, sending timesheets to the hotel, sending invoices, inventory, the knowledge base, the audit log and reports. |
| **Front Desk** | `front-desk@example.com` | Handles supplies and walk-ins: inventory, purchase orders, filling supply requests, the new-hire onboarding checklist, returning equipment. |
| **HR** | `hr@example.com` | Handles people: applicants, contractors and staff, terminations and transfers, PTO approvals, knowledge-base articles. |
| **Payroll** | `payroll@example.com` | Handles money: rates, contracts, hour imports, marking invoices paid or void, payroll and financial reports. |
| **Recruiter** | `recruiter@example.com` | Owns Acme Hotel day to day: its contractors and work orders, fixing punches, submitting each week's timesheet to the hotel, sending invoices, property visits. |
| **W-2 Employee** | `w2@example.com` | A regular office employee: their own profile, PTO requests, supply requests, the knowledge base. |

### QC Minute — the hotel side

| Role | Email | What they do |
|---|---|---|
| **Property Manager** | `pm@example.com` | Acme Hotel's manager. Sees who's working and their hours, **approves or declines weekly timesheets**, sees invoices, and can ask for more staff or a pay increase. |
| **Contractor** | see below | A worker placed at the hotel. Sees their own profile, work orders and hours, plus knowledge-base articles. |

### The five contractors

| Name | Email | Job | Usual hours | Clocks in with | Phone (for clock-in) |
|---|---|---|---|---|---|
| Maria Lopez | `maria.lopez@example.com` | Housekeeper | 8:00–4:00 | Phone (QR) | (312) 555-0141 |
| **James Carter** | `james.carter@example.com` | Houseman | **7:00–5:30, ~50h/week (overtime)** | Phone (QR) | (312) 555-0142 |
| Aisha Brown | `aisha.brown@example.com` | Laundry Attendant | 8:00–4:00 | Lobby tablet | (312) 555-0143 |
| Tom Nguyen | `tom.nguyen@example.com` | Public Space Attendant | 8:00–4:00 | Lobby tablet | (312) 555-0144 |
| Sofia Ramirez | `sofia.ramirez@example.com` | Banquet Server | 8:00–4:00 | Phone (QR) | (312) 555-0145 |

Everyone takes a ~30-minute lunch around noon, so there are four punches a day.
**James is the only one who goes over 40 hours a week**, which is what you need for
testing overtime on timesheets and invoices. Times are Chicago time, Monday–Friday.

---

## What you'll see

| Where | What to look for |
|---|---|
| Dashboard → **On the clock** | During work hours, everyone currently clocked in. Before lunch, after lunch, and after 4:00 (only James) all look different. |
| Timesheets | **This week** is still filling up. **Last week** is waiting for the property manager to approve it. |
| Invoices | Older weeks are invoiced and emailed; the oldest are marked **paid**. |
| James's hours | Overtime every week. |
| The Labor Day week | Holiday pay (1.5×) on Monday, September 7. |

**What happens on its own each week:**

| When (Chicago time) | What happens |
|---|---|
| Weekdays ~7:00–5:30 | Contractors clock in, take lunch, and clock out |
| Monday 9:30 | The recruiter sends last week's timesheet to the property manager |
| The following Monday 11:00 | The property manager approves it, which creates the invoice |
| That Tuesday 10:00 | The invoice is emailed to the hotel |
| About 3 weeks after the week ends | Payroll marks the invoice paid |

If you do one of these steps yourself first (approve, decline, clock someone out),
the demo carries on from there.

---

## Commands

Run these in the project folder. Locally use `php84 artisan …`. On Cloud, use the
demo environment's **Commands** tab and type `php artisan …`.

### Start fresh

```bash
php84 artisan demo:reset
```

**Deletes everything in the database** and creates the Acme Hotel demo: every
account, the hotel, the contractors, and the last six weeks of hours, timesheets and
invoices. It prints the account list when it's done, and won't run on production. Use
it to start over at any time. On Cloud, add `--force` to skip the "are you sure?"
question.

### Catch up to right now

```bash
php84 artisan demo:simulate
```

Adds any clock-ins, clock-outs and weekly billing steps that are due, up to the
current time. The scheduler runs this every 5 minutes on weekdays, so you'll only
need it by hand to catch up without waiting. Running it twice does nothing extra.
Only works when `DEMO_MODE=true`.

### Keep it running live (local only)

```bash
php84 artisan schedule:work
```

Runs the schedule on your Mac, including `demo:simulate` every 5 minutes. Leave it
open in a terminal tab. It stops when you close the tab or the Mac sleeps; anything
missed is filled in the next time it runs. On Cloud, the **Scheduler** toggle does
this instead.

```bash
php84 artisan queue:work
```

Optional. Processes background jobs: the notification emails (for example
"timesheet waiting for approval") and the live-update events for the timesheet
grid. In-app bell notifications appear without it. Leave it open in its own tab.

```bash
php84 artisan reverb:start
```

Optional, and only useful together with `queue:work`. Makes the timesheet grid
update live as people clock in. Without them, refresh the page to see new punches.

### Check that the demo is switched on

```bash
php84 artisan schedule:list
```

Lists the scheduled jobs. If you see a `demo:simulate` line, demo mode is on. If you
don't, set `DEMO_MODE=true` (see below).

### Go back to the real (legacy) data — local only

```bash
bin/legacy-sync
```

Rebuilds the local database from the legacy `minute` copy, replacing the demo. Run
`demo:reset` again whenever you want the demo back.

---

## Try it yourself

- **Clock in on the lobby tablet.** Open `qcminute.test/device` (or
  `demo.qcpstaffing.com/device` on Cloud), enter activation code **`ACME01`**, then
  type a contractor's phone number. The tablet doesn't check location, so it works
  from anywhere. The code changes once it's used. To pair another tablet, sign in as
  Super Admin and open **Devices** (`/admin/devices`) to get a new code.
- **Clock in by phone (QR).** As Admin, open Acme Hotel's property page, then
  **Clock-in QR**. The QR checks the phone's location against the hotel in Chicago, so
  it will turn you away if your phone says you're somewhere else.
- **Approve a timesheet.** Sign in as `pm@example.com` in QC Minute and approve last
  week. An invoice appears right away.

A contractor who is already clocked in can't clock in again. Clock them out first,
or try it outside work hours.

---

## Settings

Set these in `.env` locally, or in the Cloud demo environment's variables (then
redeploy).

| Setting | What it does |
|---|---|
| `DEMO_MODE=true` | Switches the demo on: contractors clock in and out by themselves, and search engines are told to ignore the site. Never set this on production. |
| `DEMO_PASSWORD=…` | The password for every demo account (default `password`). Set your own on Cloud before sharing the link, then run `demo:reset` so the accounts pick it up. |
| `DB_DATABASE=…` | Locally: which database to use. `qcpminute` holds the real legacy data; `qcpminute_demo` is a separate copy you can keep for the demo. |

---

## Something's not right?

| Problem | Fix |
|---|---|
| "This account cannot access…" | You're on the other website. Click **Sign out and use a different account**, then use the right site (see the account tables above). |
| Nobody is on the clock | Check it's a weekday between about 7:00 and 5:30 Chicago time. Locally, make sure `schedule:work` is running, or run `demo:simulate` to catch up. |
| The data looks old | Run `demo:simulate`. If things look broken, `demo:reset` starts over. |
| No `demo:simulate` in `schedule:list` | Set `DEMO_MODE=true`. On Cloud, redeploy after changing it. |
