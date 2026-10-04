import { confirmAction } from '@/components/ConfirmHost'
import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, Link, router } from '@inertiajs/react'
import { useState } from 'react'

type Task = {
  id: number
  workflow_id: number
  workflow_type: string
  name: string
  /** Who and what the task is about, when the workflow can say. */
  summary: string | null
  /** Decided on its own page (it needs a form) — link there, no buttons. */
  review_url: string | null
  step_type: string
  initiator: string | null
  created_at: string | null
  can_act: boolean
}
type Props = { tasks: Task[] }

const Page = ({ tasks }: Props) => {
  const [busy, setBusy] = useState<number | null>(null)
  // A refused action says why here, rather than the button just un-spinning.
  const [error, setError] = useState<string | null>(null)
  const options = (task: Task) => ({
    preserveScroll: true,
    onStart: () => {
      setBusy(task.id)
      setError(null)
    },
    onError: (errors: Record<string, string>) => setError(Object.values(errors)[0] ?? 'That task could not be updated.'),
    onFinish: () => setBusy(null),
  })

  const complete = (task: Task) => router.post(`/admin/workflow-steps/${task.id}/complete`, {}, options(task))

  const reject = (task: Task) =>
    confirmAction({
      title: 'Reject task',
      message: <>Reject <strong>{task.name}</strong>{task.initiator ? <> from {task.initiator}</> : null}?</>,
      input: { label: 'Reason', required: true },
      confirmLabel: 'Reject',
      onConfirm: (reason) => router.post(`/admin/workflow-steps/${task.id}/reject`, { reason }, options(task)),
    })

  return (
    <>
      <Head title="My Tasks" />
      <PageBreadcrumb title="My Tasks" subtitle="Workflows" />

      {error && (
        <div className="bg-danger/10 text-danger mb-4 flex items-start justify-between gap-3 rounded-md px-4 py-3 text-sm" role="alert">
          <span>{error}</span>
          <button type="button" className="font-medium hover:underline" onClick={() => setError(null)}>Dismiss</button>
        </div>
      )}

      <div className="card">
        <div className="card-header"><h4 className="card-title">Tasks assigned to me</h4></div>
        <div className="table-wrapper">
          <table className="table table-hover">
            <thead className="thead-sm">
              <tr className="bg-light/25 text-xs uppercase">
                <th>Workflow</th>
                <th>Task</th>
                <th>Type</th>
                <th>Requested by</th>
                <th>Opened</th>
                <th className="text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              {tasks.length ? (
                tasks.map((t) => (
                  <tr key={t.id}>
                    <td className="font-medium">{t.workflow_type}</td>
                    <td>
                      {t.name}
                      {t.summary && <p className="text-default-400 text-xs">{t.summary}</p>}
                    </td>
                    <td><span className="badge badge-label bg-secondary/15 text-secondary">{t.step_type}</span></td>
                    <td>{t.initiator ?? '—'}</td>
                    <td>{t.created_at}</td>
                    <td className="text-end">
                      {t.can_act && t.review_url ? (
                        <Link href={t.review_url} className="btn bg-primary/15 text-primary hover:bg-primary hover:text-white">
                          Review
                        </Link>
                      ) : t.can_act ? (
                        <div className="flex justify-end gap-1.5">
                          <button className="btn bg-success/15 text-success hover:bg-success hover:text-white" disabled={busy === t.id} onClick={() => complete(t)}>
                            Complete
                          </button>
                          <button className="btn bg-danger/15 text-danger hover:bg-danger hover:text-white" disabled={busy === t.id} onClick={() => reject(t)}>
                            Reject
                          </button>
                        </div>
                      ) : (
                        <span className="text-default-400">—</span>
                      )}
                    </td>
                  </tr>
                ))
              ) : (
                <tr><td colSpan={6} className="text-default-400 py-4 text-center">No pending tasks.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </>
  )
}

export default Page
