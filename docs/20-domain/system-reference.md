# System Reference and My access

| Field | Value |
|---|---|
| Status | Accepted — built and on staging |
| Last updated | 2026-10-10 |
| Owner | Engineering |

Admin → **System Reference** (`/admin/system`) explains how QCP is set up: who can do what,
who gets notified, and what runs on its own. **My Profile → My access** shows each person
the same information about themselves. Both are read from the running app, not written
down separately, so they can't drift from what is deployed.

## Pages

| Tab | Route | Shows |
|---|---|---|
| Overview | `/admin/system` | A lookup across permissions, notifications and scheduled tasks; cards linking to each topic with live counts |
| Roles & permissions | `/admin/system/roles` | Every permission against the nine roles, grouped by area, with per-role counts; click a role to highlight its column; search; "Super Admin only" and "Own records only" flags |
| Notifications | `/admin/system/notifications` | All 21 notices against the roles and outside inboxes, in-app vs email; click a role to highlight it; click a row for what it says, when it's sent, who gets it, whether it can be muted and the class that sends it |
| Automations | `/admin/system/automations` | Every scheduled task on a 24-hour timeline and in a table, in Chicago time or UTC, with the command or queued job that runs it and its next run |

Links can open a page on an answer: `/admin/system/roles?role=recruiter&q=invoices.send`,
`/admin/system/notifications?n=contract-expiring`. The overview's lookup results use these.

**My access** (`/admin/settings/profile#access` on the back office, `/settings/profile#access`
on QC Minute) is a tab every user has. It shows their roles, the properties they look after
("All properties" for the roles in `PropertyPolicy::GLOBAL_ROLES`), what they can do by area,
and the notices they get, with channel and mute state.

## Access

The reference pages need `system.reference.view` (Admin; Super Admin holds every
permission). It was added to `RolePermissionSeeder` on 2026-10-09. A database seeded before
then needs `php artisan db:seed --class=RolePermissionSeeder --force`; staging ran it on
2026-10-10. My access needs no permission.

## Where each fact comes from

Everything lives in `app/Domain/SystemReference/Support/`.

| Class | Reads | Written by hand |
|---|---|---|
| `PermissionCatalog` | Permissions and the roles holding them, from the database | Readable labels: a verb/noun map plus `LABELS` overrides for names that read badly; areas by name prefix |
| `NotificationCatalog` | Each notification class's channels, whether it can be muted and its mute category (asked of the class via reflection); recipients that follow a permission or a role list (`NotifyStaffOfApplication::ROLES`, `UserInviteController::INVITABLE_ROLES`) | Each notice's wording, trigger and assignment-based recipients |
| `AutomationCatalog` | The live schedule (it bootstraps the console kernel, because a web request never loads `routes/console.php`): each task's `->description()`, timing in both zones, and the command or queued job | Nothing |
| `AccessSummary` | One person's roles, permissions, property assignments, active work orders and muted categories, through the catalogs above | Nothing |

## Tests that keep it honest

In `tests/Feature/SystemReferenceTest.php`. They fail when:

- a scheduled task in `routes/console.php` has no `->description()`;
- a permission's label still contains `_` or `.`, or falls in no known area;
- a class in `app/Notifications` isn't listed in `NotificationCatalog`;
- a reference page overrides the shared `notifications` prop (see the gotcha below).

## Adding something new

- **A permission:** add it to `RolePermissionSeeder` and `permissions-matrix.md`. If its
  generated label reads badly, add an entry to `PermissionCatalog::LABELS`; if its first
  segment is new, add it to `AREAS`.
- **A notification:** add an entry to `NotificationCatalog::entries()` with its wording,
  trigger and recipients. Channels and muting come from the class.
- **A scheduled task:** give it a `->description()` in `routes/console.php`. It appears on
  the Automations page by itself.

## Gotcha: the shared `notifications` prop

`HandleInertiaRequests` shares `notifications` with every page (the top bar's bell). A page
prop with that name replaces it and the layout crashes, leaving a blank page. The reference
pages pass their data as `notices`. The shared prop names to avoid are: `name`, `auth`,
`surface`, `notifications`.

## Known issue it surfaces

The Automations page warns that five nightly tasks are written in UTC, so in Chicago they run
between 7:15 and 8:30 PM the evening before. One of them ends temporary assignments early.
Parked: see `90-open/parking-lot.md`, "Nightly tasks run on UTC".
