import ConfirmModal from '@/components/ConfirmModal'
import { useEffect, useState } from 'react'

export type ConfirmRequest = {
  title: string
  /** Name the record. "Are you sure?" tells a mis-clicker nothing. */
  message: React.ReactNode
  confirmLabel?: string
  cancelLabel?: string
  tone?: 'danger' | 'primary'
  onConfirm: () => void
}

type Listener = (request: ConfirmRequest | null) => void

let listener: Listener | null = null

/**
 * Ask for confirmation, then run the action — a drop-in replacement for
 * window.confirm() that renders the app's modal instead of a browser dialog.
 *
 *   confirmAction({
 *     title: 'Delete tag',
 *     message: <>Delete <strong>{tag.name}</strong>?</>,
 *     onConfirm: () => router.delete(url),
 *   })
 *
 * Routed through one host mounted in the layout so a screen needs no state and
 * no extra markup — which is what makes converting ~20 call sites tractable.
 */
export const confirmAction = (request: ConfirmRequest): void => {
  listener?.(request)
}

/** Mounted once, in the layout. */
const ConfirmHost = () => {
  const [request, setRequest] = useState<ConfirmRequest | null>(null)

  useEffect(() => {
    listener = setRequest

    return () => {
      listener = null
    }
  }, [])

  if (request === null) return null

  return (
    <ConfirmModal
      title={request.title}
      message={request.message}
      confirmLabel={request.confirmLabel}
      cancelLabel={request.cancelLabel}
      tone={request.tone}
      onConfirm={request.onConfirm}
      onClose={() => setRequest(null)}
    />
  )
}

export default ConfirmHost
