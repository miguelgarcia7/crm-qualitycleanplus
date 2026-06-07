# Flow: Contractor Clock-In

| Field | Value |
|---|---|
| Status | Accepted |
| Last updated | 2026-05-21 |
| Owner | Product (David) + Engineering |

End-to-end flow for contractors clocking in/out at properties. Covers the **QR code flow** (primary, modern), with a note about **tablet flow** (backup, legacy).

Related: ADR-0017, `20-domain/time-tracking.md`.

## Actors

| Actor | Role |
|---|---|
| Contractor | Person clocking in/out for their shift |
| System | Validates GPS, captures selfie, creates time_entry |
| (Recruiter / Office Manager / Admin) | View live grid; intervene if needed |

## Pre-conditions

- Contractor has an active `work_order` for the property
- Contractor has a unique phone number recorded in `people.phone`
- Property has `latitude`, `longitude`, `geofence_radius_meters` configured
- Property has a printed QR code posted on-site
- Payroll period for the property is `open`

---

## Primary flow: QR code (clock-in)

```
1. Contractor arrives at property
   │
   ▼
2. Scans QR code (printed at front desk, time clock area, etc.)
   │
   QR is a static URL: qcminute.com/clock-in/{property_id}
   │
   ▼
3. Browser opens the QC Minute clock-in page
   Property name shown at top ("Marriott Downtown Phoenix")
   "Enter your phone number to begin"
   │
   ▼
4. Contractor enters phone number → Submit
   │
   ▼
5. Server lookup:
   - Find person by phone (unique match required)
   - Find active work_orders for (person, property)
   - If no person matches: show "Phone number not recognized. Please contact HR."
   - If no work orders: show "No active work order at this property. Please contact your recruiter."
   - If contractor already has an open time_entry at THIS property → skip to step 7 (clock-out flow)
   │
   ▼
6. Page shows the contractor's active work orders at this property:
   ┌──────────────────────────────────────┐
   │  Property: Marriott Downtown Phoenix │
   │                                       │
   │  Select your shift:                  │
   │   ○ Housekeeper                      │
   │   ○ Banquet Server                   │
   └──────────────────────────────────────┘
   
   (Usually one option; multiple when contractor holds multiple work orders at same property)
   │
   ▼
7. Contractor selects → "Continue"
   │
   ▼
8. Browser requests GPS coordinates (navigator.geolocation.getCurrentPosition)
   - If denied: "Location access required to clock in. Please grant permission."
   - If unavailable: "Could not detect your location. Please try again outside or contact your recruiter."
   - If outside geofence: "You appear to be away from [Property Name]. Clock-in is only available at the property."
   │
   ▼
9. Browser requests camera access (navigator.mediaDevices.getUserMedia)
   - If denied: "Camera access required for clock-in verification."
   - Contractor taps button → captures selfie
   │
   ▼
10. Server creates time_entry:
    - person_id = contractor
    - work_order_id, property_id, payroll_period_id
    - source = clock_event, clock_method = qr
    - start_at_utc = now, start_at = now in property timezone
    - timezone = property's timezone
    - rate snapshots from work_order (pay/bill/ot rates)
    - clock_in_gps_lat, clock_in_gps_lng, clock_in_gps_accuracy_meters
    - clock_in_selfie_file_id (uploaded to S3)
    - source_metadata = { "qr_url": "...", "user_agent": "..." }
    │
    ▼
11. Confirmation screen:
    ┌──────────────────────────────────────┐
    │  ✓ Clocked in at 9:14 AM             │
    │                                       │
    │  Marriott Downtown Phoenix           │
    │  Housekeeper                          │
    │                                       │
    │  Good shift! 👋                       │
    └──────────────────────────────────────┘
    │
    ▼
12. Events fire:
    - ClockedIn event broadcast
    - Live grid updates for recruiter, PM, office_manager
    - Notification optionally sent
```

## Clock-out (end of shift)

Same QR flow:

```
1. Contractor scans QR (same URL)
2. Enters phone number
3. Server detects open time_entry for this person at this property
4. Page shows: "You're currently clocked in at 9:14 AM. Clock out?"
5. Browser captures GPS + selfie (same checks: geofence + camera)
6. Server updates the open time_entry:
   - end_at_utc = now, end_at in property tz
   - duration_minutes = computed
   - clock_out_gps_lat/lng/accuracy
   - clock_out_selfie_file_id
7. Confirmation: "✓ Clocked out at 5:32 PM. Worked: 8h 18m"
8. Events fire (ClockedOut), summary recomputed
```

## Lunch break (clock-out + clock-in same day)

Contractors handle lunch as two separate time_entries:

```
- Morning entry: clock in 9:00 → clock out 12:00 (lunch starts)
- Afternoon entry: clock in 12:30 → clock out 5:00 (end of shift)
```

Two distinct `time_entry` rows for the day. The lunch gap (12:00–12:30) isn't paid. Each clock event captures fresh GPS + selfie (slight redundancy, but maintains the verification model).

This pattern is what ADR-0017 specified for lunch breaks — simpler than tracking break_minutes within a single entry.

## Backup flow: Tablet (Sanctum-authenticated)

For contractors without smartphones, or as backup when QR isn't working:

```
1. Property has a tablet at front desk
2. Tablet is paired to the property (Sanctum token, property_id locked)
3. Contractor walks up to tablet:
   - Tablet shows list of contractors with active work orders at this property
   - Contractor picks themselves (or enters their PIN)
4. Clock-in/out happens via tablet's existing UI
5. time_entry created with clock_method = tablet, no GPS, no selfie
   (Tablet's property association is the verification — it can't serve other properties)
```

This flow uses the existing Sanctum + device authentication pattern. Property's responsibility to physically secure the tablet.

## Failure modes & user messages

| Failure | User sees | Server behavior |
|---|---|---|
| Phone number not in system | "Phone number not recognized. Please contact HR." | Refuses to advance; no time_entry created |
| No active work orders | "No active work order at this property. Please contact your recruiter." | Refuses to advance |
| GPS denied | "Location access required to clock in. Please grant permission." | Block; instructions to enable |
| GPS unavailable | "Could not detect your location. Please try again outside or use the tablet at the front desk." | Block; suggest tablet fallback |
| Outside geofence | "You appear to be [X meters] away from [Property]. Clock-in is only available at the property." | Block; log attempt (audit visibility) |
| Camera denied | "Camera access required for clock-in verification." | Block; instructions to enable |
| Already clocked in elsewhere | "You're currently clocked in at [Other Property]. Please clock out before clocking in here." | Block; show prior location |
| Payroll period closed | "This week's payroll is closed. Please contact your recruiter." | Block; rare (means someone forgot to close on time) |
| Network error during clock-in | "Couldn't complete clock-in. Please try again." | No time_entry created; safe to retry |
| Network error during clock-out (entry was created but no response) | "Clocked in! [retry option for confirmation]" | Idempotent — clock-out request can be re-sent safely |

## Permissions

| Action | Roles |
|---|---|
| QR-based clock-in / clock-out | contractor (via phone number lookup, no login required — the phone IS the credential) |
| Tablet-based clock-in / clock-out | device token (Sanctum-authenticated tablet) |
| View own time_entries | contractor (via separate QC Minute login) |
| Edit time_entries (correct mistakes) | recruiter (own property, period open), office_manager, super_admin, admin |
| Delete time_entries | super_admin, admin (recruiter only if period open) |

Note: clock-in itself is NOT authenticated as a session — the contractor doesn't "log in" via QR. The phone number + GPS + selfie ARE the authentication.

## Audit trail

Each clock event writes an `activity_log` entry:

```
[09:14:23] Clocked in    - Maria Lopez → Marriott DT, Housekeeper (clock_method=qr, gps=inside geofence)
[12:01:45] Clocked out   - Maria Lopez → Marriott DT, Housekeeper (clock_method=qr, gps=inside geofence)
[12:31:08] Clocked in    - Maria Lopez → Marriott DT, Housekeeper (clock_method=qr, gps=inside geofence) [afternoon entry]
[17:32:09] Clocked out   - Maria Lopez → Marriott DT, Housekeeper (clock_method=qr, gps=inside geofence)
```

## Selfie review

Selfies are stored on every clock event for fraud prevention. Reviewed only on dispute:

- "I didn't clock in at 9 AM, that wasn't me" → recruiter/admin can view the selfie to verify identity
- Retention: 1 year per ADR-0010, then auto-purged (legal hold blocks)

## Related

- ADR-0017 — Field check-in flows (full decision)
- `20-domain/time-tracking.md` — time_entries schema with new GPS/selfie fields
- `20-domain/work-orders.md` — what gets snapshotted on the time_entry
- `40-flows/recruiter-checkin.md` — parallel flow for recruiters
- `10-architecture/permissions-matrix.md` — time_entries permissions
- `20-domain/property-bible.md` — geofence configuration per property
