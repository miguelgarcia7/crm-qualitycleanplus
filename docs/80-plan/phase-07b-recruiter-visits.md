# Phase 07b — Recruiter Field Visits (GPS + selfie check-in)

| Field | Value |
|---|---|
| Status | ✅ Done (Pest green — 8 field-visit tests; Pint + Larastan clean, build + types clean, `migrate:fresh --seed` clean). |
| Last updated | 2026-06-08 |
| Owner | Engineering |

## As built (current state)

- **Schema:** `field_visits` (ADR-0017) + `FieldVisitStatus`/`GpsStatus` enums; `FieldVisit`
  model (`scopeOpen`, `durationMinutes`, `checkInSelfie`) + factory. `StoreSelfie` generalized
  to any Eloquent fileable.
- **Actions:** `CheckInRecruiter` (one-open-visit guard; `was_inside_geofence` via `Geofence`;
  selfie required) + `CheckOutRecruiter` (GPS only; `was_late_close` for forgot-to-check-out).
- **Controller/routes:** `FieldVisitController` — `context` (JSON: open visit + assignable
  properties w/ coords+radius for the FAB), `store`, `checkOut` (`late` flag), `index`
  (own vs all by permission + off-geofence/late/open filters). `backoffice.field-visits.*`.
- **Front-end:** global `CheckInFab` (mounted in `MainLayout`, gated `field_visits.create`):
  GPS auto-match property (client haversine) → selfie (`getUserMedia`) → check in; open-visit
  state → check out, or "different property" → late-close then check in. `field-visits/index`
  history page + sidebar entry. Dashboard widgets: recruiter "My visits this week",
  office/admin "Stale open visits".
- **Tests:** `tests/Feature/FieldVisitTest.php` (8). **Seed:** one closed + one open visit
  for the recruiter at the Sample Marriott.

> Property-page "recent visits" section deferred (avoids churn in the large tabbed property
> show; admins use the Field Visits list, filterable by property). PMs are on QC Minute.

## Context

Recruiters check in/out of the properties they visit from Back Office mobile via a floating
action button (ADR-0017): GPS + selfie on check-in, GPS on check-out. The geofence is
**informational** (`was_inside_geofence` flag, not blocking) and GPS may be unavailable
(allowed, flagged). A "forgot to check out" path closes a stale open visit (`was_late_close`)
before starting the next. Produces `field_visits` rows — accountability logging only, no
payroll/invoicing impact.

07a (contractor QR clock-in) shipped; the tablet/device path is **07c** next.

## Authoritative docs

ADR-0017 · `40-flows/recruiter-checkin.md` · `20-domain/field-visits.md`. `field_visits.*`
permissions already seeded (`manual_edit` deferred).

## Reuse

`Geofence` helper + `StoreSelfie` (generalized to any fileable) + `File` storage from 07a;
`Property` lat/long; `auth.permissions` shared prop; browser GPS + `getUserMedia` patterns.

## Increments

0. **Infra** — this doc; `FieldVisitStatus`/`GpsStatus` enums; `create_field_visits` migration;
   `FieldVisit` model + factory; generalize `StoreSelfie`.
1. **Backend** — `CheckInRecruiter`/`CheckOutRecruiter` actions; `FieldVisitController`
   (context/store/checkOut/index); routes.
2. **Front-end** — global `CheckInFab` (GPS auto-match + selfie + forgot-to-checkout) in
   `MainLayout`; field-visits history page + sidebar; property recent-visits section;
   dashboard widgets.
3. **Tests + docs + seed** — `FieldVisitTest`; demo visits; gates; this doc + roadmap +
   Domain README (`FieldVisits` context).

## Out of scope / deferred

Tablet/Sanctum device path (07c); manual visit edits (`manual_edit`); formal report catalog
(Phase 09); offline queueing.

## Related

ADR-0017; `phase-07a-contractor-clock-in.md`; `80-plan/roadmap.md`.
