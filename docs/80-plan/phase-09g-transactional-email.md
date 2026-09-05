# Phase 09g — Transactional email for the timesheet cycle

| Field | Value |
|---|---|
| Status | ✅ Done (Pest green — 24 notification tests; Pint + Larastan clean, build + types clean). |
| Last updated | 2026-09-05 |
| Owner | Engineering |

## Context

09d shipped the notification centre with in-app (database) as the **only**
channel, locked with the owner at the time. The domain spec had always wanted
mail on the timesheet cycle — `20-domain/timesheets.md` and
`40-flows/timesheet-approval.md` both say "notification → PM (mail + in-app)" —
and in practice the in-app-only channel meant a property manager learned a week
was waiting for them whenever they next happened to sign in, and a recruiter
learned their week had been declined the same way, with the reason on a screen
they had to go find.

This phase adds mail to that cycle and nothing else. Workflow and contract
notifications remain in-app only.

## As built

### Opt-in mail on the notification base

`AppNotification` gains a `channels()` hook, still `['database']` by default, so
existing notifications are unchanged. `via()` applies two rules on top:

- **Muting still wins**, and it silences every channel. The preference is per
  *category*, not per channel — someone turning Timesheets off expects silence,
  not a quieter version of it.
- **A `mail` channel is dropped when the recipient has no email address.** Most
  legacy people identify by phone; mailing them throws rather than simply not
  arriving.

### Two mail notifications

| Class | To | Surface it links to | Sent from |
|---|---|---|---|
| `TimesheetAwaitingApproval` | property managers assigned to the property | QC Minute — `/timesheets/{id}` | `SubmitTimesheetForApproval` |
| `TimesheetDecided` (approved \| declined) | the recruiter who submitted | back office — the invoice, or the weekly grid | `ApproveTimesheet`, `DeclineTimesheet` |

Each sends **alongside** the existing `TimesheetStatusChanged` in-app notice,
which is unchanged.

### Decisions worth remembering

- **Separate classes, not a `mail` channel on `TimesheetStatusChanged`.**
  Laravel's `ShouldQueue` is all-or-nothing across a notification's channels, so
  adding mail to the existing class would have pushed the in-app notice onto the
  queue too — nothing would reach the bell without a worker running. Splitting
  keeps the notice instant and the mail queued.
- **The mail is queued, and that is the point.** By the time mail is attempted
  the timesheet is already pending and the period already locked (and on
  approval, the invoice already generated), with no transaction around it. A
  synchronous send during a Postmark outage would throw *after* that work
  committed — reporting failure for something that succeeded, and inviting a
  retry. Queued, it retries on its own and never touches the decision.
  *(`InvoiceIssued` is the deliberate opposite: `SendInvoice` marks an invoice
  sent only once delivery succeeds, so that one has to be synchronous.)*
- **Each email links where the recipient has to go next** — the PM to the
  timesheet, the recruiter to the invoice they now have to send or the grid they
  have to correct — rather than to a list they would have to search.
- **The decline reason travels in the body**, and is omitted rather than printed
  empty when none was recorded.
- **Audience decides the domain.** PM mail points at QC Minute; recruiter mail at
  `/admin`. The stock Laravel header links to `APP_URL`, which is the back office
  — so PM-facing mail overrides it via `MailMessage::$viewData`.

### Mail theme

`vendor:publish --tag=laravel-mail` then recoloured to the **Sage** palette from
`resources/css/admin/config/_theme-sage.css`, which `app.blade.php` pins as the
shipped skin: sage action button, warm greige canvas, warm-white card,
slate-green headings, warm hairlines.

Publishing drops 16 files, 14 of them byte-identical to the framework's. Those
were diffed and deleted — left in place they shadow upstream silently, so a
Laravel fix to `layout.blade.php` would never arrive. What is tracked:

```
resources/views/vendor/mail/
├── html/message.blade.php      ← forwards headerUrl / headerName
├── html/themes/default.css     ← the Sage palette
└── text/message.blade.php
```

## Gotchas this phase re-confirmed

- **Blade anonymous components have isolated scope.** `MailMessage::$viewData`
  reaches `notifications/email.blade.php` but not `x-mail::message`; the wrapper
  exists solely to forward those props explicitly.
- **`config/mail.php` must keep its `markdown.paths` block**, or everything in
  `resources/views/vendor/mail` is ignored with no error.
- **A queue worker is required** for these to send at all. Already true for
  `RecomputeTimeSummary` — see `10-architecture/deployment-topology.md`.
- **Asserting on a rendered mail body:** use `getHtmlBody()`, not `toString()`.
  The raw message is quoted-printable, which turns the `=` in `?week=` into
  `=3D` and fails a URL assertion for the wrong reason.

## Tests

`NotificationTest` grew to 24. Beyond "was it sent": both bodies are **rendered
through the mailer's array transport**, not merely inspected as a `MailMessage`
— that is the check that catches Blade-layer breakage, which is how the invoice
header bug hid. Also covered: the classes are genuinely `ShouldQueue`, a
recipient with no address gets nothing, muting silences mail as well as the
notice, and the PM mail contains QC Minute but not the back-office domain.

## Out of scope / deferred

- **Workflow, contract and punch-flag notifications stay in-app only.** 09d's
  reasoning holds for them; this exception is the timesheet cycle, where an
  unseen notice blocks billing.
- **Per-channel mute preferences.** Muting a category silences both channels; a
  "in-app but not email" preference would be a schema change to
  `muted_notifications`.
- **SMS** — still not planned.
- Nothing here was viewed in a real mail client; rendering was verified from the
  array transport and by opening the HTML directly.

## Related

- `80-plan/phase-09d-notifications.md` — the notification centre and the
  in-app-only decision this narrows
- `20-domain/timesheets.md`, `40-flows/timesheet-approval.md` — the cycle
- `20-domain/invoicing.md` — `InvoiceIssued`, the synchronous counterexample
- `10-architecture/deployment-topology.md` — Postmark + the queue worker
