import Icon from '@/components/wrappers/Icon'
import { useEffect } from 'react'

type ConfirmModalProps = {
  title: string
  /** What is about to happen, named specifically enough to catch a mis-click. */
  message: React.ReactNode
  confirmLabel?: string
  cancelLabel?: string
  /** Destructive actions get a red confirm button. */
  tone?: 'danger' | 'primary'
  onConfirm: () => void
  onClose: () => void
}

/**
 * Confirmation dialog for destructive actions, replacing window.confirm().
 *
 * Follows the reference library's modal structure (ui/modals: header with title
 * and close, body, footer with cancel + action) but is React-controlled rather
 * than Preline's declarative hs-overlay, because a confirm has to carry which
 * record it is about and report the choice back.
 */
const ConfirmModal = ({
  title,
  message,
  confirmLabel = 'Delete',
  cancelLabel = 'Cancel',
  tone = 'danger',
  onConfirm,
  onClose,
}: ConfirmModalProps) => {
  // Escape closes, matching what a browser dialog does.
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose()
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [onClose])

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="confirm-modal-title"
      onClick={onClose}>
      <div className="card w-full max-w-md" onClick={(e) => e.stopPropagation()}>
        <div className="card-header">
          <h4 className="card-title" id="confirm-modal-title">
            {title}
          </h4>
          <button type="button" onClick={onClose} aria-label="Close">
            <Icon icon="x" className="size-5" />
          </button>
        </div>

        <div className="card-body p-5">
          <div className="text-default-600 text-sm">{message}</div>
        </div>

        <div className="card-footer flex items-center justify-end gap-2">
          <button type="button" className="btn bg-light hover:text-primary" onClick={onClose}>
            {cancelLabel}
          </button>
          <button
            type="button"
            autoFocus
            className={`btn font-semibold text-white ${tone === 'danger' ? 'bg-danger hover:bg-danger/90' : 'bg-primary hover:bg-primary-hover'}`}
            onClick={() => {
              onConfirm()
              onClose()
            }}>
            {confirmLabel}
          </button>
        </div>
      </div>
    </div>
  )
}

export default ConfirmModal
