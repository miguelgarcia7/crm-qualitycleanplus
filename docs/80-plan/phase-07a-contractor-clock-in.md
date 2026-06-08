# Phase 07a — Contractor QR Clock-In (GPS + selfie)

| Field | Value |
|---|---|
| Status | ✅ Done (Pest green — 9 clock-in tests; Pint + Larastan clean, build + types clean, `migrate:fresh --seed` clean). |
| Last updated | 2026-06-08 |
| Owner | Engineering |

## As built (current state)

- **Schema:** `time_entries` += `clock_in_/clock_out_` `gps_lat`/`gps_lng`/`gps_accuracy_meters`
  + `*_selfie_file_id` (FK `files`), all nullable. `TimeEntry` fillable/casts +
  `clockInSelfie()`/`clockOutSelfie()`.
- **Geofence:** `app/Domain/Shared/Support/Geofence.php` (haversine `distanceMeters`,
  `distanceToProperty`, `contains`).
- **Actions:** `ClockInContractor` (blocks if already open / outside fence / GPS missing;
  resolves open period; stores selfie via `StoreSelfie`; opens entry `clock_method=qr`) +
  `ClockOutContractor` (sets end/duration + clock-out GPS/selfie, `RecomputeTimeSummary`,
  broadcast). Selfies persist as polymorphic `File`s on `FILESYSTEM_DISK`.
- **Public surface:** `Public/QrClockInController` (show/lookup/clockIn/clockOut) on
  `qcpstaffing.com/clock-in/{property}` — no auth, `throttle:30,1`, closure-free
  `routes/clock-in.php`. Phone re-verified against the WO/entry owner on every event.
- **Front-end:** `views/public/clock-in/index.tsx` (bare layout via page-level `layout`):
  phone → pick WO (or clock-out) → browser GPS + `getUserMedia` selfie → confirm, with
  blocked-state messaging. Back-office printable QR at `/admin/properties/{id}/qr`
  (`qrcode.react`, `PropertyController@qr`), linked from the property header.
- **Tests:** `tests/Feature/ContractorClockInTest.php` (9). **Seed:** Sample Marriott given
  lat/long + contractors given phones so the QR flow is demoable.

## Context

Contractors clock in/out from their **own phone** by scanning a static per-property QR code
(`qcpstaffing.com/clock-in/{property_id}`) — no login. Phone number + GPS + selfie are the
credential (ADR-0017). GPS is validated against the property geofence and **blocks** clock-in
when outside; a selfie is required on every clock event. The result is a normal billable
`time_entry` (`clock_method = qr`) that flows through the existing money pipeline.

Phase 07 bundles two flows; this slice (**07a**) ships only the contractor QR clock-in. The
**recruiter field-visit** flow is **07b**; the **tablet/Sanctum device** path is **on hold**
pending review of the legacy project (reuse vs rebuild).

## Authoritative docs

ADR-0017 · `40-flows/contractor-clock-in.md` · `20-domain/time-tracking.md` · ADR-0010
(selfie retention) · `10-architecture/domain-routing.md`.

## Reuse

`TimeEntry` + `RecomputeTimeSummary::dispatchSync` + `TimeEntrySaved` broadcast; `File`
(polymorphic, `FILESYSTEM_DISK`); `Person.normalized_phone` lookup; `WorkOrder::scopeActive`;
`properties.latitude/longitude/geofence_radius_meters` (exist). New: `Geofence` helper,
`clock_in_/clock_out_` GPS + selfie columns, `qrcode.react` (client QR).

## Increments

0. **Infra** — this doc; `qrcode.react`; `add_clock_geo_to_time_entries` migration; `TimeEntry`
   columns + selfie relations; `Geofence` helper (haversine + `contains`).
1. **Backend** — `ClockInContractor` / `ClockOutContractor` actions (geofence block, selfie
   File, period resolve, recompute, broadcast); `Public/QrClockInController` (show/lookup/
   clockIn/clockOut) + public throttled routes on the qcminute domain.
2. **Front-end** — public clock-in page (bare layout; phone → WO → GPS → selfie → confirm;
   clock-out; blocked states); back-office printable per-property QR page (`qrcode.react`).
3. **Tests + docs + seed** — `ContractorClockInTest`; seed property lat/long; gates; this doc +
   roadmap + Domain README.

## Out of scope / deferred

Recruiter field visits (07b); tablet/Sanctum device path (on hold); formal off-geofence /
stale / late-close reports (Phase 09); S3 wiring (env only); QR rotation.

## Related

`80-plan/roadmap.md`; ADR-0017; `phase-06-dashboards.md`.
