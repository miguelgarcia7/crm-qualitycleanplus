# Open: Contracts Data Model

| Field | Value |
|---|---|
| Status | Open — deliberately deferred (circle back later) |
| Blocker | David needs to define what structured data goes alongside the uploaded contract document |
| Owner | Product (David) |
| Last updated | 2026-05-21 |
| Blocks | Phase 02 (Property Bible) — partially. Profile/Departments/Positions/Rates can build without this; Contracts section needs it. |

> **Note (2026-05-21):** David explicitly chose to defer this item. We will build the provisional v1 contract shape (see below) so Phase 02 isn't blocked, and revisit the richer structured-data design later when business has clarity on what fields are needed.

## What we know

From Email 1 (client direction):

- Contracts are a section of the Property Bible
- Restricted view: Ownership (David, Miguel) + Payroll only
- Restricted edit: same
- Fields confirmed:
  - Document name
  - Type
  - Effective date (start)
  - Expiration date (end)
  - Uploaded by
  - Notes / version
- Expiration triggers alerts: 30 days, 14 days
- Alerts go to ownership

## What's open

The shape of the structured data beyond the upload. Possible richer model:

- **Just metadata + PDF** (minimal): document name, type, dates, uploaded by, notes. Everything else is in the PDF. ← simplest, what the email implies
- **Metadata + structured rate floors:** add per-position rate minimums or maximums that the bill rates can't go below/above without an exception flag. Adds enforcement.
- **Metadata + scope of service:** add structured fields for what positions are in scope, what hours of operation are covered, etc.
- **Metadata + clauses:** model specific clauses (indemnity, exclusivity, payment terms) as separate rows or jsonb fields. Heavy.

## Questions to resolve

1. **Beyond name, type, dates, uploaded by, notes — what structured fields do we need to query, report on, or enforce?**
2. **Should contracts cap or floor rates in the Bible?** (E.g. "the contract says we won't bill below $25/hr for Housekeeper at this property" — system enforces?)
3. **Multiple active contracts simultaneously possible?** (E.g. MSA + a special-event addendum.)
4. **What happens when a contract expires with no successor?** Allowed paths:
   - System keeps generating invoices but flags loudly
   - System blocks new work orders
   - System does nothing different (just informational)
   David's earlier answer was "keep going with a flag" — confirm this is final.
5. **Renewal workflow?** Should expiring contracts trigger a workflow (assigned to ownership) to renew?
6. **Versioning?** When a new contract supersedes an old one, do we link them? Keep the old as historical?
7. **Notes field shape:** free text, or structured (e.g. one row per "amendment")?

## Provisional v1 (build if not resolved by Phase 02)

Minimal shape — can extend later without breaking:

```sql
contracts
  - id
  - property_id (FK)
  - name (varchar) — display name
  - type (varchar or enum) — MSA, Addendum, SOW, Other
  - effective_date (date)
  - expiration_date (date)
  - file_id (FK to files polymorphic table)
  - uploaded_by (FK to people)
  - notes (text)
  - is_active (bool)
  - replaces_contract_id (FK to contracts, nullable — for renewals)
  - created_at, updated_at, deleted_at
```

If we go richer later, add columns. None of this blocks Phase 02's other Bible sections.

## Related

- `20-domain/property-bible.md` — where contracts live in the Bible
- `10-architecture/permissions-matrix.md` — `bible.contracts.*` permissions
- `80-plan/roadmap.md` — Phase 02 timing
