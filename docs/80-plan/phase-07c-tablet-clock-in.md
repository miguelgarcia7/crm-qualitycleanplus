# Phase 07c — Tablet / Device Clock-In (Sanctum-paired kiosk)

| Field | Value |
|---|---|
| Status | ✅ Done (Pest green — 8 tablet tests; Pint + Larastan clean, build + types clean, `migrate:fresh --seed` clean). |
| Last updated | 2026-06-08 |
| Owner | Engineering |

## As built (current state)

- **Auth/dep:** `laravel/sanctum ^4.3` + `personal_access_tokens`; `config/auth.php` gains a
  `device` guard (driver `sanctum`, provider `devices`); `device/*` CSRF-excepted in `bootstrap/app.php`.
- **Schema/model:** `devices` (property_id, name, `activation_code`, `is_activated`, `app_version`,
  `last_seen_at`) + `Device` (`HasApiTokens`, `newCode`/`regenerateCode`/`markSeen`) + factory.
  Permission `devices.manage` (super_admin only — narrowed 2026-06-13; originally admin/office_manager).
- **Shared actions parametrized:** `ClockInContractor::handle(..., $clockMethod='qr', $enforceGeofence=true)`
  and `ClockOutContractor` (optional selfie/GPS) — tablet calls them with `clockMethod='tablet'`,
  `enforceGeofence=false` (no GPS captured).
- **Kiosk API:** `Device/DeviceClockController` — `activate` (public, `ActivateDevice`),
  `context`/`lookup`/`clockIn`/`clockOut` (`auth:device`). `routes/device.php` on the qcminute
  domain, throttled. Phone re-verified against the WO/entry owner each event.
- **Front-end:** `views/device/index.tsx` (bare-layout kiosk: pair via code → phone → WO/selfie
  → clock in/out via Bearer-token `fetch`; "Unpair"). Back-office `views/admin/devices/index.tsx`
  (create → shows activation code, regenerate, revoke) + sidebar "Devices".
- **Tests:** `tests/Feature/TabletClockInTest.php` (8). **Seed:** one un-paired tablet
  (code `QCP123`) on the Sample Marriott.

> `HandleInertiaRequests` now shares `auth` only for `Person` users — a tablet `Device` on the
> `device` guard is never treated as an Inertia user.

## Context

A shared **front-desk tablet** is the backup clock-in surface for contractors who don't use
their own phone (07a QR). Adopt-and-modernize the legacy QC Minute DeviceModule: a tablet is
**paired once to a property** via an activation code → holds a **Sanctum device token** → any
contractor clocks in/out by entering **phone + selfie**. The paired device is the location
proof, so **no GPS / geofence**. Clock events reuse the existing `ClockIn/ClockOutContractor`
actions and produce a billable `time_entry` with `clock_method = tablet`.

## Reference

Legacy `www.qcpstaffing.site/app/Modules/DeviceModule` (device + one_time_code → sanctum-device
guard; phone lookup; photo on clock-in); improved per `www.minute.site` (alphanumeric code +
`is_activated` flag). ADR-0017 · `10-architecture/identity-and-auth.md` (device flow).

## Scope notes

- Sanctum `device` guard (provider `devices`); the device token authenticates kiosk APIs — no
  per-contractor token (phone identifies the contractor per action).
- No GPS/geofence on tablet entries; selfie required on clock-in (optional clock-out).
- Kiosk endpoints are stateless Bearer APIs → `device/*` excluded from CSRF.
- Permission `devices.manage` (super_admin only — narrowed 2026-06-13) for back-office device CRUD.
- New domain context **`Devices`**.

## Increments

0. **Infra** — this doc; `laravel/sanctum` + `personal_access_tokens`; `device` guard +
   `devices` provider; `devices` table; `Device` model (`HasApiTokens`) + factory;
   `devices.manage` permission.
1. **Backend** — parametrize `ClockIn/ClockOutContractor` (clock_method + enforceGeofence +
   optional selfie/gps); `ActivateDevice` action; `Device/DeviceClockController`
   (activate/context/lookup/clockIn/clockOut); `routes/device.php` + CSRF except; back-office
   `DeviceController` CRUD.
2. **Front-end** — kiosk `/device` page (activation + clock, Bearer token in localStorage);
   back-office Devices management page + sidebar.
3. **Tests + docs + seed** — `TabletClockInTest`; seed a paired device; gates; this doc +
   roadmap + Domain README (`Devices` context).

## Out of scope / deferred

PWA / installable kiosk + offline queue; device tamper/version gating; per-contractor short
tokens; push.

## Related

ADR-0017; `phase-07a-contractor-clock-in.md`; `phase-07b-recruiter-visits.md`; `roadmap.md`.
