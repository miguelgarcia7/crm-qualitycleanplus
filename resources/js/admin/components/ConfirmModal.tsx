import Icon from '@/components/wrappers/Icon'
import { useEffect, useState } from 'react'

/** A text field inside the dialog — e.g. the reason for declining. */
export type ConfirmInput = {
  label: string
  placeholder?: string
  /** The confirm button stays inactive until something is typed. */
  required?: boolean
  initial?: string
}

type ConfirmModalProps = {
  title: string
  /** What is about to happen, named specifically enough to catch a mis-click. */
  message: React.ReactNode
  confirmLabel?: string
  cancelLabel?: string
  /** Destructive actions get a red confirm button. */
  tone?: 'danger' | 'primary'
  input?: ConfirmInput
  /** Receives the typed text when the dialog has an input ('' otherwise). */
  onConfirm: (value: string) => void
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
  input,
  onConfirm,
  onClose,
}: ConfirmModalProps) => {
  const [value, setValue] = useState(input?.initial ?? '')
  const blocked = input?.required === true && value.trim() === ''
  const confirm = () => {
    if (blocked) return
    onConfirm(value.trim())
    onClose()
  }

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
          {input && (
            <div className="mt-4">
              <label className="form-label" htmlFor="confirm-modal-input">
                {input.label}
              </label>
              <textarea
                id="confirm-modal-input"
                autoFocus
                rows={3}
                className="form-textarea"
                placeholder={input.placeholder}
                value={value}
                onChange={(e) => setValue(e.target.value)}
                onKeyDown={(e) => {
                  if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) confirm()
                }}
              />
            </div>
          )}
        </div>

        <div className="card-footer flex items-center justify-end gap-2">
          <button type="button" className="btn bg-light hover:text-primary" onClick={onClose}>
            {cancelLabel}
          </button>
          <button
            type="button"
            autoFocus={!input}
            disabled={blocked}
            className={`btn font-semibold text-white disabled:opacity-50 ${tone === 'danger' ? 'bg-danger hover:bg-danger/90' : 'bg-primary hover:bg-primary-hover'}`}
            onClick={confirm}>
            {confirmLabel}
          </button>
        </div>
      </div>
    </div>
  )
}

export default ConfirmModal
