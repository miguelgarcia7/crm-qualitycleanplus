import { router } from '@inertiajs/react'

declare global {
  interface Window {
    HSStaticMethods?: { autoInit: () => void }
    HSOverlay?: { open: (selector: string) => void }
  }
}

let initialized = false

/** Re-bind Preline's data-attribute components to the current DOM. */
const autoInit = (): void => {
  if (typeof window !== 'undefined' && typeof window.HSStaticMethods?.autoInit === 'function') {
    window.HSStaticMethods.autoInit()
  }
}

/*
| Preline is a vanilla-JS library; in this React/Inertia SPA we use it only for
| "presentation chrome" (tabs, dropdowns, accordions, tooltips). It must be
| re-initialised after each Inertia navigation so widgets on the freshly-rendered
| page bind. Form-bound components (modals, value-bound selects, date pickers)
| are React-controlled instead — see ADR-0027.
*/
export const preline = {
  init: (): void => {
    if (initialized || typeof window === 'undefined') return
    initialized = true

    import('preline/dist').then(() => {
      autoInit()
      // Re-init after every Inertia visit (incl. back/forward), once the new
      // page has painted.
      router.on('navigate', () => window.requestAnimationFrame(autoInit))
    })
  },

  /** Manually re-init after rendering Preline markup dynamically (escape hatch). */
  refresh: (): void => {
    window.requestAnimationFrame(autoInit)
  },

  openModal: (selector: string): void => {
    window.HSOverlay?.open(selector)
  },
}
