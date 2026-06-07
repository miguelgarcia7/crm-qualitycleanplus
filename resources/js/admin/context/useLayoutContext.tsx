import { getSystemTheme, toggleAttribute } from '@/utils/layout'
import { createContext, use, useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import { useSessionStorage } from 'usehooks-ts'

export type LayoutState = {
  skin: string
  theme: string
  orientation: string
  topbarColor: string
  sidenavSize: string
  sidenavColor: string
  sidenavUser: boolean
  width: string
  position: string
  dir: string
}

export type LayoutType = {
  updateSettings: (newSettings: Partial<LayoutState>) => void
  isCustomizerOpen: boolean
  toggleCustomizer: () => void
  reset: () => void
} & LayoutState

const debounce = <T extends (...args: unknown[]) => void>(fn: T, delay: number): ((...args: Parameters<T>) => void) => {
  let timer: ReturnType<typeof setTimeout>
  return (...args: Parameters<T>) => {
    clearTimeout(timer)
    timer = setTimeout(() => {
      fn(...args)
    }, delay)
  }
}

export const showBackdrop = () => {
  const htmlEl = document.documentElement
  const backdropEl = document.createElement('div')
  backdropEl.id = 'custom-backdrop'
  backdropEl.className = 'transition duration fixed inset-0 bg-default-900/50 z-40'
  document.body.appendChild(backdropEl)
  document.body.style.overflow = 'hidden'
  htmlEl.classList.add('sidenav-enable')
  if (window.innerWidth > 767) {
    document.body.style.paddingRight = '15px'
  }
  backdropEl.addEventListener('click', () => {
    hideBackdrop()
  })
}

export const hideBackdrop = () => {
  const htmlEl = document.documentElement
  htmlEl.classList.remove('sidenav-enable')
  const backdropEl = document.getElementById('custom-backdrop')
  if (backdropEl) {
    document.body.removeChild(backdropEl)
    document.body.style.overflow = ''
    document.body.style.paddingRight = ''
  }
}

const INIT_STATE: LayoutState = {
  skin: 'sage',
  theme: 'light',
  orientation: 'vertical',
  sidenavSize: 'on-hover-active',
  sidenavColor: 'dark',
  sidenavUser: false,
  topbarColor: 'light',
  width: 'fluid',
  position: 'fixed',
  dir: 'ltr',
}

const getQueryParams = (state: LayoutState): Partial<LayoutState> => {
  if (typeof window === 'undefined') return {}
  const urlParams = new URLSearchParams(window.location.search)
  const queryParams: Partial<LayoutState> = {}

  Object.keys(state).forEach((key) => {
    const k = key as keyof LayoutState
    const value = urlParams.get(k)
    if (value !== null) {
      if (typeof state[k] === 'boolean') {
        Object.assign(queryParams, { [k]: value === 'true' || value === '1' })
      } else {
        Object.assign(queryParams, { [k]: value })
      }
    }
  })

  return queryParams
}

const LayoutContext = createContext<LayoutType | undefined>(undefined)

export const useLayoutContext = () => {
  const context = use(LayoutContext)
  if (!context) {
    throw new Error('useLayoutContext can only be used within LayoutProvider')
  }
  return context
}

export const LayoutProvider = ({ children }: { children: ReactNode }) => {
  const queryConfig = useMemo(() => getQueryParams(INIT_STATE), [])
  const [settings, setSettings] = useSessionStorage<LayoutState>('__THEME_CONFIG__', { ...INIT_STATE, ...queryConfig })

  const [isCustomizerOpen, setIsCustomizerOpen] = useState(false)

  const updateSettings = useCallback(
    (_newSettings: Partial<LayoutState>) => {
      setSettings((prevSettings) => ({
        ...prevSettings,
        ..._newSettings,
      }))
    },
    [setSettings]
  )

  const toggleCustomizer = useCallback(() => {
    setIsCustomizerOpen((prevValue: boolean) => !prevValue)
  }, [])

  const reset = useCallback(() => {
    setSettings(INIT_STATE)
  }, [setSettings])

  useEffect(() => {
    if (Object.keys(queryConfig).length > 0) {
      updateSettings(queryConfig)
    }
  }, [queryConfig, updateSettings])

  useEffect(() => {
    if (!settings.sidenavSize.includes('on-hover')) hideBackdrop()

    toggleAttribute('data-layout', settings.orientation)
    toggleAttribute('data-sidenav-user', settings.sidenavUser.toString())
    toggleAttribute('data-layout-position', settings.position)
    toggleAttribute('data-topbar-color', settings.topbarColor)
    toggleAttribute('data-menu-color', settings.sidenavColor)
    toggleAttribute('data-theme', settings.theme === 'system' ? getSystemTheme() : settings.theme)
    toggleAttribute('data-skin', settings.skin)
    toggleAttribute('data-sidenav-size', settings.sidenavSize)
    toggleAttribute('data-layout-width', settings.width)
    toggleAttribute('dir', settings.dir)
  }, [settings])

  useEffect(() => {
    const handleResize = () => {
      const width = window.innerWidth

      if (settings.orientation === 'vertical') {
        if (width <= 768) {
          updateSettings({ sidenavSize: 'offcanvas' })
        } else if (width <= 1140 && settings.sidenavSize !== 'offcanvas') {
          updateSettings({ sidenavSize: 'condensed' })
        } else {
          updateSettings({ sidenavSize: INIT_STATE.sidenavSize })
        }
      } else if (settings.orientation === 'horizontal') {
        if (width < 992) {
          updateSettings({ sidenavSize: 'offcanvas' })
        } else {
          updateSettings({ sidenavSize: INIT_STATE.sidenavSize })
        }
      }
    }

    const debouncedResize = debounce(handleResize, 200)

    window.addEventListener('resize', debouncedResize)

    return () => {
      window.removeEventListener('resize', debouncedResize)
    }
  }, [settings.orientation, settings.sidenavSize, updateSettings])

  return (
    <LayoutContext
      value={useMemo(
        () => ({
          ...settings,
          updateSettings,
          isCustomizerOpen,
          toggleCustomizer,
          reset,
        }),
        [settings, updateSettings, isCustomizerOpen, toggleCustomizer, reset]
      )}
    >
      {children}
    </LayoutContext>
  )
}
