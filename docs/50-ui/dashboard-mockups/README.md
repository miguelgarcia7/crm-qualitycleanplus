# Dashboard mockups by role

Approved direction for the back-office dashboard (`/admin/dashboard`), designed with Miguel on
2026-10-11. The live canvas is the claude.ai artifact "Dashboards by Role":
https://claude.ai/artifact/KCHeApuTcj6gfLwLT2cbaU. These `.dc.html` files are copies of its artboards
(open one in a browser to read the markup; it won't render outside the canvas). Sample data in the
mockups is illustrative, at production scale (~30 properties, ~100 contractors).

Status: build one role at a time, on branch `dashboard`. The property manager's view is on hold.

| View | Built |
|---|---|
| Admin · Today | Yes: `AdminToday` service, `admin/dashboard/admin-today` page, shared `components/dashboard/DashCard` |
| Admin · Analytics | Yes: `AdminAnalytics` service, `admin/dashboard/admin-analytics` page, tabs via `?view=analytics` (needs `reports.financial.view`), period via `?period=mtd|last_month|quarter` |
| Admin · Workforce | Yes: `AdminWorkforce` service, `admin/dashboard/admin-workforce` page, `?view=workforce` (needs `people.contractors.view`). Recruiters table is full width; property expenses card left out until expenses are ported |
| Recruiter | Yes: `RecruiterHome` service, `admin/dashboard/recruiter` page. Submit from the dashboard (with a confirm); "Check in" opens the existing check-in panel |
| Payroll | Yes: `PayrollHome` service, `admin/dashboard/payroll` page. The pay run is the week before last (Mon–Sun, Miguel 2026-10-11), taking in each property's pay week that ended in it; `?week=` steps to other weeks. Mark paid from the dashboard (with a confirm) |
| HR | Yes: `HrHome` service, `admin/dashboard/hr` page. Promote from the dashboard (with a confirm). "Expiring documents" replaced by Terminations in progress until certifications exist |
| Office manager, Front desk | Not yet. These roles still see the Phase 06 widgets (`DashboardMetrics`) |

Shared pieces: `Dashboards/Support` (`ApprovalQueue`, `RequestQueue`, `DirectHireSoon`, `StaffAway`, `DashboardFormat`) and
`components/dashboard` (`DashCard`, `DashboardHeader`, `RequestList`, `ExpiringCard`, `DirectHireCard`, `StaffAwayCard`).

## Rules for every dashboard

- **One page per role.** Only admin gets three views, as tabs under the title: Today
  (productivity), Analytics (money), Workforce (people). Money appears only on Analytics.
- **Each number appears once** on a page. The current dashboard repeats hours, revenue, approvals and
  tasks 2–3 times (an older stat grid under newer panels); the rebuild removes that.
- **Lists are capped at about 5 rows**, sorted by urgency, with "View all N →" to the full list.
- **Empty queues collapse**: no rows of zero cards. An empty queue folds into a single
  "Nothing else is waiting" line.
- **Every row has its next action** (Submit, Send, Fill, Promote, Mark paid…), linking to the real
  page or action.
- **Cards in the same row are the same height**, with the footer link pinned to the bottom.
- **Comparisons are like for like**: compare with the same point last month, never a part month
  with a whole month.
- **Pipelines highlight the step this role acts on.**
- Header: a greeting, the scope ("All 30 properties" / "Your 9 properties"), and only the one or two
  actions that role starts from the dashboard. The sidebar already covers navigation.
- Recent activity hides sign-ins and groups repeats.

## Per role

| File | Role | Sections, top to bottom |
|---|---|---|
| `Admin-Today` | admin, super admin | Alert banner (only when broken) · Hours on the way to invoices pipeline + "Who to chase" (awaiting PM approval, oldest first) · Needs a decision (one list across staffing requests, pay increases, terminations, time off, applications) + Expiring in 30 days (contracts, certifications) · Coming up next 14 days (placements starting/ending, staff away, postings closing) + Property setup gaps |
| `Admin-Analytics` | admin | Billed / Collected / Still owed / Gross margin vs same point last month · Revenue and payouts by month + Who owes what (aging buckets, top late properties) · Properties this month (sort: billed, lowest margin, most overtime) + Hours per week with overtime share |
| `Admin-Workforce` | admin | Active contractors, on the clock now, open staffing requests, onboarding pending · Contractors by property (+N needed) + Hiring (applied, in review, placed, open postings) · Recruiters this week (properties, contractors, visits, visits left open, timesheets not sent) + Close to direct hire · Staff away + Property expenses (placeholder) |
| `Recruiter` | recruiter (own properties only) | Actions: Check in at a property, New work order · Last week's timesheets per property (open punches → Fix, else Submit) · Waiting on you (staffing → Place, pay increase → Review, invoice → Send, applications, onboarding) + Waiting on property managers · Expiring in 30 days, Close to direct hire, Visits (properties not visited lately) |
| `Payroll` | payroll | Actions: Upload hours, Export payouts · This pay run (gross pay + hour buckets; deductions & incentives; contractors still unapproved) · Check before paying (big OT, long shifts, hand-edited punches, first week) + Your tasks (final paycheck, QuickBooks removal) · Overdue invoices with Mark paid + Staff time off |
| `HR` | hr | Actions: Invite staff, New job posting · Hiring stages (new, in review [highlighted], ready to promote, became contractors) · Waiting on you (info changes to verify, staff time off) + Missing documents (per-applicant document chips) · Ready to promote (Promote) + Expiring documents · Staff away + Moving up a time-off tier (anniversaries crossing PtoTenure tiers) |
| `OfficeManager` | office manager | Actions: New work order, Upload hours · Last week's hours to invoices (Ready to send highlighted) + invoice list with Send / Add email · Punches to fix (open > 12 h) + Hour imports (missing files, skipped rows) · Supply requests, Low stock, Website inquiries (business vs job seeker) |
| `FrontDesk` | front desk | Header search "Find a contractor or applicant", action Receive a delivery · Supply requests to fill + Deliveries expected (purchase orders) · Terminations · your steps (recover equipment → Mark returned; move file → Done) + Documents to collect + Low stock |

## Data notes for the build

- Most cards come from existing data: timesheets/approvals, invoices (paid_at, due_date), workflows
  (staffing requests, pay increases, terminations, time off, info changes, supply requests), contracts
  (`expiration_date`), field visits, job postings/applications, the onboarding checklist, PTO tenure
  tiers, inventory (`reorder_threshold`, purchase orders), contact inquiries, time entries (open
  punches, `was_updated`).
- Reuse `PropertyAttention` for property setup gaps, but leave out stalled weeks (they're in the
  pipeline).
- **Not in the app yet:** certifications (no model, new or legacy). Expiry alerts need a new
  feature. Property expenses exist only in the legacy app (`property_expenses`).
- Admin Analytics reads every money figure (headline and monthly chart) straight from frozen invoices,
  so they agree. `ReportMonthlyRevenue` is no longer used by the dashboard; the Reports pages still read it.
- Known bug to fix separately: the payouts report omits holiday hours, but its Total Pay includes
  holiday pay (`ReportController::payoutsData`).
- No approval reminders exist for property managers; don't imply them in copy.
- Job postings have no closing date, so "postings closing" was left out of Admin Today's Coming up.
  Certifications are left out of Expiring until the feature exists.
