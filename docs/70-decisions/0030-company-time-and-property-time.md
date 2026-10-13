# ADR-0030: Company Time and Property Time

| Field | Value |
|---|---|
| Status | Accepted |
| Decided | 2026-10-12 |
| Owner | Engineering |
| Refines | ADR-0009 (payroll periods), ADR-0019 (temporary assignment auto-close) |
| Supersedes | — |
| Superseded by | — |

## Context

Every timestamp is stored in UTC (`config/app.php` timezone, MySQL sessions
pinned to `+00:00`). Where the server runs doesn't matter.

Displaying and dating those timestamps had no single rule:

- Punches already used the property's timezone (`time_entries.timezone`), and
  so did overdue invoices (by the property's own date).
- "Today" on the dashboards used a hardcoded `America/Chicago` constant.
- About 75 office screens printed `created_at`, `approved_at`, `paid_at` and
  similar fields straight from UTC. An approval at 3 PM Central showed as
  8 PM, and in the evening a date showed as tomorrow.
- Several actions and the nightly jobs used the UTC date. The nightly jobs were
  scheduled in UTC, so they ran at about 7–8:30 PM Central the evening before.
  `ProcessTemporaryAssignmentEnds` closed assignments that evening, which
  stopped a contractor clocking in on the last day.
- `docs/30-schema/conventions.md` said "display in the request user's timezone",
  but people have no timezone column, and the office all works in Central time.

## Decision

Two clocks, and each piece of data belongs to one of them.

**Property time** (the property's `timezone`) for work at a property:
- punches and the day they count toward;
- payroll weeks (ADR-0009);
- holidays;
- overdue invoices, judged by the property's own date;
- work order start and end dates.

**Company time** (Central, `config('app.company_timezone')`, default
`America/Chicago`, `COMPANY_TIMEZONE` in `.env`) for everything else, including
what the office does:
- requests, approvals, decisions;
- the audit log and notifications;
- recruiter activity;
- "today", "this week" and "this month" on the dashboards;
- the dates the app stamps on records (invoice issue date, application date,
  PTO adjustment date);
- the nightly schedule (`config('app.schedule_timezone')`).

`App\Support\CompanyTime` is the one way in:
- `zone()`, `now()` and `today()` (Central midnight);
- `date()`: the company's date as a plain date, for comparing with DATE columns;
- `of($timestamp)`: a stored UTC timestamp converted for display.

`Property::localDate()` gives a property's date.

Storage doesn't change: everything stays UTC.

Rules for code:
- Never format a stored timestamp without converting it first: use
  `CompanyTime::of(...)`, or `setTimezone($property->timezone)` for punches.
- When a company-time boundary goes into a query against a UTC column, bind
  `->utc()`. The database compares the date's text, not the instant.
- Calendar values (`toDateString()`, start of week or month, year) come from
  `CompanyTime::now()` or the property's date, never from bare `now()`.
- Pure durations (`subDays` for an SLA, `diffInMinutes`) don't depend on the
  zone. `now()` is fine for those.

**Temporary assignment end date (refines ADR-0019).** The end date is the last
day worked. The assignment closes once that day is over at the property. It
used to close on `end_date <= today`; it now closes on `end_date < today`, in
property time.

## Consequences

- Office screens show the time people actually saw on their clocks.
- The nightly jobs run after midnight Central, and the System Reference →
  Automations page no longer warns about them. It still flags any future task
  written in UTC.
- A person in another timezone still sees Central times. That's intended: one
  company clock. A timezone per user can be added later if the office spreads out.
- `tests/Feature/CompanyTimeTest.php` guards the rule. It fails if a controller
  or dashboard service formats a stored timestamp without converting it.
