# ADR-0029: List Pagination — Server-Side for Ledgers, Client-Side for Config

| Field | Value |
|---|---|
| Status | Accepted |
| Decided | 2026-09-05 |
| Owner | Engineering |
| Refines | ADR-0022 (frontend stack), ADR-0027 (Preline/React hybrid) |
| Supersedes | — |
| Superseded by | — |

## Context

Every back-office list page was built the same way: the controller ran `->get()`
with no limit, handed the entire table to the browser as an Inertia prop, and
TanStack Table sliced it into pages client-side. Search, filtering and sorting
also ran in the browser, over that full array.

That is fine for a list that stays small. It fails for anything that
accumulates. By September 2026, on imported legacy data:

| Page | Rows shipped per visit |
|---|---|
| Work orders | 2,580 |
| Timesheets | 1,787 |
| Invoices | 1,508 |
| People | 1,451 (both tabs, including the hidden one) |

Timesheets alone grow ~3,600 rows/year at 70 properties — one per property per
week — so this gets worse on a schedule, not by accident.

The visible symptom was cosmetic: the shared `TablePagination` rendered *every*
page as a button (`Array.from({ length: pageCount })`), which at 1,787 timesheets
put **179 numbered buttons** in one row. The real cost was underneath it.

`AuditLogController` had already gone server-side, because the activity log grows
without bound — so the pattern existed, on exactly one page, as an exception.

## Decision

**Lists that accumulate paginate in the database. Lists that are bounded do not.**

The dividing line is *ledger vs config*, not row count today:

- **Ledger** — a record of things that happened, which only ever grows:
  timesheets, work orders, invoices, people, time entries, the audit log, field
  visits, applicants. These paginate server-side.
- **Config** — a maintained set with a natural ceiling: departments, positions,
  properties, job codes, KB categories and tags. These stay client-side, where
  instant filtering with no round-trip is genuinely better and conversion would
  add complexity for nothing.

Field visits and applicants are ledger-shaped but currently empty; they take the
pattern when those features carry real volume.

### What "server-side" means here

Five things move together. Taking fewer than all five produces a worse bug than
the one being fixed:

1. **Pagination** — `->paginate($perPage)->withQueryString()`; the front end
   receives one page plus a `pagination` meta block.
2. **Filtering, searching and sorting** — in the query builder. If the server
   paginates but the browser still filters, search matches only the rows
   currently loaded, silently.
3. **The query string is the state** — `?status=active&sort=name&page=3`. A
   filtered view becomes a link someone can send, the back button steps through
   filters, and a refresh keeps the view. Inertia partial reloads (`only: [...]`)
   re-fetch just the rows.
4. **Whitelists on `sort` and `per_page`** — a hand-edited query string must not
   reach the query builder or ask for every row at once. Unknown values fall back
   to the default rather than erroring.
5. **A stable tiebreaker on `id`** — weeks, start dates and issue dates tie
   constantly. Without one, a row appears on two pages while another appears on
   none. This is a data bug, not a cosmetic one, and every converted page has a
   test for it.

### Scoping is part of the query, not a filter over results

Row-level scoping (a recruiter's assigned properties, their own contractors)
belongs in the query so it survives paging. Where a filter and a scope key on the
same column, naming another value in the URL must narrow, never widen. Filter
*dropdowns* are scoped too — an unscoped property list leaks the client roster to
someone who cannot see those rows.

### Offset, not cursor

`paginate()` (offset + count), not `cursorPaginate()`. Cursor pagination stays
fast at any depth but cannot give page numbers or a total, and admin users ask
for "page 12" and expect to see "1,787 results". `COUNT(*)` over indexed rows at
this scale is not the bottleneck. Revisit only if a table reaches millions.

## Consequences

- One `Pagination` component owns the windowing (`1 … 89 90 91 … 179`, constant
  slot count, first/last jumps). Two thin adapters feed it: `TablePagination`
  from a TanStack instance, `ServerPagination` from a Laravel paginator's meta.
  `TablePagination` kept its existing props, so every client-side list picked up
  the windowed control without edits.
- Columns assembled from related rows — a contractor's properties, a staff
  member's roles — lose sorting. Ordering by a comma-joined string is not
  meaningful, and supporting it would mean a subquery for something nobody wants.
- Filter options come from enums or scoped queries rather than from the loaded
  rows, which server-side would have degraded to "whatever page one happens to
  hold".
- Sort whitelists are per tab where a page has tabs, since a column can exist on
  one side and not the other.
- Sorted and filtered columns need indexes; these were edited into the create
  migrations per the pre-live convention, so existing dev databases do not have
  them until refreshed.

## Related

- ADR-0022 — frontend stack (React + Inertia)
- ADR-0028 — report rollups (the other answer to "this query is too expensive")
- `80-plan/phase-09f-list-pagination.md` — as built
- `80-plan/phase-09c-audit-log.md` — the first page to do this
