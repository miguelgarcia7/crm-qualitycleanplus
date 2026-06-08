declare global {
  interface Window {
    HSStaticMethods?: { autoInit: () => void }
    HSOverlay?: { open: (selector: string) => void }
  }
}

let initialized = false
export const preline = {
  init: () => {
    let debounceRef: ReturnType<typeof setTimeout> | null = null
    const refreshPreline = () => {
      if (typeof window.HSStaticMethods?.autoInit === 'function') {
        window.HSStaticMethods.autoInit()
      }
    }
    if (initialized || typeof window === 'undefined') return
    initialized = true
    setTimeout(async () => {
      await import('preline/dist')
      refreshPreline()
    }, 200)
    const observer = new MutationObserver(() => {
      if (debounceRef) clearTimeout(debounceRef)
      debounceRef = setTimeout(refreshPreline, 400)
    })

    observer.observe(document.body, { childList: true, subtree: true })
  },

  openModal: (selector: string) => {
    window.HSOverlay?.open(selector)
  },
}
