# Outbound Email

Every path that sends mail, who triggers it, and what guarantees it carries.

There are **six**. There are no Mailable classes and no direct `Mail::` calls —
everything goes through Laravel notifications, so this list is complete as long
as that stays true. Grep check: a notification sends mail only if its `via()` (or
`channels()`) returns `mail`.

## The six

| Email | Trigger | Recipient | Surface it links to | Queued |
|---|---|---|---|---|
| **Invoice issued** (`InvoiceIssued`) | Recruiter clicks *Send Invoice to Property* — manual, never automatic | A confirmed address, defaulted from the property's billing email | QC Minute | **No** — see below |
| **Timesheet awaiting approval** (`TimesheetAwaitingApproval`) | Recruiter submits a week | Every PM assigned to that property | QC Minute | Yes |
| **Timesheet decided** (`TimesheetDecided`) | PM approves or declines | The recruiter who submitted | Back office | Yes |
| **User invitation** (`UserInvitation`) | Admin invites a user (`admin.users.create`) | The new person | Role-dependent | Yes |
| **Password reset** (`PasswordResetLink`) | Anyone posts `/forgot-password` | Whoever owns the address | Role-dependent | Yes |
| **Contact lead** (`ContactInquiryReceived`) | A visitor submits a marketing contact form (job seeker or business) | The addresses in `MARKETING_JOB_SEEKERS_TO` / `MARKETING_BUSINESS_TO` (on demand, not a `Person`; blank = not sent). Reply-to is the visitor | None — the lead is in the body | Yes |

**Nothing else emails.** Workflow notices (pay increase, transfer, temporary
assignment, more staff, personal-info change), contract expiry, punch flags,
direct-hire eligibility and the timesheet bell notices are all in-app only —
they inherit `AppNotification`'s `database` default. **No scheduled job sends
mail**; the six daily tasks write in-app notifications at most.

## Two rules that are easy to get wrong

### 1. Audience decides the domain

A recipient must land on the surface they can actually sign in to. Property
managers and contractors use QC Minute; everyone else uses the back office.

This is not automatic. Laravel builds URLs from `APP_URL`, which is always the
back office, and Fortify's auth routes carry no domain constraint — so a wrong
link *loads* rather than 404s, and the mistake is invisible until someone cannot
sign in. Both the mail body **and** the header logo need the right host; the
header rides on `MailMessage::$viewData` (see `phase-09g`).

### 2. Queue when the state is already committed; send in-line when the state depends on delivery

| | Queued | Synchronous |
|---|---|---|
| When | The work is already durable before mail is attempted | A status only becomes true if delivery succeeds |
| Failure mode | Retries silently; nothing is lost | Throws in-line, and the caller reports it |
| Here | timesheet ×2, invitation, password reset, contact lead | invoice |

`SendInvoice` marks an invoice `invoice_sent` **only after Postmark accepts it**,
and `InvoiceController::send()` catches the transport exception and tells the
recruiter the invoice was not delivered. That promise depends on the send being
in-line, so `InvoiceIssued` must never become `ShouldQueue` — there is a test
asserting it is not.

The others are the opposite case: by the time mail is attempted the timesheet is
already locked, or the `Person` row already committed. A synchronous failure
there would report failure for work that succeeded, and invite a retry.

## Transactional notifications ignore the category mute

`AppNotification::mutable()` defaults to true. A notification that returns
**false** is transactional: the recipient's category mute does not reach it.

| Notification | Mutable |
|---|---|
| `TimesheetAwaitingApproval` (email to the PM) | **No** |
| `TimesheetStatusChanged`, event `submitted` | **No** — the same request, other channel |
| `TimesheetStatusChanged`, approved / declined | Yes |
| `TimesheetDecided` (email to the recruiter) | Yes |
| Everything else | Yes |

The test is **whether silence breaks a process someone else depends on**. A
request for action addressed to a named person is transactional; an outcome
notice is informational. Before this, a property manager who muted Timesheets
received nothing at all for the notification asking them to approve a week —
one switch could silently stall billing.

This is the split every mature approval system draws (Concur, Coupa, Bill.com):
the approver cannot switch off the request, and the pressure valve is *frequency*
— digests and reminders — rather than silence. That is why
`90-open/parking-lot.md` ranks a reminder job above per-channel preferences.

The invoice, invitation and reset emails are transactional in the same sense;
they simply never extended `AppNotification`, so they were never mutable.

**Both halves of a request must agree.** Muting only the email would have left a
muted PM getting mail but no bell for the same event, which is why the
`submitted` event of the in-app notice is transactional too.

## Where a person changes their preferences

`/admin/settings/profile#notifications` on the back office, and
`/settings/profile#notifications` on QC Minute. Both surfaces run the **same**
`settings/profile` component and the same controllers; routes exist on each, and
the page posts relative to whichever surface rendered it (`useSettingsBase()` off
the shared `surface` prop).

QC Minute had no settings area at all until 2026-09-06 — so property managers and
contractors, the audience the timesheet emails are addressed to, could not reach
the switches governing them.

The screen shows an **"Email too"** badge on any category that also sends mail,
driven by `NotificationCategory::sendsEmail()` rather than hardcoded in the view.
Muting remains **per category, not per channel**: there is no "in-app but not
email" setting, and adding one would be a schema change to `muted_notifications`.
## What this depends on

**A queue worker.** Four of the five sit in the `jobs` table until one picks them
up. This was already true for `RecomputeTimeSummary` — see
`10-architecture/deployment-topology.md` — but it now covers billing-adjacent
mail, so a dead worker is more expensive than it was. Queue depth and
`failed_jobs` are the signal; there is no user-visible error.

**An address on the person.** `AppNotification::via()` drops the `mail` channel
when the recipient has none. Most legacy contractors identify by phone, and
mailing them throws rather than quietly not arriving.

**Category mutes apply to mail too.** Muting is per *category*, not per channel,
so a PM who mutes Timesheets stops receiving approval requests entirely. That is
deliberate, and worth knowing because it can stall billing.

## Testing locally

`MAIL_MAILER=smtp` with `MAIL_HOST=127.0.0.1` / `MAIL_PORT=2525` routes into
Herd's mail catcher; nothing leaves the machine. Run `php84 artisan queue:listen`
alongside it or the queued four never arrive.

**Do not point a local environment at Postmark.** The dev database is imported
legacy data containing real client addresses.

## Related

- `80-plan/phase-09g-transactional-email.md` — how the timesheet emails were built
- `20-domain/invoicing.md` — the invoice send flow
- `10-architecture/identity-and-auth.md` — invitation + reset
- `80-plan/phase-09d-notifications.md` — the in-app channel and category mutes
