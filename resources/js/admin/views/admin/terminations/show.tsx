import PageBreadcrumb from '@/components/PageBreadcrumb'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head, Link, router } from '@inertiajs/react'
import { useState } from 'react'

type Step = { index: number; key: string; name: string; actor: string; status: string; completed_at: string | null }
type Equipment = { id: number; label: string; quantity: number }
type Props = {
  workflow: { id: number; status: string; is_terminal: boolean; current_step_key: string | null }
  person: { id: number; name: string; status: string }
  record: {
    effective_date: string
    type: string
    reason: string
    notes: string | null
    rehireable: boolean
    terminated_at: string | null
    final_paycheck_consolidated_cents: number | null
    final_paycheck_remainder_cents: number | null
    final_paycheck_processed_at: string | null
    cancellation_reason: string | null
  }
  steps: Step[]
  context: { equipment: Equipment[]; outstanding_charge_cents: number; open_period: string | null }
  can: { physical_tasks: boolean; payroll_tasks: boolean; cancel: boolean }
}

const money = (c: number | null) => (c == null ? '—' : `$${(c / 100).toFixed(2)}`)

const workflowBadge = (status: string) =>
  status === 'Completed'
    ? 'bg-success/15 text-success'
    : status === 'Cancelled'
      ? 'bg-secondary/15 text-secondary'
      : status === 'Rejected'
        ? 'bg-danger/15 text-danger'
        : 'bg-warning/15 text-warning'

/** Label/value cell for the info grids (HRM staff-profile pattern). */
const Field = ({ label, children, span = false }: { label: string; children: React.ReactNode; span?: boolean }) => (
  <div className={span ? 'md:col-span-2' : undefined}>
    <p className="text-default-400 mb-1.25 font-medium">{label}</p>
    <p>{children}</p>
  </div>
)

/** Icon fact row for the identity card (HRM staff-profile pattern). */
const FactRow = ({ icon, label, children }: { icon: string; label: string; children: React.ReactNode }) => (
  <div className="flex items-center gap-3">
    <div>
      <div className="btn btn-icon bg-light size-8!">
        <Icon icon={icon} className="text-secondary text-lg" />
      </div>
    </div>
    <p className="text-sm">
      {label} <span className="text-dark font-semibold">{children}</span>
    </p>
  </div>
)

const StepRow = ({ step, isCurrent }: { step: Step; isCurrent: boolean }) => {
  const chip =
    step.status === 'done' ? (
      <span className="bg-success/15 text-success flex size-8 shrink-0 items-center justify-center rounded-full">
        <Icon icon="check" className="text-base" />
      </span>
    ) : isCurrent ? (
      <span className="bg-primary/15 text-primary flex size-8 shrink-0 items-center justify-center rounded-full">
        <Icon icon="clock" className="text-base" />
      </span>
    ) : step.status === 'skipped' ? (
      <span className="bg-secondary/15 text-secondary flex size-8 shrink-0 items-center justify-center rounded-full">
        <Icon icon="x" className="text-base" />
      </span>
    ) : (
      <span className="bg-light text-default-400 flex size-8 shrink-0 items-center justify-center rounded-full">
        <span className="size-2 rounded-full bg-current" />
      </span>
    )

  return (
    <li className="flex items-center gap-3 py-2.5">
      {chip}
      <div className="grow">
        <p className={cn('text-sm', isCurrent && 'font-semibold')}>{step.name}</p>
        <p className="text-default-400 text-xs capitalize">{step.actor.replace(/_/g, ' ')}</p>
      </div>
      <span className="text-default-400 text-xs">
        {step.completed_at ?? (isCurrent ? <span className="badge badge-label bg-primary/15 text-primary">Current</span> : '')}
      </span>
    </li>
  )
}

const Page = ({ workflow, person, record, steps, context, can }: Props) => {
  const current = workflow.current_step_key
  const moveFileDone = steps.find((s) => s.key === 'move_file')?.status === 'done'
  const initials = person.name
    .split(' ')
    .map((part) => part[0])
    .slice(0, 2)
    .join('')
    .toUpperCase()

  const cancel = () => {
    const reason = window.prompt('Reason for cancelling?')
    if (reason) router.post(`/admin/terminations/${workflow.id}/cancel`, { reason }, { preserveScroll: true })
  }

  return (
    <>
      <Head title={`Termination — ${person.name}`} />
      <PageBreadcrumb title={person.name} subtitle="Terminations" />

      <div className="mb-4 flex flex-wrap items-center gap-3">
        <Link href="/admin/terminations" className="btn btn-sm btn-light">
          ← All terminations
        </Link>
        <span className="grow" />
        {can.cancel && !workflow.is_terminal && !moveFileDone && (
          <button className="btn btn-sm bg-danger/15 text-danger hover:bg-danger hover:text-white" onClick={cancel}>
            Cancel termination
          </button>
        )}
      </div>

      {record.cancellation_reason && (
        <div className="card mb-4">
          <div className="card-body p-4 text-sm">
            <span className="text-danger font-medium">Cancelled:</span> {record.cancellation_reason}
          </div>
        </div>
      )}

      <div className="gap-base grid grid-cols-1 xl:grid-cols-3">
        <div className="space-y-6">
          <div className="card">
            <div className="card-body">
              <div className="mb-7.5 flex items-center justify-between">
                <div className="gap-base flex items-center">
                  <div className="bg-danger/10 text-danger flex size-18 shrink-0 items-center justify-center rounded-full text-xl font-semibold">
                    {initials}
                  </div>
                  <div>
                    <h5 className="font-medium">{person.name}</h5>
                    <p className="text-default-400 mb-3 capitalize">{person.status.replace(/_/g, ' ')}</p>
                    <span className={cn('badge badge-label', workflowBadge(workflow.status))}>{workflow.status}</span>
                  </div>
                </div>
              </div>
              <div className="flex flex-col gap-y-3">
                <FactRow icon="calendar" label="Effective">
                  {record.effective_date}
                </FactRow>
                <FactRow icon="files" label="Type">
                  {record.type}
                </FactRow>
                <FactRow icon="layout" label="Reason">
                  {record.reason}
                </FactRow>
                <FactRow icon="check" label="Rehireable">
                  {record.rehireable ? 'Yes' : 'No'}
                </FactRow>
                {record.terminated_at && (
                  <FactRow icon="clock" label="Terminated">
                    {record.terminated_at}
                  </FactRow>
                )}
              </div>
            </div>
          </div>

          <div className="card">
            <div className="card-header">
              <h4 className="card-title">Progress</h4>
            </div>
            <div className="card-body py-2">
              <ol className="divide-default-200 divide-y">
                {steps.map((s) => (
                  <StepRow key={s.index} step={s} isCurrent={s.key === current} />
                ))}
              </ol>
            </div>
          </div>
        </div>

        <div className="space-y-6 xl:col-span-2">
          {!workflow.is_terminal && (
            <div className="card">
              <div className="card-header">
                <h4 className="card-title">Current Step</h4>
                <span className="badge badge-label bg-primary/15 text-primary">{steps.find((s) => s.key === current)?.name ?? '—'}</span>
              </div>
              <div className="card-body space-y-4">
                {current === 'recover_equipment' &&
                  (can.physical_tasks ? (
                    <RecoverEquipment workflowId={workflow.id} equipment={context.equipment} />
                  ) : (
                    <p className="text-default-500 text-sm">Awaiting front desk to recover equipment.</p>
                  ))}

                {current === 'move_file' &&
                  (can.physical_tasks ? (
                    <button
                      className="btn bg-primary hover:bg-primary-hover w-full py-2 font-semibold text-white"
                      onClick={() => router.post(`/admin/terminations/${workflow.id}/move-file`, {}, { preserveScroll: true })}
                    >
                      Mark file moved
                    </button>
                  ) : (
                    <p className="text-default-500 text-sm">Awaiting front desk to move the physical file.</p>
                  ))}

                {current === 'process_final_paycheck' &&
                  (can.payroll_tasks ? (
                    <FinalPaycheck workflowId={workflow.id} outstanding={context.outstanding_charge_cents} period={context.open_period} />
                  ) : (
                    <p className="text-default-500 text-sm">Awaiting payroll to process the final paycheck.</p>
                  ))}
              </div>
            </div>
          )}

          <div className="card">
            <div className="card-header">
              <h4 className="card-title">Termination Details</h4>
            </div>
            <div className="card-body">
              <div className="gap-x-base grid grid-cols-1 gap-y-5 md:grid-cols-2">
                <Field label="Effective date">{record.effective_date}</Field>
                <Field label="Type">{record.type}</Field>
                <Field label="Reason">{record.reason}</Field>
                <Field label="Rehireable">{record.rehireable ? 'Yes' : 'No'}</Field>
                <Field label="Person status">
                  <span className="capitalize">{person.status.replace(/_/g, ' ')}</span>
                </Field>
                <Field label="Terminated at">{record.terminated_at ?? '—'}</Field>
                {record.notes && (
                  <Field label="Notes" span>
                    {record.notes}
                  </Field>
                )}
              </div>
            </div>
          </div>

          {record.final_paycheck_processed_at && (
            <div className="card">
              <div className="card-header">
                <h4 className="card-title">Final Paycheck</h4>
                <span className="badge badge-label bg-success/15 text-success">Processed</span>
              </div>
              <div className="card-body">
                <div className="gap-x-base grid grid-cols-1 gap-y-5 md:grid-cols-2">
                  <Field label="Consolidated charges">{money(record.final_paycheck_consolidated_cents)}</Field>
                  <Field label="Written off">{money(record.final_paycheck_remainder_cents)}</Field>
                  <Field label="Processed at" span>
                    {record.final_paycheck_processed_at}
                  </Field>
                </div>
              </div>
            </div>
          )}
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
            <div className="font-medium">
              {e.label} <span className="text-default-400">×{e.quantity}</span>
            </div>
            <div className="mt-2 flex gap-4">
              <label className="flex items-center gap-1.5">
                <input type="radio" className="form-radio" checked={it?.returned === true} onChange={() => set(e.id, true)} /> Returned
              </label>
              <label className="flex items-center gap-1.5">
                <input type="radio" className="form-radio" checked={it?.returned === false} onChange={() => set(e.id, false)} /> Lost
              </label>
            </div>
          </div>
        )
      })}
      <button className="btn bg-primary hover:bg-primary-hover w-full py-2 font-semibold text-white" onClick={submit}>
        Confirm equipment recovery
      </button>
    </div>
  )
}

const FinalPaycheck = ({ workflowId, outstanding, period }: { workflowId: number; outstanding: number; period: string | null }) => (
  <div className="space-y-3 text-sm">
    <div className="bg-light/40 rounded-lg p-3">
      <div className="flex justify-between">
        <span className="text-default-500">Outstanding charges</span>
        <span className="font-medium">{money(outstanding)}</span>
      </div>
      <div className="flex justify-between">
        <span className="text-default-500">Open period</span>
        <span className="font-medium">{period ?? 'None'}</span>
      </div>
    </div>
    {!period && <p className="text-warning text-xs">No open payroll period — charges will be flagged for manual handling.</p>}
    <button
      className="btn bg-primary hover:bg-primary-hover w-full py-2 font-semibold text-white"
      onClick={() => router.post(`/admin/terminations/${workflowId}/final-paycheck`, {}, { preserveScroll: true })}
    >
      Process final paycheck
    </button>
  </div>
)

export default Page
