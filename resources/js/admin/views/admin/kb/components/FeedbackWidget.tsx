import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { router, usePage } from '@inertiajs/react'
import { useState } from 'react'

/**
 * "Was this helpful?" widget (Phase 08c) — used by both KB readers (back
 * office + QC Minute). Votes post immediately; the detailed form covers
 * suggestion / issue / question. `myVote` reflects the reader's recorded vote
 * (votes can be flipped, not repeated).
 */
const FeedbackWidget = ({ endpoint, myVote }: { endpoint: string; myVote: string | null }) => {
  const [showForm, setShowForm] = useState(false)
  const [type, setType] = useState('suggestion')
  const [message, setMessage] = useState('')
  const [sending, setSending] = useState(false)
  const errors = usePage().props.errors as Record<string, string>

  const vote = (value: 'helpful' | 'not_helpful') => {
    if (myVote === value) return
    router.post(endpoint, { type: value }, { preserveScroll: true })
  }

  const submitDetail = (e: React.FormEvent) => {
    e.preventDefault()
    setSending(true)
    router.post(
      endpoint,
      { type, message },
      {
        preserveScroll: true,
        onSuccess: () => {
          setShowForm(false)
          setMessage('')
        },
        onFinish: () => setSending(false),
      },
    )
  }

  return (
    <div className="card">
      <div className="card-body">
        <div className="flex flex-wrap items-center gap-3">
          <p className="font-semibold">Was this article helpful?</p>
          <button
            type="button"
            onClick={() => vote('helpful')}
            className={cn(
              'btn btn-sm',
              myVote === 'helpful' ? 'bg-success text-white' : 'bg-success/15 text-success hover:bg-success hover:text-white',
            )}
          >
            <Icon icon="thumb-up" className="me-1 size-4" /> Yes
          </button>
          <button
            type="button"
            onClick={() => vote('not_helpful')}
            className={cn(
              'btn btn-sm',
              myVote === 'not_helpful' ? 'bg-danger text-white' : 'bg-danger/15 text-danger hover:bg-danger hover:text-white',
            )}
          >
            <Icon icon="thumb-down" className="me-1 size-4" /> No
          </button>
          <span className="grow" />
          <button type="button" className="text-primary text-sm" onClick={() => setShowForm((v) => !v)}>
            {showForm ? 'Close' : 'Leave detailed feedback'}
          </button>
        </div>

        {errors.feedback && <p className="text-default-400 mt-2 text-sm">{errors.feedback}</p>}

        {showForm && (
          <form onSubmit={submitDetail} className="border-default-200 mt-4 space-y-3 border-t pt-4">
            <div className="flex flex-wrap gap-3">
              <select className="form-select w-auto" value={type} onChange={(e) => setType(e.target.value)}>
                <option value="suggestion">Suggestion</option>
                <option value="issue">Something is wrong</option>
                <option value="question">Question</option>
              </select>
            </div>
            <textarea
              className="form-input w-full"
              rows={3}
              value={message}
              onChange={(e) => setMessage(e.target.value)}
              placeholder="Tell us more…"
              required
            />
            {errors.message && <p className="text-danger text-sm">{errors.message}</p>}
            <button className="btn bg-primary hover:bg-primary-hover text-white" disabled={sending}>
              Send feedback
            </button>
          </form>
        )}
      </div>
    </div>
  )
}

export default FeedbackWidget
