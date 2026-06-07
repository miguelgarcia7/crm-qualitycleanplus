# ~~PTO System for W-2 Employees~~ (resolved)

| Field | Value |
|---|---|
| Status | RESOLVED — see ADR-0016 |
| Last updated | 2026-05-21 |
| Owner | — |

Resolved in design conversation 2026-05-21. The full PTO policy and system design is now documented in:

- **ADR-0016** — `70-decisions/0016-pto-tier-based-accrual.md` — the policy decision
- **Domain model** — `20-domain/pto.md` — full data model and behavior
- **Flow** — `40-flows/pto-request.md` — step-by-step lifecycle

## Summary of resolutions

| Original open question | Resolution |
|---|---|
| Eligibility | W-2 only, 30-day probation, tier-based by tenure |
| Bucket structure | Keep three (vacation, scheduled, unscheduled); strictly separate pools |
| Accrual rules | Tier-based: 0/0/0 → 20/20/20 → 30/30/30 → 40/40/40 |
| Annual cycle | Hire anniversary (not calendar year) |
| Rollover | None — use it or lose it |
| Growth past 12 months | None — 40/40/40 stays |
| Tier crossing behavior | Top up by delta (not replace) |
| Block insufficient submission | Yes — block at submission time |
| Deduct on submission | Yes — pending hours count against available immediately |
| Approval routing | Admin and HR (plus Super Admin always); HR cannot self-approve |
| Cancellation | Allowed by requester or approver; hours return |
| Notice period vacation | Soft warning, no hard block |
| Termination payout | None — accrued PTO forfeited on separation |
| Automation | Daily job processes tier crossings + anniversaries |
