import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, Link, router } from '@inertiajs/react'
import { useState } from 'react'

type Step = { index: number; key: string; name: string; actor: string; status: string; completed_at: string | null }
type Equipment = { id: number; label: string; quantity: number }
type Props = {
  workflow: { id: number; status: string; is_terminal: boolean; current_step_key: string | null }
  person: { id: number; name: string; status: string }
  record: {
    effective_date: string; type: string; reason: string; notes: string | null; rehireable: boolean
    terminated_at: string | null; final_paycheck_consolidated_cents: number | null
    final_paycheck_remainder_cents: number | null; final_paycheck_processed_at: string | null
    cancellation_reason: string | null
  }
  steps: Step[]
  context: { equipment: Equipment[]; outstanding_charge_cents: number; open_period: string | null }
  can: { physical_tasks: boolean; payroll_tasks: boolean; cancel: boolean }
}

const money = (c: number | null) => (c == null ? '—' : `$${(c / 100).toFixed(2)}`)
const stepIcon = (s: string) => (s === 'done' ? '✓' : s === 'skipped' ? '–' : '○')
const stepColor = (s: string) => (s === 'done' ? 'text-success' : s === 'skipped' ? 'text-default-400' : 'text-primary')

const Page = ({ workflow, person, record, steps, context, can }: Props) => {
  const current = workflow.current_step_key
  const moveFileDone = steps.find((s) => s.key === 'move_file')?.status === 'done'

  return (
    <>
      <Head title={`Termination — ${person.name}`} />
      <PageBreadcrumb title={person.name} subtitle="Termination" />

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
        {/* Left: summary + steps */}
        <div className="space-y-4 lg:col-span-2">
          <div className="card rounded-2xl">
            <div className="card-header flex items-center justify-between p-6">
              <h4 className="card-title">Details</h4>
              <span className="badge badge-soft-primary">{workflow.status}</span>
            </div>
            <div className="card-body grid grid-cols-2 gap-4 p-6 text-sm">
              <div><div className="text-default-400 text-xs uppercase">Person status</div>{person.status}</div>
              <div><div className="text-default-400 text-xs uppercase">Effective</div>{record.effective_date}</div>
              <div><div className="text-default-400 text-xs uppercase">Type</div>{record.type}</div>
              <div><div className="text-default-400 text-xs uppercase">Reason</div>{record.reason}</div>
              <div><div className="text-default-400 text-xs uppercase">Rehireable</div>{record.rehireable ? 'Yes' : 'No'}</div>
              <div><div className="text-default-400 text-xs uppercase">Terminated at</div>{record.terminated_at ?? '—'}</div>
              {record.notes && <div className="col-span-2"><div className="text-default-400 text-xs uppercase">Notes</div>{record.notes}</div>}
              {record.final_paycheck_processed_at && (
                <div className="col-span-2">
                  <div className="text-default-400 text-xs uppercase">Final paycheck</div>
                  Consolidated {money(record.final_paycheck_consolidated_cents)}
                  {record.final_paycheck_remainder_cents ? ` · written off ${money(record.final_paycheck_remainder_cents)}` : ''}
                </div>
              )}
              {record.cancellation_reason && (
                <div className="col-span-2"><div className="text-default-400 text-xs uppercase">Cancelled</div>{record.cancellation_reason}</div>
              )}
            </div>
          </div>

          <div className="card rounded-2xl">
            <div className="card-header p-6"><h4 className="card-title">Steps</h4></div>
            <div className="card-body p-6">
              <ol className="space-y-3">
                {steps.map((s) => (
                  <li key={s.index} className="flex items-center gap-3 text-sm">
                    <span className={`text-lg ${stepColor(s.status)}`}>{stepIcon(s.status)}</span>
                    <span className={s.key === current ? 'font-semibold' : ''}>{s.name}</span>
                    <span className="text-default-400 ms-auto text-xs">{s.completed_at ?? (s.key === current ? 'Current' : '')}</span>
                  </li>
                ))}
              </ol>
            </div>
          </div>
        </div>

        {/* Right: action panel */}
        <div className="space-y-4">
          <div className="card rounded-2xl">
            <div className="card-header p-6"><h4 className="card-title">Action</h4></div>
            <div className="card-body space-y-4 p-6">
              {workflow.is_terminal && <p className="text-default-500 text-sm">This termination is {workflow.status.toLowerCase()}.</p>}

              {!workflow.is_terminal && current === 'recover_equipment' && (
                can.physical_tasks
                  ? <RecoverEquipment workflowId={workflow.id} equipment={context.equipment} />
                  : <p className="text-default-500 text-sm">Awaiting front desk to recover equipment.</p>
              )}

              {!workflow.is_terminal && current === 'move_file' && (
                can.physical_tasks
                  ? (
                    <button
                      className="btn w-full bg-primary py-2 font-semibold text-white"
                      onClick={() => router.post(`/admin/terminations/${workflow.id}/move-file`, {}, { preserveScroll: true })}
                    >Mark file moved</button>
                  )
                  : <p className="text-default-500 text-sm">Awaiting front desk to move the physical file.</p>
              )}

              {!workflow.is_terminal && current === 'process_final_paycheck' && (
                can.payroll_tasks
                  ? <FinalPaycheck workflowId={workflow.id} outstanding={context.outstanding_charge_cents} period={context.open_period} />
                  : <p className="text-default-500 text-sm">Awaiting payroll to process the final paycheck.</p>
              )}

              {can.cancel && !workflow.is_terminal && !moveFileDone && (
                <button
                  className="btn btn-soft-danger w-full py-2"
                  onClick={() => { const reason = window.prompt('Reason for cancelling?'); if (reason) router.post(`/admin/terminations/${workflow.id}/cancel`, { reason }, { preserveScroll: true }) }}
                >Cancel termination</button>
              )}
            </div>
          </div>

          <Link href="/admin/terminations" className="btn btn-light w-full py-2">← All terminations</Link>
        </div>
      </div>
    </>
  )
}

const RecoverEquipment = ({ workflowId, equipment }: { workflowId: number; equipment: Equipment[] }) => {
  const [items, setItems] = useState(() => equipment.map((e) => ({ assignment_id: e.id, returned: true, notes: '' })))
  const set = (id: number, returned: boolean) => setItems((prev) => prev.map((i) => (i.assignment_id === id ? { ...i, returned } : i)))

  const submit = () => router.post(`/admin/terminations/${workflowId}/recover-equipment`, { items }, { preserveScroll: true })

  return (
    <div className="space-y-3">
      {equipment.length === 0 && <p className="text-default-500 text-sm">No equipment is assigned — confirm to continue.</p>}
      {equipment.map((e) => {
        const it = items.find((i) => i.assignment_id === e.id)
        return (
          <div key={e.id} className="border-default-200 rounded-lg border p-3 text-sm">
            <div className="font-medium">{e.label} <span className="text-default-400">×{e.quantity}</span></div>
            <div className="mt-2 flex gap-4">
              <label className="flex items-center gap-1"><input type="radio" checked={it?.returned === true} onChange={() => set(e.id, true)} /> Returned</label>
              <label className="flex items-center gap-1"><input type="radio" checked={it?.returned === false} onChange={() => set(e.id, false)} /> Lost</label>
            </div>
          </div>
        )
      })}
      <button className="btn w-full bg-primary py-2 font-semibold text-white" onClick={submit}>Confirm equipment recovery</button>
    </div>
  )
}

const FinalPaycheck = ({ workflowId, outstanding, period }: { workflowId: number; outstanding: number; period: string | null }) => (
  <div className="space-y-3 text-sm">
    <div className="bg-light/40 rounded-lg p-3">
      <div className="flex justify-between"><span className="text-default-500">Outstanding charges</span><span className="font-medium">{money(outstanding)}</span></div>
      <div className="flex justify-between"><span className="text-default-500">Open period</span><span className="font-medium">{period ?? 'None'}</span></div>
    </div>
    {!period && <p className="text-warning text-xs">No open payroll period — charges will be flagged for manual handling.</p>}
    <button
      className="btn w-full bg-primary py-2 font-semibold text-white"
      onClick={() => router.post(`/admin/terminations/${workflowId}/final-paycheck`, {}, { preserveScroll: true })}
    >Process final paycheck</button>
  </div>
)

export default Page
