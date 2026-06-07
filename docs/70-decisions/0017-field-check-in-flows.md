# ADR-0017: Field Check-In Flows (Contractor QR + Recruiter Floating Button)

| Field | Value |
|---|---|
| Status | Accepted |
| Decided | 2026-05-21 |
| Owner | Product (David) + Engineering |
| Supersedes | — (refines Phase 07 "Recruiter GPS check-in" from roadmap; extends ADR-0012's clock-in model) |
| Superseded by | — |
| Note | The QR/device URLs in this ADR's body use the old `qcminute.com` domain; the live domain is `qcpstaffing.com` per ADR-0024. |

## Context

Two operational needs surfaced that share architecture but serve different purposes:

1. **Contractor clock-in via QR code** — a browser-based alternative to property-locked tablets. Contractor scans a QR code at the property, identifies themselves by phone number, picks their work order, captures GPS + selfie, clocks in. Same flow to clock out and for lunch breaks.

2. **Recruiter check-in/out via floating button** — recruiters visit multiple properties per day. They tap a floating button on Back Office mobile to check in (GPS + selfie), do their work, tap again to check out (GPS only). Not paid time — just visit logging for accountability.

Both use browser geolocation + camera APIs. Both verify "you were physically here." But they're semantically distinct: contractor clock-in is a billable time event; recruiter check-in is operational metadata.

## Decision

### 1. Two separate data models

**Contractor clock-in** creates a `time_entry` (existing table) with new fields capturing the GPS + selfie context. Stays in the billing/payroll pipeline as today.

**Recruiter check-in** creates a `field_visit` (new table). Not billable. Not in the time-entries pipeline. Pure operational logging.

| Concern | time_entries | field_visits |
|---|---|---|
| Who | Contractor (billable) | Recruiter (W-2 staff) |
| Purpose | Pay + bill calculation | Accountability + visit history |
| State | Single open→closed event | Same |
| GPS | Captured (new) | Captured |
| Selfie | Captured on clock-in (new) | Captured on check-in only |
| Affects payroll? | Yes | No |
| Affects invoice? | Yes | No |

Mixing them would force every payroll/invoice query to filter out recruiter visits. Cleaner to keep distinct.

### 2. Contractor QR clock-in flow

- **QR code** at each property is a **static URL** with the property_id embedded: `qcminute.com/clock-in/{property_id}`
- The QR is generated when the property is set up; printed and posted at front desk / time-clock area
- Contractor scans → page opens with property context already loaded
- Contractor enters **phone number** — must be unique in `people.phone` (no shared phones)
- System finds the contractor by phone AND looks up their active work orders for THIS property
  - Usually 1 work order; possibly multiple (e.g. contractor works both Housekeeping and Banquet at the same hotel)
- Contractor selects work order (confirms what they're clocking into)
- System captures **GPS coordinates** and runs **geofence check** against property's location + radius
  - If outside fence → block clock-in with clear message
  - If GPS unavailable → block clock-in (require permission grant)
- System captures **selfie** via camera API
  - If camera denied → block clock-in
- `time_entry` row created (status=open, source=clock_event, clock_method=qr, plus GPS + selfie file_id)
- Confirmation screen with timestamp

### 3. Contractor clock-out + lunch breaks

- Same QR flow; system detects contractor has an open time_entry and shows "Clock Out" option
- Lunch breaks = **two separate time_entries per day** (morning entry closes when leaving for lunch; afternoon entry opens when returning, closes at end of shift)
- Each clock event captures fresh GPS + selfie (mostly redundant but maintains the verification model uniformly)

### 4. Tablets remain as backup

The legacy Sanctum-token tablet flow (per existing time-tracking docs) **stays in place**. Properties can use either:

- QR + browser (primary, modern flow)
- Tablet at front desk (backup; for contractors without smartphones, or as redundancy)

Both produce `time_entry` rows. `clock_method` field on time_entries distinguishes the source.

### 5. Recruiter floating button flow

- Recruiter on Back Office mobile sees a floating action button (FAB) consistently in the bottom corner
- Tap → context-aware modal:
  - If no open `field_visit`: shows "Check In" button
  - If an open `field_visit` exists: shows "Check Out" button for that property + visit context
- **Check-in:** captures GPS + selfie; creates `field_visit` (status=open)
- **Check-out:** captures GPS only; updates the open `field_visit` (status=closed, check_out_at, check_out_gps)
- GPS captured **only at explicit tap** — no background tracking

### 6. Property identification for recruiter check-in

Two paths:

- **GPS auto-match** — system checks recruiter's coordinates against all property geofences; if inside one, auto-selects it for the modal
- **Manual picker** — if no auto-match (rare) or multiple matches, recruiter picks from a list of their assigned properties

Auto-match is the happy path; picker is the fallback.

### 7. "Forgot to check out" handling

If recruiter taps the floating button at Property B while still checked-in at Property A:

- The modal shows: "You're still checked-in at Property A (since [time])"
- Two options:
  - **"Check Out of Property A"** button — closes Property A's visit with current GPS, then proceeds to show Check In for Property B
  - **"Cancel"** — recruiter can dismiss and resolve manually later
- The Property A check-out is flagged: `was_late_close = true` for audit visibility on the visit history
- NO silent auto-close — recruiter explicitly acknowledges

### 8. GPS unavailable handling

If GPS is unavailable or returns inaccurate coordinates:

- **Contractor clock-in via QR:** blocks (GPS is required for fraud prevention since QR could be photographed)
- **Recruiter check-in:** allows with `gps_status = 'unavailable'` flag. Recruiter still gets logged; the flag surfaces in reports for review

### 9. Selfie storage and retention

For both contractor selfies and recruiter selfies:

- Stored to S3 via the standard files mechanism
- `selfie_file_id` references the file row
- **Retention: 1 year**, then automatically purged per ADR-0010 retention rules (legal hold blocks purge)
- Reviewed only on dispute/investigation — not actively monitored

### 10. Geofence configuration

Each property in the Property Bible has:

- `latitude`, `longitude` (set when property is added)
- `geofence_radius_meters` (default 300m; configurable per property)

Both contractor clock-in (block) and recruiter check-in (auto-match) use this geofence.

## Consequences

### Positive

- Contractors using their own phones removes tablet maintenance burden over time
- GPS + selfie make clock-in fraud-resistant
- Recruiter visit accountability is captured automatically (no separate reporting)
- Clean separation between billable contractor time and operational recruiter visits
- "Forgot to check out" handled gracefully without silent state changes
- Tablets remain as backup — no abrupt transition, properties migrate as they're ready

### Negative

- Contractors must have smartphones with camera + GPS permissions granted
- Contractors without smartphones must use tablets (still supported)
- GPS accuracy varies (especially in dense buildings) — may cause clock-in friction
- Camera permission must be granted (one-time per device per browser); UX needs to handle the permission denial flow gracefully
- Recruiter floating button needs to stay accessible across all back-office mobile views — touch-friendly design

### Implementation requirements

Schema additions to `time_entries`:

```
- clock_method: enum (tablet | qr | manual)
- clock_in_gps_lat, clock_in_gps_lng (decimal)
- clock_in_gps_accuracy_meters (int)
- clock_in_selfie_file_id (FK, nullable)
- clock_out_gps_lat, clock_out_gps_lng (decimal)
- clock_out_gps_accuracy_meters (int)
- clock_out_selfie_file_id (FK, nullable — for QR flow, selfie captured both directions)
```

New `field_visits` table:

```
field_visits
  - id
  - person_id (FK to people — must be a W-2 staff member, typically recruiter)
  - property_id (FK)
  - status: enum (open | closed)
  - check_in_at (datetime)
  - check_in_gps_lat, check_in_gps_lng (decimal)
  - check_in_gps_accuracy_meters (int)
  - check_in_gps_status: enum (ok | unavailable | denied)
  - check_in_selfie_file_id (FK)
  - check_out_at (datetime, nullable)
  - check_out_gps_lat, check_out_gps_lng (decimal, nullable)
  - check_out_gps_accuracy_meters (int, nullable)
  - check_out_gps_status (enum, nullable)
  - was_late_close (bool — true when closed by "I'm at next property" flow)
  - was_inside_geofence (bool — computed at check-in)
  - notes (text, nullable)
  - created_at, updated_at
```

Schema additions to `properties`:

```
- latitude, longitude (decimal — required for new properties going forward)
- geofence_radius_meters (int, default 300)
```

Permissions (additions):

- `time_entries.qr_clock_in` — granted to contractor role
- `field_visits.create` — granted to recruiter, super_admin, admin
- `field_visits.view_own` — recruiter sees their own visits
- `field_visits.view_all` — admin, super_admin, office_manager, hr

## Alternatives considered

### A. Continuous GPS tracking for recruiters

Rejected. Privacy concerns + significant battery + consent overhead. Tap-to-capture GPS at explicit events gives the same accountability with much lower invasiveness.

### B. Native mobile app for contractors

Rejected for v1. Browser flow works; native app is a v2 candidate. Browser flow keeps the technical surface area smaller.

### C. Replace tablets entirely on day one

Rejected. Some contractors don't have smartphones; tablets serve as fallback. Coexistence is operationally pragmatic.

### D. Silent auto-checkout when recruiter checks in elsewhere

Rejected. The explicit "you're still checked-in at A" prompt is better UX and avoids confusion. Recruiter sees what's happening.

### E. Combine time_entries and field_visits into one table

Rejected. Forces every billing query to filter out recruiter visits. Separate tables match semantic meaning.

## Related

- ADR-0010 — Legal hold on PII (selfie retention rules)
- ADR-0012 — Unified inventory (tablet clock-in flow predates this)
- `20-domain/time-tracking.md` — time_entries enhanced with GPS + selfie
- `20-domain/field-visits.md` — recruiter check-in domain (new)
- `40-flows/contractor-clock-in.md` — full QR flow (new)
- `40-flows/recruiter-checkin.md` — full floating-button flow (new)
- `10-architecture/permissions-matrix.md` — new field_visits permissions
- `20-domain/property-bible.md` — geofence configuration on properties
- `80-plan/roadmap.md` — Phase 07 expanded to cover both flows
