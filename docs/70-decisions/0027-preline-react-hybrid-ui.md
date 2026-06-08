# ADR-0027: Preline in a React/Inertia App — Hybrid Component Policy

| Field | Value |
|---|---|
| Status | Accepted |
| Decided | 2026-06-07 |
| Owner | Engineering |
| Refines | ADR-0022 (frontend stack) |
| Supersedes | — |
| Superseded by | — |

## Context

The Paces/Minute theme (ADR-0022, the `./design-reference` library) ships its
interactive components as **Preline UI** — a *vanilla-JS + Tailwind* library.
Components are driven by HTML `data-hs-*` attributes and a global initializer
(`HSStaticMethods.autoInit()`); there is **no React build of Preline** (the npm
package is the same vanilla one for every framework). Preline is actively
maintained (v4.2.0 at time of writing) — this is not a version or abandonment
problem.

Our app is **React + Inertia** (a SPA). A vanilla, DOM-driven library has a
genuine impedance mismatch with React:

- Preline mutates the DOM imperatively, competing with React's virtual DOM.
- Its widgets must be **re-initialised after every Inertia navigation**, because
  the page DOM is swapped without a full reload.
- Open/close and value state live in the DOM, not React — so components that must
  be **driven by React state or feed a form** (`useForm`) require manual
  event-bridging, suffer init/render timing races, and are hard to test.

Phase 03 already chose React-controlled modals for the timesheet grid for these
reasons; Phase 04 followed suit. We want a single, documented rule so this isn't
re-litigated each phase.

## Decision

**Use Preline for stateless presentation chrome; use React-controlled components
for anything bound to React state or a form.**

### Use Preline (data-attribute + `autoInit`)

Self-contained widgets whose interaction state can live in the DOM and whose
content is static at render time:

- Tabs (static panels), dropdown menus, accordions, collapse, tooltips, popovers.

These are on-brand, require no extra dependency, and just need `autoInit()` to run
after each navigation.

### Use React-controlled (or a small headless helper)

Components coupled to React state, data, or form submission:

- **Modals/dialogs** (especially those tied to a form) — controlled via `useState`,
  styled with the theme's `card` classes.
- **Value-bound selects / comboboxes** whose value flows into `useForm`.
- **Date pickers** bound to a field — reach for a focused headless library
  (e.g. `react-day-picker`) **only when a real field needs one**, per the project's
  per-phase dependency discipline. Do not add a broad UI framework speculatively.
- Anything rendering React-managed dynamic content inside the widget.

### Initialization lifecycle

`resources/js/admin/utils/preline.ts` loads Preline once and re-runs
`HSStaticMethods.autoInit()` on Inertia's `router.on('navigate')` (after paint,
via `requestAnimationFrame`) — replacing the previous body-wide `MutationObserver`,
which re-init'd unpredictably. A `preline.refresh()` escape hatch re-inits after
rendering Preline markup dynamically.

### Version: stay on 4.0.1 (for now)

Preline is **pinned to an exact `4.0.1`**. The 4.2.x line ships a strict package
`exports` map that omits the CSS specifiers our Tailwind v4 stylesheet relies on
(`@import "preline/variants.css"`), which breaks `vite build`. Until that packaging
stabilises (or we migrate the CSS to whatever 4.2's exports expect), we hold at
4.0.1 and pin it exactly so a caret range can't pull in the broken minor.

## Consequences

### Positive

- Clear, settled rule; no per-component debate.
- Preline's strengths (tabs/dropdowns/tooltips) are available and reliable across
  navigation; React's strengths (state, forms, testability) are used where they
  matter.
- No speculative dependency; headless helpers are added only when a field needs one.

### Negative / cost

- Two patterns coexist (Preline chrome + React-controlled), so contributors must
  know which camp a component falls in — hence this ADR.
- Modals diverge from the reference library's Preline `hs-overlay`; this is
  intentional and limited to state-bound components.

## Related

- ADR-0022 (React/Inertia/Fortify stack), ADR-0024 (per-surface bundles)
- `resources/js/admin/utils/preline.ts`
- `MEMORY` note: reuse the Paces/Minute theme; don't recreate CSS/components
