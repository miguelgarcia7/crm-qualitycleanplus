import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, router } from '@inertiajs/react'
import { useState } from 'react'

type Task = {
  id: number
  workflow_id: number
  workflow_type: string
  name: string
  step_type: string
  initiator: string | null
  created_at: string | null
  can_act: boolean
}
type Props = { tasks: Task[] }

const Page = ({ tasks }: Props) => {
  const [busy, setBusy] = useState<number | null>(null)

  const complete = (task: Task) => {
    setBusy(task.id)
    router.post(`/admin/workflow-steps/${task.id}/complete`, {}, { onFinish: () => setBusy(null), preserveScroll: true })
  }

  const reject = (task: Task) => {
    const reason = window.prompt('Reason for rejecting this task?')
    if (!reason) return
    setBusy(task.id)
    router.post(`/admin/workflow-steps/${task.id}/reject`, { reason }, { onFinish: () => setBusy(null), preserveScroll: true })
  }

  return (
    <>
      <Head title="My Tasks" />
      <PageBreadcrumb title="My Tasks" subtitle="Workflows" />

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
                    <td>{t.name}</td>
                    <td><span className="badge badge-label bg-secondary/15 text-secondary">{t.step_type}</span></td>
                    <td>{t.initiator ?? '—'}</td>
                    <td>{t.created_at}</td>
                    <td className="text-end">
                      {t.can_act ? (
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
