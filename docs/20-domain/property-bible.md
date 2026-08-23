# Property Bible

| Field | Value |
|---|---|
| Status | Accepted (v1 structure); contracts model TBD |
| Last updated | 2026-05-21 |
| Owner | Product (David) |

The Property Bible is the **central source of truth for everything related to a property**. It replaces information that today lives in emails, spreadsheets, and people's memory.

Every property has its Bible accessible through the back-office under `Properties → [Name] → Bible`.

## Sections of the Bible

A property's Bible has six sections:

1. **Profile** — basic identity and contact info
2. **Departments** — operational units within the property with their managers
3. **Positions & Rates** — what jobs exist here and what they pay/bill
4. **Holidays** — which calendar holidays this property observes (see below)
5. **Contracts** — signed agreements (restricted access)
6. **Notes / History** — audit log and timeline of changes

## 1. Profile

Standard property identity. One row in `properties`.

| Field | Notes |
|---|---|
| Name | E.g. "Marriott Downtown Phoenix" |
| Property Manager (name) | The PM's name, free text (separate from a `people` row — the PM might not have a login) |
| Property Manager (phone) | Direct contact |
| Hotel main phone | Front desk / switchboard |
| Address, city, state, zip | Postal address |
| Timezone | IANA timezone string (e.g. `America/Phoenix`) — drives all time-tracking math |
| Latitude / longitude | Used for geofencing (recruiter check-in + contractor QR clock-in). Required for new properties. |
| Geofence radius (meters) | Default 300m; configurable per property. Drives contractor clock-in block + recruiter check-in flag (per ADR-0017) |
| Closing day | Day of **week** the property's work week ends (ISO 1=Mon…7=Sun; unset = Sunday). Payroll periods start the next day — ends Wednesday → Thu–Wed timesheets. See ADR-0009 amendment. |
| Tax rate | Decimal for sales tax on invoices |
| Status | active / inactive — inactive properties don't accept new work orders |
| Soft delete | Standard |
| Activity logged | Standard |

The PM identity question: if the PM has a `people` row (e.g. they log into QC Minute), there's a separate `property_managers` association. The Bible's Profile fields store the *current* PM's name/phone for display even if there's no login.

## 2. Departments

Each property has a set of departments. Departments are predefined (dropdown), not free-text:

- Housekeeping
- Banquets
- Food & Beverage
- Public Spaces
- Kitchen / Culinary
- (extensible by super_admin via a `departments` reference table)

Per (property, department):

| Field | Notes |
|---|---|
| Manager name | Department head (free text — they often don't have logins) |
| Manager phone | Direct contact |
| Active | Allows enabling/disabling without deletion |

Used for: "I need to call the F&B manager at the Hilton — what's their number?" answered without hunting through emails.

The department names themselves live in a global catalog (`departments` table) managed at `/admin/departments` (gated `bible.departments.view` / `.edit`): create, rename (propagates everywhere via FK), deactivate — never delete, since `property_departments` references them. Per-property facts (manager name/phone) stay on the property's Departments tab.

## 3. Positions & Rates

This is the **core feature** of the Bible. It standardizes what jobs exist at each property and what they cost.

### Positions

Globally defined (`positions` table — e.g. `Housekeeper`, `Banquet Server`, `Dishwasher`, `Janitor`), so position names stay canonical across every property, work order, and report. The catalog is managed at `/admin/positions` (gated `bible.positions.view` / `.edit`): create, rename (propagates everywhere via FK), and deactivate — never delete, since rates and work orders reference positions. Per (property, position) you store:

| Field | Notes |
|---|---|
| Pay rate | What QCP pays the contractor (cents) |
| Bill rate | What QCP charges the property (cents) |
| OT pay rate | Overtime pay rate (cents — stored explicitly; the form computes 1.5× pay by default, with an explicit override toggle for contracts that differ) |
| OT bill rate | Overtime bill rate (cents — same: auto 1.5× bill unless overridden) |
| Effective date | When this rate takes effect |
| Active | Toggle without delete |
| Notes | Optional context |

### Multiple rate rows over time

When a rate changes, a new row is added with a new `effective_date`. The old row remains — it's the rate for entries before the change. The model:

```
property_position_rates
  - id
  - property_id
  - position_id
  - pay_rate, bill_rate, ot_pay_rate, ot_bill_rate (cents)
  - effective_date (date)
  - end_date (nullable — set when superseded)
  - active (bool)
  - created_by, created_at, updated_at, deleted_at
  - UNIQUE(property_id, position_id, effective_date)
```

The "current" rate for (property, position) is the row with the latest `effective_date ≤ today` and `active = true`.

### Rates are reference, work orders are authoritative

A work order can only be **created** against a position that has a current Bible rate at that property — the WO form offers only those positions, and the server rejects any other (position, property) pair. This guarantees every WO starts from Bible data; configure the position's rates on the property first.

Once past that gate, the Bible's rates **auto-fill** the work order form — they don't *force* the values. The user can override:

- Two contractors at the same hotel, same position, can have different WO rates (this is real and common)
- The Bible row stays as the standard; the WO is the actual

When a Bible rate changes, **existing work orders are NOT updated.** Dashboard warning may flag "23 active WOs are below the current Bible standard rate for Housekeeper at this property." See `20-domain/work-orders.md` for rate authority details.

## 3b. Holidays

A global holiday calendar (`holidays` table) is managed at `/admin/holidays`
(gated `bible.holidays.view` / `.edit`): 7 seeded **legal** holidays follow a
recurrence rule (e.g. Thanksgiving = 4th Thursday of November) and are
read-only; **custom** holidays are month/day dates repeating yearly, validated
with `checkdate` at creation. Properties opt in per holiday on their Bible's
Holidays tab (`property_holiday` pivot); new properties auto-attach the 4
defaults (New Year's, Labor Day, Thanksgiving, Christmas).

Work on an observed holiday's date (property-local start date) buckets as
holiday time at `qcp.time.holiday_multiplier` (default 1.5×) on the WO's base
rates — never overtime, though the hours still advance the weekly 40h counter.
Changing a property's holiday set (or deleting a custom holiday) recomputes
that property's open weeks; closed/invoiced weeks are frozen (ADR-0006). Full
algorithm: `20-domain/time-tracking.md` §Hourly bucketing.

## 4. Contracts

Contracts are the legal documents under which QCP works at a property. They're sensitive — only super_admin and payroll can view, edit, or download.

> **The detailed contract data model is open.** The general shape: a contract is **document upload + structured form fields**. Specifics deferred to `90-open/contracts-data-model.md`.

Shape we know we need:

| Field | Notes |
|---|---|
| Document name | E.g. "MSA - Marriott DT Phoenix - 2026" |
| Type | Master Services Agreement / Addendum / SOW / Other |
| Uploaded file | PDF or doc (`files` polymorphic) |
| Effective date | When the contract starts |
| Expiration date | When it ends (drives renewal alerts) |
| Uploaded by | Person who added it |
| Notes / version | Free text |
| Active | Toggle |

The full shape (clauses, rate floors, scope of service, indemnity) is a v2 / business-input decision. See `90-open/contracts-data-model.md`.

### Expiration alerts

A daily scheduled job (`ContractExpirationCheck`) surfaces:

- Contracts expiring in 30 days → alert to ownership + payroll
- Contracts expiring in 14 days → second alert (escalation)
- Contracts that have expired but have no successor → flagged on dashboard, but operations continue (work orders don't stop, invoices don't stop — see `90-open/parking-lot.md` for the policy decision)

## 5. Notes / History

The activity log filtered to this property — every Bible edit, every rate change, every contract upload. Read-only display, sortable by date.

## Permissions

| Action | Allowed roles |
|---|---|
| View Profile, Departments, Positions, Rates | super_admin, office_manager, hr, payroll, recruiter (own), property_manager (own) |
| Edit Profile | super_admin, office_manager |
| Edit Departments | super_admin, office_manager, recruiter (own) |
| Edit Positions | super_admin, office_manager, payroll |
| Edit Rates | super_admin, office_manager, payroll |
| View Contracts | super_admin, payroll only |
| Edit / Download Contracts | super_admin, payroll only |

Full matrix in `10-architecture/permissions-matrix.md`.

## Lifecycle

A property's Bible exists from the moment the property is added. Initial profile values are required to save the property. Departments, positions, and contracts can be added later.

A property goes `inactive` when QCP stops servicing it. Inactive properties:

- Cannot have new work orders created
- Existing work orders should be closed (manual action, not automatic)
- Read-only in the Bible (no edits)
- Reports continue to include them historically
- Can be reactivated

## Related

- `20-domain/work-orders.md` — how rates flow from Bible to WOs
- `20-domain/workflows.md` — pay increase workflow that creates new WOs
- `10-architecture/permissions-matrix.md` — full permission table
- `90-open/contracts-data-model.md` — what the contract structure needs
- `30-schema/property-tables.md` — column-level detail (TBD)
