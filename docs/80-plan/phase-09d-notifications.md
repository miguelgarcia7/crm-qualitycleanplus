# Phase 09d — Notification Center + Preferences

| Field | Value |
|---|---|
| Status | ✅ Done (Pest green — 13 notification tests; Pint + Larastan clean, build + types clean, `migrate:fresh --seed` clean). |
| Last updated | 2026-06-12 |
| Owner | Engineering |

## Context

Since Phase 03 the app has **written** notifications (three classes, database channel
only) but nothing ever **read** them: the topbar bell was stock theme demo content with
a hardcoded badge, there were no routes or UI for the `notifications` table, and rows
piled up unseen. 09d builds the read side plus user-controlled muting so the single
in-app channel stays useful instead of fatiguing.

**Locked with owner:** in-app (database) is the **only** channel — deliberately no
mail and no SMS. Fatigue control = per-category mutes on My Profile.

> **Narrowed in 09g (2026-09-05).** The timesheet cycle now also emails: a PM when a
> week is submitted, the recruiter when it is approved or declined. An unseen in-app
> notice there blocks billing, and the domain spec had always called for mail on this
> cycle. Everything else — workflows, contracts, punch flags — remains in-app only,
> and SMS is still not planned. See `phase-09g-transactional-email.md`.

## As built

### Write side (hardened, not new)

- `app/Notifications/AppNotification.php` — abstract base: every notification declares a
  `category()` and `via()` returns `[]` when the recipient muted that category (the
  notification is simply never stored). *09g added a `channels()` hook so a notification
  can opt into mail; the default is still database-only and muting still silences every
  channel.*
- `app/Notifications/NotificationCategory.php` — enum, the single source of truth for
  the preference UI: `workflows` / `timesheets` / `contracts`, with `label()` +
  `description()`. Each stored payload now also carries `category` for the UI icon map.
- Senders unchanged: `WorkflowNotice` (workflow definitions/actions),
  `TimesheetStatusChanged` (submit/approve/decline), `ContractExpiringNotification`
  (scheduled 30/14/7-day check).

### Preferences

- `people.muted_notifications` — nullable JSON list of muted category values
  (null/empty = receive everything; sparse, no new table).
- `Person::hasMutedNotifications(NotificationCategory)` + `ProfileController::updateNotifications`
  (`PATCH /admin/settings/notifications`, enum-validated).
- My Profile gained a third **Notifications** tab: one `form-switch` per category with a
  plain-English description; copy notes that muting only silences the bell — actionable
  work still appears in My Tasks / dashboards.

### Read side (both surfaces)

- `HandleInertiaRequests` shares a lazy `notifications` prop: `{unread, items[8], base}`.
  `base` is surface-relative (`/admin/notifications` on the back office, `/notifications`
  on QC Minute) so the shared TopBar posts to the right domain.
- `NotificationController` (`index` / `open` / `read` / `readAll`) mounted on **both**
  route files; queries hang off `$request->user()->notifications()` so they're inherently
  scoped — no cross-user reads.
- `NotificationLink::resolve(data, onMinute)` maps a stored payload to a deep link **at
  render time** (the same notification opens `/timesheets/{id}` for a PM on QC Minute but
  `/admin/timesheets` for a recruiter). Unknown types → null → opening just marks read.
- `TopBar/components/NotificationDropdown.tsx` replaces the stock demo dropdown (deleted):
  live unread badge (9+ cap), category icon map, mark-all-read, per-item open links,
  empty state, "View all" → `views/admin/notifications/index.tsx` (full history page,
  latest 100, unread highlighting).

## Tests / seed

`tests/Feature/NotificationTest.php` (13): bell payload + count, history page, open
marks-read + deep-links (and falls back without one), read-all, cross-user 404,
default delivery, muted category skipped, other categories unaffected, profile settings
payload, prefs save + enum validation, QC Minute bell/history/deep-link with
surface-relative base. No seeder changes — SampleDataSeeder's timesheet/workflow flows
already produce real notifications (8 rows after `migrate:fresh --seed`).

## Out of scope (deferred)

Mail/SMS channels (deliberately none); real-time bell updates over Reverb (refreshes
with normal Inertia navigation); per-notification granularity beyond the three
categories; KB publish notifications (would join as a fourth category when built).

## Related

`phase-06-dashboards.md` (dashboards remain the "needs my attention" surface);
`phase-03-timesheets.md`; ADR-0024 (two domains, shared TopBar).
