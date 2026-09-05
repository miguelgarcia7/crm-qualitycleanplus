# Phase 09f — Server-side list pagination

| Field | Value |
|---|---|
| Status | ✅ Done (Pest green — 468 passing; Pint + Larastan clean; `tsc` + build clean). |
| Last updated | 2026-09-05 |
| Owner | Engineering |

## Context

Every list page shipped its whole table to the browser and paginated there — see
ADR-0029 for the numbers and the reasoning. This phase converts the four pages
that actually accumulate, and fixes the page control they all share.

## As built

### The shared control

- **`components/table/Pagination.tsx`** — presentational, and the only place the
  windowing lives: first page, last page, a window around the current one, gaps
  for what is elided. Constant slot count so buttons do not shuffle under the
  cursor while paging, and a gap standing in for a single page collapses into
  that page. First/last jump buttons either side of prev/next.
- **`TablePagination.tsx`** — client-side adapter (TanStack's 0-based
  `pageIndex` in, 1-based pages out). **Props unchanged**, so every list still
  using it picked up the windowed control with no edits — 24 at the time, 20
  once these four moved to `ServerPagination`.
- **`ServerPagination.tsx`** — server-side adapter, fed by the paginator meta
  (`current_page`, `last_page`, `per_page`, `total`, `from`, `to`).

The windowing was verified across every page position for lists of 1–200 pages:
constant 7 slots above 7 pages, current page always present, always anchored at
first and last, strictly ascending, no adjacent gaps. **This project has no JS
test runner, so that check is not in CI** — adding Vitest is an open decision.

### The four converted pages

| Page | Controller | Filters | Sorts |
|---|---|---|---|
| Timesheets | `TimesheetController` | tab (with counts), search (property, invoice #), property | property, week, hours, billed, submitted, invoice #, status |
| Work orders | `WorkOrderController` | search (contractor, property, position), status, property | contractor, property, position, pay, bill, start, status |
| Invoices | `InvoiceController` | search (invoice #, property), status, property | invoice #, property, issued, total, status |
| People | `PeopleController` | tab (with counts), search (name, email, phone), status | name, phone, recruiter *(contractors)*, hired *(staff)*, status |

Per-page choice is 10/25/50 everywhere, default 25 (was 10 client-side).

### Decisions worth remembering

- **Timesheets** — hours and billed amount sort in SQL via a `leftJoinSub` over
  the `time_summaries` aggregate, rather than being summed in PHP. Tab counts
  respect the search and property filter but not the tab itself, so they describe
  what switching tabs would show; they previously ignored every filter. Export
  follows the filters — a filtered list that exports something else is a
  reporting bug waiting to happen.
- **Work orders** — direct-hire progress is computed for the rows on the page
  rather than for every work order; `DirectHireProgress::forMany` runs a
  `TimeSummary` aggregate over everything it is handed, which was the most
  expensive thing on the page. Default order stays newest-first (`created_at`),
  matching the old `latest()`; no visible column carries that, so the table shows
  no sort arrow until one is clicked.
- **Invoices** — the new property filter is scoped like the list. Listing every
  property would have shown a recruiter the entire client roster in a dropdown,
  even though they can only see invoices for their own properties.
- **People** — the two tabs are two independent tables, and **both used to load
  on every visit**, including the hidden one. Now only the tab on screen is
  fetched, with counts for both from two `count()` queries. The tab is a
  permission boundary, not just a view: asking for `?tab=staff` without
  `people.staff.view` falls back to contractors rather than serving it. Sort and
  status whitelists are per tab (`recruiter` is contractors-only, `hire_date` is
  staff-only), and switching tabs clears both.

### Indexes

Edited into the create migrations per the pre-live convention (ADR/phase-09e
precedent), so **an existing dev database will not have them until refreshed**:

- `payroll_periods.week_start`, `timesheets.sent_for_approval_at`
- `work_orders.created_at`, `work_orders.start_date`
- `invoices.issue_date`
- `people.name`

### Model relations added

`Property::timesheets()` and `Property::invoices()` — both needed by the scoped
`whereHas` behind the filter dropdowns.

## Tests

New: `TimesheetListTest` (9), `WorkOrderListTest` (9), `InvoiceListTest` (8),
`PeopleListTest` (11). Each covers slicing, page-boundary integrity (no row on
two pages, none missing), both whitelists, filters, search, sort ordering and
fallback, and scoping under filters. `PeopleDirectoryTest` gained an assertion
that the staff tab is refused to a role without staff view.

One fixture trap worth remembering: `person()` creates a **new record per call**
and the factory defaults to **staff**, so a helper calling it per request quietly
grows the staff list under assertion. Pass the actor in.

## Out of scope / deferred

- **Config lists stay client-side** (ADR-0029): departments, positions,
  properties, job codes, KB categories/tags. They have the windowed control but
  still load in full, which is the intended behaviour.
- **Field visits and applicants** are ledger-shaped but empty today; they take
  the pattern when those features carry volume.
- **A JS test runner** for the windowing logic.
- **Browser verification** — none of these pages was opened in a browser; the
  multi-domain routing means a localhost preview 404s the admin surface.

## Related

- ADR-0029 — the policy and its reasoning
- `80-plan/phase-09c-audit-log.md` — the first server-paginated page
- `80-plan/phase-09e-architecture-hardening.md` — the in-place migration convention
