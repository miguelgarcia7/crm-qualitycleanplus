import Icon from '@/components/wrappers/Icon'
import { ReactNode, useEffect, useLayoutEffect, useRef, useState } from 'react'

type SidePanelProps = {
  title: ReactNode
  subtitle?: ReactNode
  /** Sits left of the title — an avatar or icon. */
  leading?: ReactNode
  /** A strip under the header, e.g. previous/next navigation. */
  toolbar?: ReactNode
  footer?: ReactNode
  children: ReactNode
  onClose: () => void
  /** Ignore Escape while a dialog is open on top, so one key press closes one layer. */
  escapeDisabled?: boolean
}

/**
 * Right-hand panel for working on one record without leaving the page — the
 * detail behind a dense table cell. React-controlled like ConfirmModal (the
 * reference library's offcanvas is Preline's declarative hs-overlay), and sits
 * one layer below it (z-40) so a confirmation can open on top.
 */
const SidePanel = ({ title, subtitle, leading, toolbar, footer, children, onClose, escapeDisabled = false }: SidePanelProps) => {
  const [shown, setShown] = useState(false)
  const panelRef = useRef<HTMLElement>(null)
  const closeRef = useRef<HTMLButtonElement>(null)

  // Slide in: reading the layout commits the off-screen start position, so the
  // transition runs without waiting on an animation frame (which never comes
  // while the tab is in the background). Focus moves into the panel and goes
  // back to whatever opened it on close.
  useLayoutEffect(() => {
    const opener = document.activeElement as HTMLElement | null
    void panelRef.current?.offsetWidth
    setShown(true)
    // A field inside may already have taken focus (autoFocus on an add form).
    if (!panelRef.current?.contains(document.activeElement)) closeRef.current?.focus()
    return () => opener?.focus?.()
  }, [])

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && !escapeDisabled) onClose()
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [onClose, escapeDisabled])

  return (
    <>
      <div
        className={`fixed inset-0 z-40 bg-black/30 transition-opacity duration-200 motion-reduce:transition-none ${shown ? 'opacity-100' : 'opacity-0'}`}
        onClick={onClose}
      />
      <aside
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby="side-panel-title"
        className={`bg-card fixed inset-y-0 right-0 z-40 flex w-full max-w-md flex-col shadow-2xl transition-transform duration-200 ease-out motion-reduce:transition-none ${shown ? 'translate-x-0' : 'translate-x-full'}`}>
        <div className="border-default-300 flex items-start gap-3 border-b px-5 py-4">
          {leading}
          <div className="min-w-0 flex-1">
            <h4 id="side-panel-title" className="text-default-900 text-base font-semibold">
              {title}
            </h4>
            {subtitle && <div className="text-default-400 text-sm">{subtitle}</div>}
          </div>
          <button ref={closeRef} type="button" className="hover:bg-light rounded-lg p-1.5" onClick={onClose} aria-label="Close panel">
            <Icon icon="x" className="size-5" />
          </button>
        </div>
        {toolbar && <div className="border-default-300 border-b px-5 py-2.5 text-sm">{toolbar}</div>}
        <div className="flex-1 space-y-3 overflow-y-auto px-5 py-4">{children}</div>
        {footer && <div className="border-default-300 flex items-center justify-between gap-2 border-t px-5 py-3">{footer}</div>}
      </aside>
    </>
  )
}

export default SidePanel
