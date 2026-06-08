# ADR-0026: Workflow Engine — Hybrid Concrete-Action Definitions + Shared Task Surface

| Field | Value |
|---|---|
| Status | Accepted |
| Decided | 2026-06-07 |
| Owner | Engineering |
| Refines | ADR-0025 |
| Supersedes | — |
| Superseded by | — |

## Context

Phase 04 introduces a generalized engine for the back-office approval/task flows
(`20-domain/workflows.md`): termination, transfer, temporary assignment, supply
request, more-staff, pay-increase, change-personal-info (+ PTO, deferred to Phase
08). The roadmap deliverables list `workflow_definitions`, `workflows`,
`workflow_steps` tables and "an engine that walks steps based on definition."

There are two ways to read "definition":

1. **Data-driven** — store each workflow's steps as JSON rows in a
   `workflow_definitions` table and build a generic interpreter that reads the JSON
   at runtime, resolves assignees, executes effects, and advances.
2. **Code-driven** — express each workflow as a concrete PHP class that declares
   its steps and side-effects, with the engine providing the shared persistence
   (`workflows`/`workflow_steps`), the unified task inbox, and the
   advance/approve/reject/cancel mechanics.

The roadmap **risk register** explicitly flags *"Workflow engine over-engineering —
build to the current 8 workflows, defer abstractions until the pattern emerges."*
A fully data-driven interpreter is the abstraction that risk warns against: the
effects (close a work order, create a charge schedule, transition a person's
status) are real PHP touching real domain Actions — they cannot live in JSON
without inventing an effect-DSL. JSON definitions also defeat static analysis
(Larastan level 5) and type-safe tests, which the project relies on per increment.

## Decision

**Build a hybrid engine: shared tables + shared task surface, but each workflow is
a concrete PHP `WorkflowDefinition` class in a code registry.**

- **Persistence (shared):** `workflows` (one row per instance — type, polymorphic
  subject, initiator, status, `current_step_index`, `data` json payload) and
  `workflow_steps` (one row per materialized step — type, actor, assignee
  person/role, status, notes, completion stamps). **No `workflow_definitions`
  table** — definitions are code.
- **Definitions (code):** an abstract `App\Domain\Workflows\Definitions\WorkflowDefinition`
  declares `type()`, `steps()`, and lifecycle hooks (`onStart()`,
  `onStepComplete()`). Concrete definitions (e.g. `SupplyRequestDefinition`) live
  in their owning context and are resolved by `WorkflowType` through a small
  registry bound in a service provider.
- **Engine mechanics (shared, reusable):** `StartWorkflow`, `CompleteStep`,
  `RejectStep`, `AdvanceWorkflow` (auto-runs leading/trailing `system` steps),
  `CancelWorkflow` — generic over any definition.
- **Unified task surface (shared):** a *My Tasks* inbox queries `workflow_steps`
  for `status = pending` assigned to the current person **or** one of their roles;
  shared `complete`/`reject` endpoints authorize via `WorkflowPolicy` plus the
  relevant seeded `workflows.*` permission. This delivers the roadmap's "my
  pending tasks" dashboard without a generic interpreter.

Step `actor` is `system` (engine runs the definition's effect automatically) or
`human` (an assignee acts via the inbox). Assignment is by **person**, by **role**
(first eligible actor claims), or by **relationship** (resolved to a person/role at
step materialization, e.g. "the recruiter of the subject's property").

## Consequences

### Positive

- Effects are plain PHP calling existing domain Actions — type-checked, testable,
  Larastan-clean. No effect-DSL to design or maintain.
- The shared `workflows`/`workflow_steps` tables + task inbox still give one
  audit trail and one "my tasks" surface across every workflow.
- Honors the risk-register guidance: abstraction is limited to what the 8
  workflows actually share (persistence, assignment, advancement, inbox).

### Negative / cost

- Adding a workflow means writing a PHP class, not editing a DB row (acceptable —
  workflows are developer-defined, not end-user-authored).
- Runtime re-ordering/branching of steps is bounded by what the definition class
  expresses; complex per-instance branching uses `data` + hook logic rather than
  arbitrary stored graphs. Multi-step sequential approvals and SLA auto-escalation
  are supported by the model but unused until a workflow needs them.

### If we ever need data-driven definitions

The `workflows`/`workflow_steps` schema is already definition-agnostic. A
`workflow_definitions` table + interpreter could be added later as an alternative
`WorkflowDefinition` implementation without reshaping instances — deferred until a
real need (e.g. end-user-authored flows) emerges.

## Related

- `20-domain/workflows.md` — engine + the eight workflows
- `80-plan/phase-04-workflow-inventory.md` — Phase 04a build
- ADR-0025 (`app/Domain` structure), ADR-0012/0014/0015 (inventory + charges)
