# Workflows

| Field | Value |
|---|---|
| Status | Accepted |
| Last updated | 2026-05-21 |
| Owner | Product + Engineering |

A **workflow** is a structured request with one or more approval/action steps. The legacy systems handle each business process (PTO, termination, transfer) one-off. The new system uses a **single generalized workflow engine** that all processes ride on.

## Why one engine

Every workflow has the same shape:

- An initiator (someone starts it)
- A subject (what or who it's about)
- Zero or more approvers (sequential or parallel)
- One or more action steps (what happens on completion)
- An audit trail (who did what, when)
- A notification surface (who needs to know)

Building one engine and porting all workflows onto it means:

- One activity log pattern
- One dashboard widget pattern ("my pending tasks")
- One audit shape across all processes
- One notification surface
- New workflows added in days, not weeks

## Engine shape

```
workflow_definitions  (seeded; identifies workflow types)
  - id, slug (e.g. "termination", "uniform_request"), name, description
  - steps_json (the step sequence — see below)

workflows  (one row per instance)
  - id
  - definition_id (FK)
  - subject_type, subject_id (polymorphic — e.g. Person, Property, TimeEntry)
  - initiator_id (FK to people)
  - status: enum (pending | in_progress | completed | cancelled | rejected)
  - current_step_index
  - data (jsonb — payload values specific to this workflow type)
  - completed_at, completed_by
  - created_at, updated_at

workflow_steps  (executions of each step in a workflow instance)
  - id
  - workflow_id (FK)
  - step_index (ordering)
  - step_type: enum (approval | action | notification)
  - assigned_to (FK to people, nullable — roles can also be assigned)
  - assigned_role (string, nullable — for "any HR can do this")
  - status: enum (pending | done | skipped | rejected)
  - completed_at, completed_by
  - notes (text)
  - created_at, updated_at
```

## Anatomy of a definition

Each `workflow_definitions.steps_json` describes the steps. Example for `termination`:

```json
[
  {
    "type": "approval",
    "name": "Recruiter initiates and submits termination details",
    "assigned_role": "recruiter",
    "form_fields": ["reason", "rehireable", "notes"]
  },
  {
    "type": "action",
    "name": "Mark contractor inactive on roster",
    "actor": "system",
    "effect": "remove_from_active_roster"
  },
  {
    "type": "action",
    "name": "Receptionist moves physical file from active cabinet to HR retention",
    "assigned_role": "receptionist",
    "completion_marks_status": "terminated"
  },
  {
    "type": "action",
    "name": "Payroll removes from QuickBooks after final paycheck",
    "assigned_role": "payroll",
    "delay_days": 7
  }
]
```

The engine reads the definition, walks the steps, dispatches notifications, and creates dashboard items for each assignee.

## Catalog of workflows (v1)

| Workflow | Initiator | Steps | Notes |
|---|---|---|---|
| `pto_request` | W-2 employee | 1. Initiate (form), 2. Approval (Admin / Super Admin / HR; HR cannot self-approve) | Tier-based accrual; deduct on submission; no rollover; no termination payout; soft warning for notice-period vacation. See ADR-0016 and `20-domain/pto.md`. |
| `termination` | Recruiter (own contractors), HR (anyone), Admin, Super Admin | 1. Initialize (auto: status → `pending_termination` if effective_date ≤ today; else scheduled), 2. Remove from rosters + close WOs + auto-close clock-in (auto), 3. Auto-cancel pending workflows by subject (auto), 4. **Front Desk** Recover Equipment task, 5. **Front Desk** Move file to HR retention (completion flips status → `terminated`), 6. **Payroll** Process final paycheck (consolidates contractor_charge_schedules per ADR-0014; forfeits PTO per ADR-0016), 7. **Payroll** Remove from QuickBooks (7-day delay) | See `40-flows/termination.md` and ADR-0018. Same workflow handles contractor + W-2 with conditional steps. Form captures: effective_date (forward/today/backward), termination_type, reason_category, notes, rehireable. Cancellable by super_admin before file-move step. |
| `transfer` (permanent) | Recruiter (own), HR, Admin | 1. Initiate (form: new property, new recruiter, new position, rates), 2. Auto-close any open clock-in, 3. Close old WO + open new WO, 4. Update `primary_recruiter_id` if recruiter changes, 5. Remap charge schedule entries, 6. Reassign pending pay-increase requests | Old timesheet stays with old property. Handles property change AND same-property position change. See ADR-0019 and `40-flows/transfer.md`. |
| `temporary_assignment` | Home recruiter, HR, Admin | 1. Initiate (form: new property, start/end dates, position, rates), 2. Home WO stays open, 3. Create temp WO with required `end_date`, 4. `primary_recruiter_id` UNCHANGED, 5. Notify receiving recruiter, 6. Daily job auto-closes at end_date | Contractor stays under home recruiter for roster count; works at temp property in the window. See ADR-0019 and `40-flows/temporary-assignment.md`. |
| `supply_request` | Anyone with permission (recruiter, hr, office_manager, front_desk, etc.) | 1. Initiate (form, dynamic by category), 2. Front Desk fulfills existing-item path OR Admin approves new item → Front Desk creates PO → receive → fulfill | Single workflow handles Office Supplies, Uniforms, Equipment. Uniform deductions auto-created on completion. Approval is Admin-only (no $50 threshold). See ADR-0012, ADR-0013. |
| `more_staff_request` | Property manager (own property), Recruiter (own property), HR, Admin | 1. PM submits form (position, quantity, by-date, urgency, reason), 2. Lands in recruiter's "Talent Needs" queue, 3. Recruiter places contractors (new hire / transfer / temp) and links each new WO to the request (`more_staff_request_id` FK), 4. quantity_fulfilled auto-tracks; status auto-transitions submitted → in_progress → fulfilled. Recruiter can decline with reason; PM can cancel own request. By-date is informational (overdue flag, no auto-close). See ADR-0021 and `40-flows/more-staff-request.md`. |
| `pay_increase` | Property manager (own contractor at own property), Recruiter (own contractor), HR, Admin | 1. PM submits requested **increase amount** in QC Minute (not target rate; PM never sees pay rate), 2. Recruiter reviews in Back Office, sets actual new pay + bill rates (defaults pass-through; bill rate must ≥ current + PM's requested increase), picks effective period (next or +1), 3. On approval: close old WO at period end, open new WO at next period start. Recruiter can self-initiate without PM step. See ADR-0020 and `40-flows/pay-increase.md`. |
| `recruiter_to_property_transfer` | Super admin, Admin, Office Manager | 1. Initiate (form: from recruiter, to recruiter, optional per-property override), 2. Reassign all property assignments (auto), 3. Update `primary_recruiter_id` on all affected contractors (auto), 4. Reassign pending workflows initiated by/for affected parties to new recruiter (auto), 5. Notify both recruiters | Bulk operation. Used when recruiter A leaves/changes role; all their properties + contractors move to recruiter B. Detailed flow doc deferred to parking-lot — see ADR-0019 for related primary_recruiter_id mechanics. |
| `change_personal_info` | Contractor (self), W-2 employee (self), Recruiter / HR / Admin (on behalf of) | 1. Initiate (form: field to change + new value + reason), 2. HR verifies and applies (or rejects with reason) | Prevents social engineering of contact-info change (e.g. fraudulent direct-deposit changes). Common fields: phone, address, email, emergency contact, bank info. Critical PII fields (SSN, DOB) require additional verification. Detailed flow doc pending. |

More can be added by defining new entries in `workflow_definitions`.

## Step types

| Type | Behavior |
|---|---|
| `approval` | Assignee approves or rejects with reason. Approval advances. Rejection cancels the workflow (or routes back, depending on definition). |
| `action` | Either a system action (executed automatically) or a human task (assignee marks done). |
| `notification` | Informational; sent to a person or role without requiring action. |

## Assignment patterns

A step can be assigned by:

- **Specific person:** `assigned_to = some person_id`
- **Role:** `assigned_role = 'receptionist'` (anyone with that role can pick it up)
- **Relationship:** "the recruiter of the subject's property" (resolved at step-creation time)

When assigned by role, the task appears in everyone-with-role's queue. First person to act claims it.

## Notifications

Every step assignment triggers a notification (mail + in-app + broadcast) to the assignee. The notification links directly to the workflow detail page where they can act.

## Dashboards

Every role's dashboard surfaces:

- "Tasks assigned to me" (pending steps in workflows they own)
- "Workflows I initiated" (status check)
- Domain-specific aggregations (e.g. recruiter sees "open uniform requests" specifically)

## Audit

Every state transition writes an activity log row tagged with the workflow ID. The workflow detail page renders this as a timeline.

## Rejection / cancellation

A workflow can be:

- **Cancelled** by the initiator (before any approval) or super_admin (any time)
- **Rejected** at an approval step by the approver (with reason)

A rejected workflow is final — the initiator must start over if they still need the action.

## Multi-step approval (engine supports, day-one workflows don't use)

The engine handles multiple sequential approval steps natively. None of our day-one workflows need it, but the capability is there:

```json
[
  { "type": "approval", "assigned_role": "recruiter" },
  { "type": "approval", "assigned_role": "office_manager" },
  { "type": "approval", "assigned_role": "ownership" },
  { "type": "action", "actor": "system", "effect": "..." }
]
```

Each approval must complete before the next is created. Rejection at any step terminates.

## SLAs and aging

Workflows can have optional `sla_hours` per step. Steps exceeding their SLA appear on dashboards with aging indicators. No automatic escalation in v1 — just visibility.

## Permissions

Workflow-level permissions are per-definition (e.g. `workflows.pto.initiate`, `workflows.pto.approve`). See `10-architecture/permissions-matrix.md` for the full list.

Step-level: assignment determines who can act on a specific step.

## Related

- ADR-0011 — More-staff workflow creates a search task
- `10-architecture/permissions-matrix.md` — workflow permissions
- `40-flows/termination.md`
- `40-flows/transfer.md`
- `40-flows/supply-request.md`
- `40-flows/pay-increase.md`
- `40-flows/more-staff-request.md`
- `40-flows/pto-request.md`
- `30-schema/workflow-tables.md` (TBD)
