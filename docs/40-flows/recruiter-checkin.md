# Flow: Recruiter Check-In / Check-Out

| Field | Value |
|---|---|
| Status | Accepted |
| Last updated | 2026-05-21 |
| Owner | Product (David) + Engineering |

End-to-end flow for recruiters visiting properties. Captures GPS + selfie for accountability. NOT paid time, NOT billable — purely operational logging.

Related: ADR-0017, `20-domain/field-visits.md`.

## Actors

| Actor | Role |
|---|---|
| Recruiter | Person visiting properties |
| System | Captures GPS, selfie, manages visit lifecycle |
| Office Manager / Admin / HR | View visit history for accountability + reports |

## Pre-conditions

- Recruiter is logged into the Back Office on their mobile device
- Browser has been granted GPS + camera permissions (one-time per device)
- Each property they visit has `latitude`, `longitude`, `geofence_radius_meters` configured

---

## The floating action button (FAB)

A persistent floating button sits in the bottom-right corner of every Back Office mobile view. Always accessible regardless of which screen the recruiter is on.

```
┌────────────────────────────────────┐
│                                     │
│   (Back Office page content)        │
│                                     │
│                                     │
│                                     │
│                                     │
│                                     │
│                              ┌───┐  │
│                              │ ⊙ │  │  ← FAB (visit check-in/out)
│                              └───┘  │
└────────────────────────────────────┘
```

Tap → context-aware modal opens.

---

## Path 1: Check In (no open visit)

```
1. Recruiter arrives at property, taps FAB
   │
   ▼
2. Browser requests GPS coordinates
   │
   ▼
3. System checks GPS against all property geofences:
   - If exactly one property matches → auto-select it
   - If no match → show property picker (list of recruiter's assigned properties)
   - If multiple match (rare) → show picker with matches highlighted
   - If GPS unavailable → show picker with "GPS unavailable — please select" message
   │
   ▼
4. Modal shows:
   ┌─────────────────────────────────────┐
   │  Check In                            │
   │                                       │
   │  Property: Marriott DT Phoenix       │
   │  (auto-detected via GPS)             │
   │                                       │
   │  Time: 10:14 AM                      │
   │                                       │
   │  📸 Take selfie to continue           │
   │   [Open Camera]                      │
   │                                       │
   │   [Cancel]   [Confirm Check-In]      │
   └─────────────────────────────────────┘
   │
   ▼
5. Camera opens → recruiter captures selfie
   │
   ▼
6. Tap "Confirm Check-In"
   │
   ▼
7. Server creates field_visit:
   - person_id = recruiter
   - property_id = selected
   - status = open
   - check_in_at = now
   - check_in_gps_lat, lng, accuracy
   - check_in_gps_status = ok (or unavailable / denied)
   - was_inside_geofence = true/false (computed)
   - check_in_selfie_file_id
   │
   ▼
8. Confirmation toast: "Checked in at Marriott DT Phoenix"
   FAB icon updates to show "checked in" state (small property badge)
```

## Path 2: Check Out (open visit exists)

```
1. Recruiter is leaving the property, taps FAB
   │
   ▼
2. Modal shows (context-aware — knows there's an open visit):
   ┌─────────────────────────────────────┐
   │  You're checked in at:               │
   │  Marriott DT Phoenix                 │
   │  (since 10:14 AM, 2h 47m ago)        │
   │                                       │
   │   [Check Out]                        │
   │                                       │
   │   [Cancel]                           │
   └─────────────────────────────────────┘
   │
   ▼
3. Tap "Check Out"
   │
   ▼
4. Browser captures GPS coordinates (no selfie this time)
   │
   ▼
5. Server updates field_visit:
   - status = closed
   - check_out_at = now
   - check_out_gps_lat, lng, accuracy, status
   │
   ▼
6. Confirmation toast: "Checked out — visit lasted 2h 47m"
   FAB returns to default state
```

## Path 3: "Forgot to check out" (open visit at A, taps at B)

```
1. Recruiter at Property B (in their car, in the lobby, etc.), taps FAB
   │
   ▼
2. System detects: open field_visit exists for Property A
   │
   ▼
3. Modal shows:
   ┌─────────────────────────────────────┐
   │  ⚠️ You're still checked in at:       │
   │  Marriott DT Phoenix                 │
   │  (since 10:14 AM, 4h 12m ago)        │
   │                                       │
   │  Looks like you may have forgotten   │
   │  to check out.                       │
   │                                       │
   │   [Check Out of Marriott DT]         │
   │                                       │
   │   [Cancel — I'll handle later]       │
   └─────────────────────────────────────┘
   │
   ▼
4. Tap "Check Out of Marriott DT"
   │
   ▼
5. Server captures current GPS, updates the Property A visit:
   - status = closed
   - check_out_at = now (current time, not the actual leaving time)
   - check_out_gps_lat, lng (current coords — might not match Property A)
   - check_out_gps_status
   - was_late_close = true  ← flagged for audit
   │
   ▼
6. Modal advances to standard Check-In flow for Property B
   (GPS detected; selfie capture; new field_visit created)
```

`was_late_close = true` surfaces in:

- Recruiter's own visit history (with a small "late close" badge)
- Admin reports ("Visits with late close" filter)
- Periodic visibility for managers to spot patterns

## GPS unavailable scenarios

Per ADR-0017, field visits allow check-in/out even with non-ok GPS:

```
On check-in attempt with GPS unavailable:
  Modal shows:
    "Couldn't detect your location.
     Please select the property you're visiting:"
     [property picker]
    "Your GPS status will be logged as 'unavailable' for review."
  
  Recruiter picks property → flow continues
  field_visit created with check_in_gps_status = unavailable
```

Same on check-out — GPS unavailable doesn't block, just gets flagged.

## Camera denied scenario

```
On check-in attempt with camera denied:
  Modal shows:
    "Camera permission required for check-in verification.
     [Instructions to enable in browser settings]
     
     [Try Again]   [Cancel]"
```

Unlike GPS, camera is required for check-in (no fallback). Recruiter must grant permission to proceed.

## Multiple visits per day

A typical recruiter day:

```
9:00 AM   - Check in at Property A (selfie, GPS)
11:30 AM  - Check out (GPS only)
12:00 PM  - Check in at Property B (lunch meeting with PM)
1:15 PM   - Check out
2:00 PM   - Check in at Property C
4:30 PM   - Check out

→ 3 field_visits records for the day
```

Each is independent. Visit history shows all sorted by check_in_at.

## Selfie storage

- Stored to S3 on check-in via the standard files mechanism
- `check_in_selfie_file_id` references the row
- Retention: 1 year per ADR-0010
- Reviewed on dispute only

## Permissions

| Action | Roles |
|---|---|
| Create field_visit (check in) | recruiter, super_admin, admin |
| Close own field_visit (check out) | recruiter, super_admin, admin |
| View own visit history | recruiter (own), super_admin, admin |
| View all visits | super_admin, admin, office_manager, hr |
| Manual edit (correction) | super_admin only |

Recruiters cannot edit visits after the fact — only super_admin can correct mistakes.

## Audit trail

```
[10:14:08] Field visit checked in   - Jane (recruiter) → Marriott DT (gps=inside geofence)
[12:58:33] Field visit checked out  - Jane → Marriott DT (gps=inside geofence)
[13:15:21] Field visit checked in   - Jane → Hyatt DT (gps=inside geofence)
[16:42:55] Field visit checked out  - Jane → Hyatt DT (gps=inside geofence, late_close=false)
```

For late-close cases:

```
[09:14:08] Field visit checked in    - Jane → Marriott DT
[14:32:11] Field visit late-closed   - Jane → Marriott DT (closed via "I'm at next property" flow; check_out_gps was at Hyatt DT)
[14:32:11] Field visit checked in    - Jane → Hyatt DT
```

## Reporting surfaces

- **Recruiter dashboard** (mobile + desktop): "My visit history" widget — recent 10 visits
- **Property page** (back office): "Recent recruiter visits" section showing who's been there and when
- **Admin reports:**
  - "Recruiter Activity" — visits per recruiter per week/month
  - "Stale Open Visits" — visits open >24h (likely forgotten check-outs)
  - "Off-geofence Visits" — visits with `was_inside_geofence = false`
  - "Late-Closed Visits" — visits with `was_late_close = true`

## Edge cases

| Case | Handling |
|---|---|
| Recruiter loses phone | Open visit stays open until they manually close it or admin closes it |
| Recruiter rejects GPS permission entirely | Each visit logs gps_status=denied; admin can review pattern |
| GPS reports impossible coords | Logged with accuracy field; admin can flag for review |
| Multiple recruiters at same property at same time | Both create their own visits — no conflict |
| Recruiter visits a property they're not assigned to | Allowed (might be covering); flagged in reports for pattern review |
| FAB hidden on certain pages (e.g. modals) | Reappears when modal closes; always accessible from main back-office views |

## Related

- ADR-0017 — Field check-in flows (full decision)
- `20-domain/field-visits.md` — domain model
- `20-domain/property-bible.md` — geofence config per property
- `40-flows/contractor-clock-in.md` — parallel flow for contractors
- `10-architecture/permissions-matrix.md` — field_visits.* permissions
