# Permissions Matrix

| Field | Value |
|---|---|
| Status | Draft — to be reviewed before seeder is finalized |
| Last updated | 2026-05-21 |
| Owner | Product + Engineering |

This is the **canonical role × capability matrix**. The Spatie seeder generates from this table. UI gating, policy checks, and middleware all key off these permissions.

## Reading this table

- ✅ = permission granted by default to the role
- ⚪ = permission NOT granted; can be added per-user if needed (rare)
- — = not applicable to this role

When a permission needs property-level scoping (e.g. a recruiter can edit *their* properties, not all), the policy enforces the scope on top of the permission check.

**About `super_admin` vs `admin` columns:** in v1 they're identical (both ✅ on everything except where explicitly different). Future devolution will narrow `admin` over time (see ADR-0013). The seeder still seeds them as separate roles from day one.

## Property Bible

| Permission | super_admin | admin | office_manager | front_desk | hr | payroll | recruiter | w2_employee | property_manager | contractor |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `bible.properties.view` | ✅ | ✅ | ✅ | ⚪ | ✅ | ✅ | ✅ (own) | ⚪ | ✅ (own) | ⚪ |
| `bible.properties.edit` | ✅ | ✅ | ✅ | ⚪ | ⚪ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ |
| `bible.departments.view` | ✅ | ✅ | ✅ | ⚪ | ✅ | ✅ | ✅ (own) | ⚪ | ✅ (own) | ⚪ |
| `bible.departments.edit` | ✅ | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ✅ (own) | ⚪ | ⚪ | ⚪ |
| `bible.positions.view` | ✅ | ✅ | ✅ | ⚪ | ✅ | ✅ | ✅ (own) | ⚪ | ✅ (own) | ⚪ |
| `bible.positions.edit` | ✅ | ✅ | ✅ | ⚪ | ⚪ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ |
| `bible.rates.view` | ✅ | ✅ | ✅ | ⚪ | ⚪ | ✅ | ✅ (own) | ⚪ | ⚪ | ⚪ |
| `bible.rates.edit` | ✅ | ✅ | ✅ | ⚪ | ⚪ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ |
| `bible.contracts.view` | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ |
| `bible.contracts.edit` | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ |
| `bible.contracts.download` | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ |

**Contract restriction:** Only Ownership (`admin`, `super_admin`) and Payroll can view, edit, or download contracts. This is a hard rule.

## People

| Permission | super_admin | admin | office_manager | front_desk | hr | payroll | recruiter | w2_employee | property_manager | contractor |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `people.applicants.view` | ✅ | ✅ | ✅ | ✅ | ✅ | ⚪ | ✅ (own) | ⚪ | ⚪ | ⚪ |
| `people.applicants.edit` | ✅ | ✅ | ✅ | ⚪ | ✅ | ⚪ | ✅ (own) | ⚪ | ⚪ | ⚪ |
| `people.applicants.onboarding_checklist.edit` | ✅ | ✅ | ✅ | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| `people.applicants.promote` | ✅ | ✅ | ✅ | ⚪ | ✅ | ⚪ | ✅ (own) | ⚪ | ⚪ | ⚪ |
| `people.contractors.view` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ (own) | ⚪ | ✅ (own) | ⚪ |
| `people.contractors.edit` | ✅ | ✅ | ✅ | ⚪ | ✅ | ⚪ | ✅ (own) | ⚪ | ⚪ | ⚪ |
| `people.contractors.terminate` | ✅ | ✅ | ⚪ | ⚪ | ✅ | ⚪ | ✅ (own) | ⚪ | ⚪ | ⚪ |
| `people.contractors.transfer` | ✅ | ✅ | ⚪ | ⚪ | ✅ | ⚪ | ✅ (own) | ⚪ | ⚪ | ⚪ |
| `people.contractors.set_external_id` | ✅ | ✅ | ✅ | ⚪ | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ |
| `people.staff.view` | ✅ | ✅ | ✅ | ⚪ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| `people.staff.edit` | ✅ | ✅ | ✅ | ⚪ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| `people.own_profile.view` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `people.own_profile.request_change` | ⚪ | ⚪ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ⚪ | ✅ |

> Front Desk has view access on applicants and contractors so they can identify who they're handing items to. They edit *only* the onboarding checklist on applicants (not the broader profile).

## Recruiting (Phase 08b-ii)

| Permission | super_admin | admin | office_manager | front_desk | hr | payroll | recruiter | w2_employee | property_manager | contractor |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `job_postings.manage` | ✅ | ✅ | ✅ | ⚪ | ✅ | ⚪ | ✅ | ⚪ | ⚪ | ⚪ |

> One permission covers the posting lifecycle (create/edit/publish/close/delete). Waiving onboarding-checklist items is additionally restricted to hr/admin/super_admin roles (people-lifecycle.md); promotion reversal is promoter-or-super_admin (policy-enforced).

## Work orders

| Permission | super_admin | admin | office_manager | front_desk | hr | payroll | recruiter | w2_employee | property_manager | contractor |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `work_orders.view` | ✅ | ✅ | ✅ | ⚪ | ✅ | ✅ | ✅ (own) | ⚪ | ✅ (own) | ✅ (self) |
| `work_orders.create` | ✅ | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ✅ (own) | ⚪ | ⚪ | ⚪ |
| `work_orders.edit` | ✅ | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ✅ (own) | ⚪ | ⚪ | ⚪ |
| `work_orders.close` | ✅ | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ✅ (own) | ⚪ | ⚪ | ⚪ |

## Time tracking

| Permission | super_admin | admin | office_manager | front_desk | hr | payroll | recruiter | w2_employee | property_manager | contractor |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `time_entries.view` | ✅ | ✅ | ✅ | ⚪ | ⚪ | ✅ | ✅ (own) | ⚪ | ✅ (own) | ✅ (self) |
| `time_entries.create_manual` | ✅ | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ✅ (own) | ⚪ | ⚪ | ⚪ |
| `time_entries.edit` | ✅ | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ✅ (own, period open) | ⚪ | ⚪ | ⚪ |
| `time_entries.delete` | ✅ | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ✅ (own, period open) | ⚪ | ⚪ | ⚪ |
| `time_entries.add_adjustment` | ✅ | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ✅ (own, period open) | ⚪ | ⚪ | ⚪ |
| `time_entries.qr_clock_in` (per ADR-0017) | n/a | n/a | n/a | n/a | n/a | n/a | n/a | n/a | n/a | n/a — public flow, phone+GPS+selfie are the credential |

## Field visits (recruiter accountability — per ADR-0017)

| Permission | super_admin | admin | office_manager | front_desk | hr | payroll | recruiter | w2_employee | property_manager | contractor |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `field_visits.create` | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ✅ | ⚪ | ⚪ | ⚪ |
| `field_visits.close_own` | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ✅ | ⚪ | ⚪ | ⚪ |
| `field_visits.view_own` | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ✅ | ⚪ | ⚪ | ⚪ |
| `field_visits.view_all` | ✅ | ✅ | ✅ | ⚪ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| `field_visits.view_by_property` | ✅ | ✅ | ✅ | ⚪ | ✅ | ⚪ | ⚪ | ⚪ | ✅ (own property) | ⚪ |
| `field_visits.manual_edit` | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |

## Timesheets

| Permission | super_admin | admin | office_manager | front_desk | hr | payroll | recruiter | w2_employee | property_manager | contractor |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `timesheets.view_live` | ✅ | ✅ | ✅ | ⚪ | ⚪ | ✅ | ✅ (own) | ⚪ | ✅ (own, RO) | ⚪ |
| `timesheets.view_history` | ✅ | ✅ | ✅ | ⚪ | ⚪ | ✅ | ✅ (own) | ⚪ | ✅ (own) | ⚪ |
| `timesheets.submit_for_approval` | ✅ | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ✅ (own) | ⚪ | ⚪ | ⚪ |
| `timesheets.approve` | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ✅ (own) | ⚪ |
| `timesheets.decline` | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ✅ (own) | ⚪ |
| `timesheets.export` | ✅ | ✅ | ✅ | ⚪ | ⚪ | ✅ | ✅ (own) | ⚪ | ✅ (own) | ⚪ |

## Imports

| Permission | super_admin | admin | office_manager | front_desk | hr | payroll | recruiter | w2_employee | property_manager | contractor |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `imports.upload` | ✅ | ✅ | ✅ | ⚪ | ⚪ | ✅ | ✅ (own) | ⚪ | ⚪ | ⚪ |
| `imports.commit` | ✅ | ✅ | ✅ | ⚪ | ⚪ | ✅ | ✅ (own) | ⚪ | ⚪ | ⚪ |
| `imports.rollback` | ✅ | ✅ | ✅ | ⚪ | ⚪ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ |

## Invoices

| Permission | super_admin | admin | office_manager | front_desk | hr | payroll | recruiter | w2_employee | property_manager | contractor |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `invoices.view` | ✅ | ✅ | ✅ | ⚪ | ⚪ | ✅ | ✅ (own) | ⚪ | ✅ (own) | ⚪ |
| `invoices.send` | ✅ | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ✅ (own) | ⚪ | ⚪ | ⚪ |
| `invoices.void` | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ |
| `invoices.export` | ✅ | ✅ | ✅ | ⚪ | ⚪ | ✅ | ✅ (own) | ⚪ | ✅ (own) | ⚪ |

## Workflows

| Permission | super_admin | admin | office_manager | front_desk | hr | payroll | recruiter | w2_employee | property_manager | contractor |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `workflows.pto.initiate` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ⚪ | ⚪ |
| `workflows.pto.approve` (per ADR-0016) | ✅ | ✅ | ⚪ | ⚪ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| `workflows.pto.cancel_own` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ⚪ | ⚪ |
| `workflows.pto.cancel_others` | ✅ | ✅ | ⚪ | ⚪ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| `pto.balances.view_all` | ✅ | ✅ | ⚪ | ⚪ | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ |
| `pto.balances.adjust_manual` | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| `pto.balances.view_own` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ⚪ | ⚪ |
| `workflows.termination.initiate` | ✅ | ✅ | ⚪ | ⚪ | ✅ | ⚪ | ✅ (own) | ⚪ | ⚪ | ⚪ |
| `workflows.termination.physical_tasks` | ✅ | ✅ | ✅ | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| `workflows.termination.payroll_tasks` (per ADR-0018) | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ |
| `workflows.termination.cancel` (per ADR-0018) | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| `termination_records.view` | ✅ | ✅ | ⚪ | ⚪ | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ |
| `workflows.transfer.initiate` (per ADR-0019) | ✅ | ✅ | ⚪ | ⚪ | ✅ | ⚪ | ✅ (own) | ⚪ | ⚪ | ⚪ |
| `workflows.transfer.assign_different_recruiter_same_property` (per ADR-0019) | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| `workflows.transfer.cancel` (per ADR-0019) | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| `workflows.temporary_assignment.initiate` (per ADR-0019) | ✅ | ✅ | ⚪ | ⚪ | ✅ | ⚪ | ✅ (own — home recruiter) | ⚪ | ⚪ | ⚪ |
| `workflows.temporary_assignment.cancel` (per ADR-0019) | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| `workflows.supply_request.initiate` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ⚪ | ⚪ |
| `workflows.supply_request.fulfill` | ✅ | ✅ | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| `workflows.supply_request.approve_new_item` | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| `workflows.more_staff.initiate` (per ADR-0021) | ✅ | ✅ | ⚪ | ⚪ | ✅ | ⚪ | ✅ (own property) | ⚪ | ✅ (own property) | ⚪ |
| `workflows.more_staff.fulfill` (link WOs to requests) | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ✅ (own property) | ⚪ | ⚪ | ⚪ |
| `workflows.more_staff.decline` (per ADR-0021) | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ✅ (own property) | ⚪ | ⚪ | ⚪ |
| `workflows.more_staff.cancel_own` (per ADR-0021) | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ✅ (own request) | ⚪ | ✅ (own request) | ⚪ |
| `workflows.more_staff.cancel_others` (per ADR-0021) | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| `workflows.pay_increase.initiate` (per ADR-0020) | ✅ | ✅ | ⚪ | ⚪ | ✅ | ⚪ | ✅ (own contractor) | ⚪ | ✅ (own property) | ⚪ |
| `workflows.pay_increase.approve` (per ADR-0020) | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ✅ (own contractor — for PM-initiated) | ⚪ | ⚪ | ⚪ |
| `workflows.pay_increase.cancel_own` (per ADR-0020) | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ✅ (own request) | ⚪ | ✅ (own request) | ⚪ |
| `workflows.pay_increase.cancel_others` (per ADR-0020) | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| `workflows.recruiter_transfer.execute` | ✅ | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| `workflows.change_personal_info.verify` | ✅ | ✅ | ⚪ | ⚪ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |

> **New-item approval routes to Admin only.** Per ADR-0013, the previous $50-threshold approval rule is removed. All new-item supply requests require `workflows.supply_request.approve_new_item`, granted only to Admin and Super Admin.

> **PTO approval restricted to Admin and HR only** (per ADR-0016). Office Manager, Payroll, Recruiter, and others do NOT approve PTO. HR has a **self-approval guardrail**: HR cannot approve their own PTO request — those route to Admin (and Super Admin) only. Admin and Super Admin can self-approve; the `is_self_approved` flag is set for audit visibility.

## Knowledge base

| Permission | super_admin | admin | office_manager | front_desk | hr | payroll | recruiter | w2_employee | property_manager | contractor |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `kb.articles.view` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ⚪ | ✅ (role-gated) |
| `kb.articles.create` | ✅ | ✅ | ✅ | ⚪ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| `kb.articles.edit` | ✅ | ✅ | ✅ | ⚪ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| `kb.articles.publish` | ✅ | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| `kb.articles.delete` | ✅ | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |

## Inventory

Note: per ADR-0012, requesters do **not** see stock levels when submitting a request. They have `workflows.supply_request.initiate` only, not `inventory.items.view`. The full Browse Inventory page is restricted to admin, super_admin, office_manager, and front_desk.

| Permission | super_admin | admin | office_manager | front_desk | hr | payroll | recruiter | w2_employee | property_manager | contractor |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `inventory.items.view` | ✅ | ✅ | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| `inventory.items.create` | ✅ | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| `inventory.items.edit` | ✅ | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| `inventory.items.deactivate` | ✅ | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| `inventory.categories.manage` | ✅ | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| `inventory.stock.receive_direct` | ✅ | ✅ | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| `inventory.stock.adjust` | ✅ | ✅ | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| `inventory.stock.manual_out` (per ADR-0015) | ✅ | ✅ | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| `inventory.stock.return_to_stock` | ✅ | ✅ | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| `inventory.purchase_orders.view` | ✅ | ✅ | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| `inventory.purchase_orders.create` | ✅ | ✅ | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| `inventory.purchase_orders.receive` | ✅ | ✅ | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| `inventory.equipment.return` | ✅ | ✅ | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| `inventory.equipment.view_assignments` | ✅ | ✅ | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |

## Reports

| Permission | super_admin | admin | office_manager | front_desk | hr | payroll | recruiter | w2_employee | property_manager | contractor |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `reports.financial.view` | ✅ | ✅ | ✅ | ⚪ | ⚪ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ |
| `reports.operational.view` | ✅ | ✅ | ✅ | ⚪ | ✅ | ⚪ | ✅ (own) | ⚪ | ✅ (own) | ⚪ |
| `reports.payroll.view` | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ |

## Audit & admin

| Permission | super_admin | admin | office_manager | front_desk | hr | payroll | recruiter | w2_employee | property_manager | contractor |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `audit.activity_log.view` | ✅ | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| `audit.legal_hold.set` | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| `audit.legal_hold.clear` | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| `admin.impersonate` | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| `admin.users.create` | ✅ | ✅ | ✅ | ⚪ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |
| `admin.roles.assign` | ✅ | ✅ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ | ⚪ |

> Several admin/audit capabilities will likely move out of the `admin` column over time per ADR-0013's planned devolution. Specifically: `admin.impersonate`, `audit.activity_log.view`, and `audit.legal_hold.*`. Not changing yet — happens with a new ADR when ready.

## "Own" scoping rules

When a permission row says "✅ (own)", the policy enforces:

| Resource | "Own" means |
|---|---|
| Property | The recruiter is assigned to this property (or the PM works at this property) |
| Contractor | The contractor is currently assigned (via active work order) to a property the user is assigned to |
| Work order | The WO's property is one the user is assigned to |
| Time entry / timesheet | The time entry's work order's property is one the user is assigned to |
| Invoice | The invoice's property is one the user is assigned to |
| Workflow | The workflow was either initiated by, or routes to, the user |

These rules live in policy classes (`app/Policies/`).

## Related

- `10-architecture/identity-and-auth.md` — how authentication works
- `10-architecture/domain-routing.md` — which roles are allowed on which domain
- `20-domain/people-lifecycle.md` — what status enables what role
- `20-domain/workflows.md` — workflow-specific permission patterns
- ADR-0013 — admin and front_desk roles introduction
