# Field Visits

| Field | Value |
|---|---|
| Status | Accepted |
| Last updated | 2026-05-21 |
| Owner | Product (David) + Engineering |

A **field visit** is a recruiter's visit to a property, recorded for accountability and visit history. It's operational metadata — **not paid time, not billable, not in the time-tracking pipeline.**

See ADR-0017 for the design rationale and split from `time_entries`.

## Why field visits are separate from time entries

| Concern | `time_entries` | `field_visits` |
|---|---|---|
| Who | Contractor (billable) | Recruiter (W-2 staff) |
| Purpose | Pay + bill calculation | Visit accountability + history |
| Affects payroll? | Yes | No |
| Affects invoice? | Yes | No |
| Required selfie | On every event (QR flow) | Check-in only |
| Geofence response | Block if outside | Allow with flag |
| Reportable to property? | Yes (invoices) | No (internal) |

Mixing them would force every billing query to filter out recruiter visits. Separate tables match semantic meaning.

## Shape

```
field_visits
  - id
  - person_id (FK — must be a W-2 staff member, typically recruiter)
  - property_id (FK)
  - status: enum (open | closed)
  
  -- Check-in
  - check_in_at (datetime, UTC)
  - check_in_gps_lat, check_in_gps_lng (decimal)
  - check_in_gps_accuracy_meters (int)
  - check_in_gps_status: enum (ok | unavailable | denied)
  - check_in_selfie_file_id (FK to files)
  - was_inside_geofence (bool, computed at check-in)
  
  -- Check-out (nullable until closed)
  - check_out_at (datetime, UTC, nullable)
  - check_out_gps_lat, check_out_gps_lng (decimal, nullable)
  - check_out_gps_accuracy_meters (int, nullable)
  - check_out_gps_status (enum, nullable)
  - was_late_close (bool — true when closed via "I'm at next property" flow)
  
  - notes (text, nullable — recruiter can annotate)
  - created_at, updated_at
```

## Lifecycle

```
[Recruiter on Back Office mobile, taps floating action button]
  │
  ▼
Modal context-aware:
  • No open visit for this person → shows "Check In" with property auto-detection (GPS match)
  • Open visit exists → shows "Check Out" for that property
  │
  ▼
[Check In path]
  System captures: GPS coordinates + selfie
  Property selection: auto-matched via GPS geofence (or manual pick if no match / multiple matches)
  
  field_visit created (status=open):
    - person_id, property_id
    - check_in_at = now
    - GPS coords + accuracy + status
    - was_inside_geofence = true/false based on property's geofence
    - check_in_selfie_file_id
  │
  ▼
[Recruiter works at property]
  │
  ▼
[Check Out path]
  System captures: GPS coordinates only (no selfie)
  
  field_visit updated:
    - status = closed
    - check_out_at = now
    - check_out GPS + status
  │
  ▼
[Recruiter moves to next property → cycle repeats]
```

## Geofence policy

Each property in the Property Bible has:

- `latitude`, `longitude` — set when the property is added (required for new properties)
- `geofence_radius_meters` — default 300m, configurable per property

For field visits:

- **Geofence is informational, not restrictive.** Recruiter can check in even if outside the fence (e.g. GPS is fuzzy, they're across the parking lot, etc.)
- `was_inside_geofence` flag is captured for review
- Reports can flag visits where geofence was not satisfied

This differs from contractor clock-in, where GPS check is **blocking** (to prevent QR-code-photograph fraud).

## "Forgot to check out" handling

When recruiter taps the floating button while a prior visit is still open:

```
Modal shows:
  "You're still checked in at [Property A] since [time]"
  
  Two actions:
    [Check Out of Property A]    [Cancel]
  
  On "Check Out of Property A":
    - Captures current GPS
    - Updates the open Property A visit:
        status = closed
        check_out_at = now
        check_out GPS + status
        was_late_close = true ← important flag for audit
    - Then proceeds to show the standard check-in for the new context
```

No silent auto-checkout. Recruiter explicitly acknowledges. `was_late_close = true` surfaces in visit history and reports for review.

## GPS status values

| Status | Meaning |
|---|---|
| `ok` | GPS reported coordinates with reasonable accuracy |
| `unavailable` | Browser couldn't get GPS (no signal, indoors with no WiFi, etc.) |
| `denied` | User denied permission |

For field visits, the system **allows check-in/out even with non-ok GPS status**. The flag is captured for review.

For contractor clock-in via QR (see `20-domain/time-tracking.md`), `unavailable` or `denied` GPS **blocks** the action.

## Selfie storage and retention

- Stored to S3 via the standard files mechanism
- `check_in_selfie_file_id` references the file row
- Retention: **1 year** per ADR-0010 rules (legal hold blocks purge)
- Reviewed only on dispute / investigation — not actively monitored
- File rows soft-deleted on purge; underlying S3 object deleted

## Permissions

| Action | Roles |
|---|---|
| Create a field visit (check in) | recruiter, super_admin, admin (own visits) |
| Close own visit (check out) | recruiter, super_admin, admin (own visits) |
| View own visit history | recruiter (own), super_admin, admin |
| View all visits | super_admin, admin, office_manager, hr |
| View visits for a specific property | super_admin, admin, office_manager, hr |
| Manual edit (e.g. correction) | super_admin only |

Recruiters cannot edit visits after the fact — only super_admin can correct mistakes (audit-clean).

## What field visits enable

- **Recruiter accountability** — managers can see "Jane visited 6 properties this week"
- **Per-property visit log** — "Marriott DT had 3 recruiter visits this month"
- **Fraud detection** — visits with `was_inside_geofence = false` are flagged for review
- **Performance metrics** — recruiter productivity correlated with visit frequency
- **Compliance** — proof of visits when required

## What field visits do NOT do

- ❌ Generate pay (recruiters' pay is separate)
- ❌ Generate invoices (not billable to property)
- ❌ Appear on contractor timesheets
- ❌ Trigger workflows (purely passive recording)

## UI surfaces

### Mobile (Back Office on phone)

- **Floating action button (FAB)** in the bottom corner of every back-office page
- Tap → context-aware modal with check-in or check-out action
- Capture screen with GPS indicator + camera preview

### Desktop / mobile (Back Office)

- **"My Visits"** page (recruiter) — list of own visit history with filters
- **"Property Visit History"** page (managers + admins) — visits to a specific property
- **"Recruiter Activity"** report — visits by recruiter over time

## Edge cases

| Case | Handling |
|---|---|
| Recruiter forgets to check out for days | Open visit remains until they explicitly close it (or admin manually closes). Surface as "Stale Open Visits" on admin dashboard. |
| GPS reports impossible coordinates (e.g. coords in middle of ocean) | Accuracy field captures the inaccuracy; admin can flag for review |
| Two recruiters at the same property at the same time | Both create their own visits; no conflict |
| Recruiter checks in at property they're NOT assigned to | Allowed (might be covering for another recruiter); flagged for review if pattern emerges |
| Person without `recruiter` role tries to check in | Blocked at permission check |
| Selfie file deletion request (privacy) | Standard PII anonymization — file deleted, selfie_file_id nulled, visit row retained |

## Related

- ADR-0017 — Field check-in flows (full decision)
- ADR-0010 — Legal hold + retention rules for selfies
- `20-domain/time-tracking.md` — contractor clock-in (parallel concern, different table)
- `20-domain/property-bible.md` — geofence configuration on properties
- `40-flows/recruiter-checkin.md` — step-by-step flow
- `10-architecture/permissions-matrix.md` — field_visits.* permissions
