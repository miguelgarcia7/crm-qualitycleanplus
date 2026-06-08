import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, Link, router } from '@inertiajs/react'
import { useMemo, useState } from 'react'

type ExistingWo = { id: number; pay_rate: number; bill_rate: number; ot_pay_rate: number; ot_bill_rate: number }

type Row = {
  id: number
  row_number: number
  name: string | null
  external_id: string | null
  hours: number | null
  pay_rate_cents: number | null
  bill_rate_cents: number | null
  position: string | null
  position_id: number | null
  status: string
  status_label: string
  resolution: string | null
  matched_person: { id: number; name: string } | null
  existing_wo: ExistingWo | null
  rate_delta_warning: boolean
  needs_resolution: boolean
  needs_position: boolean
}

type Summary = {
  entries: number
  new_contractors: number
  new_work_orders: number
  adjustments: number
  total_payout: number
  total_bill: number
  unresolved: number
  can_commit: boolean
}

type AdjustmentItem = { id: number; name: string; default_value: number; type: string; is_billable: boolean }
type PendingAdjustment = {
  row_id: number
  adjustment_item_id: number | null
  value: number
  type: string
  is_billable: boolean
  notes: string | null
}

type Props = {
  batch: {
    id: number
    status: string
    status_label: string
    property: string | null
    period: string | null
    property_id: number
    payroll_period_id: number
    file_name: string
    invoice: { id: number; number: string } | null
    stats: Record<string, number> | null
  }
  rows: Row[]
  summary: Summary
  positions: { id: number; name: string }[]
  adjustmentItems: AdjustmentItem[]
  pendingAdjustments: PendingAdjustment[]
  people: { id: number; name: string }[]
  can: { commit: boolean; rollback: boolean }
}

const money = (cents: number | null | undefined) => `$${(((cents ?? 0) as number) / 100).toFixed(2)}`

const StatusBadge = ({ status, label }: { status: string; label: string }) => {
  const tone =
    status === 'matched'
      ? 'bg-success/10 text-success'
      : status === 'skipped' || status === 'applied'
        ? 'bg-gray-200 text-gray-600'
        : status === 'rate_conflict'
          ? 'bg-warning/10 text-warning'
          : 'bg-danger/10 text-danger'
  return <span className={`rounded px-2 py-0.5 text-xs font-medium ${tone}`}>{label}</span>
}

const post = (url: string, data: Record<string, string | number | boolean | null>) =>
  router.post(url, data, { preserveScroll: true })

const RowActions = ({ batchId, row, positions, people }: { batchId: number; row: Row; positions: Props['positions']; people: Props['people'] }) => {
  const base = `/admin/imports/${batchId}`
  const [personId, setPersonId] = useState('')
  const [creating, setCreating] = useState(false)
  const [newName, setNewName] = useState(row.name ?? '')
  const [positionId, setPositionId] = useState('')

  if (row.status === 'skipped') {
    return <span className="text-muted text-sm">Skipped</span>
  }

  return (
    <div className="space-y-2">
      {row.status === 'unmatched' && !creating && (
        <div className="flex flex-wrap items-center gap-2">
          <select className="form-select form-select-sm w-48" value={personId} onChange={(e) => setPersonId(e.target.value)}>
            <option value="">Find existing…</option>
            {people.map((p) => (
              <option key={p.id} value={p.id}>
                {p.name}
              </option>
            ))}
          </select>
          <button
            className="btn btn-sm bg-primary text-white disabled:opacity-50"
            disabled={!personId}
            onClick={() => post(`${base}/resolve`, { row_id: row.id, action: 'find_existing', person_id: personId })}
          >
            Link
          </button>
          <button className="btn btn-sm btn-light" onClick={() => setCreating(true)}>
            Create new
          </button>
          <button className="btn btn-sm btn-light" onClick={() => post(`${base}/resolve`, { row_id: row.id, action: 'skip' })}>
            Skip
          </button>
        </div>
      )}

      {row.status === 'unmatched' && creating && (
        <div className="flex flex-wrap items-center gap-2">
          <input className="form-input form-input-sm w-48" placeholder="Full name" value={newName} onChange={(e) => setNewName(e.target.value)} />
          <button
            className="btn btn-sm bg-primary text-white disabled:opacity-50"
            disabled={!newName}
            onClick={() => post(`${base}/resolve`, { row_id: row.id, action: 'create_contractor', name: newName })}
          >
            Create &amp; link
          </button>
          <button className="btn btn-sm btn-light" onClick={() => setCreating(false)}>
            Cancel
          </button>
        </div>
      )}

      {row.status === 'rate_conflict' && (
        <div className="flex flex-wrap items-center gap-2">
          <span className="text-xs">
            File {money(row.pay_rate_cents)} vs WO {money(row.existing_wo?.pay_rate)}
            {row.rate_delta_warning && <span className="text-danger ms-1 font-semibold">⚠ &gt;50%</span>}
          </span>
          <button
            className={`btn btn-sm ${row.resolution === 'use_file' ? 'bg-primary text-white' : 'btn-light'}`}
            onClick={() => post(`${base}/resolve`, { row_id: row.id, action: 'use_file' })}
          >
            Use file (new WO)
          </button>
          <button
            className={`btn btn-sm ${row.resolution === 'use_existing' ? 'bg-primary text-white' : 'btn-light'}`}
            onClick={() => post(`${base}/resolve`, { row_id: row.id, action: 'use_existing' })}
          >
            Use existing
          </button>
        </div>
      )}

      {row.needs_position && (
        <div className="flex flex-wrap items-center gap-2">
          <select className="form-select form-select-sm w-48" value={positionId} onChange={(e) => setPositionId(e.target.value)}>
            <option value="">Pick position{row.position ? ` (file: ${row.position})` : ''}…</option>
            {positions.map((p) => (
              <option key={p.id} value={p.id}>
                {p.name}
              </option>
            ))}
          </select>
          <button
            className="btn btn-sm bg-primary text-white disabled:opacity-50"
            disabled={!positionId}
            onClick={() => post(`${base}/resolve`, { row_id: row.id, action: 'set_position', position_id: positionId })}
          >
            Set
          </button>
        </div>
      )}

      {!row.needs_resolution && !row.needs_position && row.status !== 'unmatched' && (
        <span className="text-success text-sm">Ready{row.matched_person ? ` — ${row.matched_person.name}` : ''}</span>
      )}
    </div>
  )
}

const Page = ({ batch, rows, summary, positions, adjustmentItems, pendingAdjustments, people, can }: Props) => {
  const [step, setStep] = useState<'review' | 'adjustments' | 'final'>('review')
  const [adjustments, setAdjustments] = useState<PendingAdjustment[]>(pendingAdjustments)
  const readOnly = batch.status !== 'preview'

  const committableRows = useMemo(() => rows.filter((r) => r.status !== 'skipped' && r.status !== 'unmatched'), [rows])

  const addAdjustment = () =>
    setAdjustments((prev) => [
      ...prev,
      { row_id: committableRows[0]?.id ?? 0, adjustment_item_id: null, value: 0, type: 'incentive', is_billable: false, notes: null },
    ])

  const updateAdjustment = (i: number, patch: Partial<PendingAdjustment>) =>
    setAdjustments((prev) => prev.map((a, idx) => (idx === i ? { ...a, ...patch } : a)))

  const removeAdjustment = (i: number) => setAdjustments((prev) => prev.filter((_, idx) => idx !== i))

  const saveAdjustments = () =>
    router.post(
      `/admin/imports/${batch.id}/adjustments`,
      { adjustments: adjustments.map((a) => ({ ...a, value: a.value })) },
      { preserveScroll: true, onSuccess: () => setStep('final') },
    )

  const commit = () => {
    if (confirm('Commit this import? Time entries, an approved timesheet, and a frozen invoice will be created.')) {
      router.post(`/admin/imports/${batch.id}/commit`, {}, { preserveScroll: true })
    }
  }

  return (
    <>
      <Head title={`Import #${batch.id}`} />
      <PageBreadcrumb title={`Import #${batch.id}`} subtitle="Hour Imports" />

      <div className="card mb-4 rounded-2xl">
        <div className="card-body flex flex-wrap items-center justify-between gap-3 p-5">
          <div>
            <div className="font-semibold">{batch.property}</div>
            <div className="text-muted text-sm">
              {batch.period} · {batch.file_name} · <StatusBadge status={batch.status} label={batch.status_label} />
            </div>
          </div>
          <div className="flex items-center gap-2">
            {batch.invoice && (
              <Link href={`/admin/invoices/${batch.invoice.id}`} className="btn btn-sm bg-primary text-white">
                View invoice {batch.invoice.number}
              </Link>
            )}
            {batch.status === 'applied' && can.rollback && (
              <>
                <button
                  className="btn btn-sm btn-light"
                  onClick={() => {
                    if (confirm('Void this import and start a corrected re-import for the same week?')) {
                      router.post(`/admin/imports/${batch.id}/rollback`, { reimport: true }, { preserveScroll: true })
                    }
                  }}
                >
                  Re-import
                </button>
                <button
                  className="btn btn-sm btn-light text-danger"
                  onClick={() => {
                    if (confirm('Void and roll back this import? The invoice will be voided and time entries removed.')) {
                      router.post(`/admin/imports/${batch.id}/rollback`, { reimport: false }, { preserveScroll: true })
                    }
                  }}
                >
                  Void
                </button>
              </>
            )}
          </div>
        </div>
      </div>

      {!readOnly && (
        <div className="mb-4 flex gap-2">
          {(['review', 'adjustments', 'final'] as const).map((s, i) => (
            <button
              key={s}
              className={`btn btn-sm ${step === s ? 'bg-primary text-white' : 'btn-light'}`}
              onClick={() => setStep(s)}
            >
              {i + 1}. {s.charAt(0).toUpperCase() + s.slice(1)}
            </button>
          ))}
        </div>
      )}

      {(step === 'review' || readOnly) && (
        <div className="card rounded-2xl">
          <div className="card-body p-0">
            <table className="w-full text-sm">
              <thead className="border-default-200 border-b text-left">
                <tr className="text-muted">
                  <th className="p-3">#</th>
                  <th className="p-3">Name / ID</th>
                  <th className="p-3">Hours</th>
                  <th className="p-3">Pay</th>
                  <th className="p-3">Status</th>
                  {!readOnly && <th className="p-3">Resolve</th>}
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr key={row.id} className="border-default-100 border-b">
                    <td className="p-3">{row.row_number}</td>
                    <td className="p-3">
                      <div className="font-medium">{row.name}</div>
                      <div className="text-muted text-xs">
                        {row.external_id}
                        {row.position ? ` · ${row.position}` : ''}
                      </div>
                    </td>
                    <td className="p-3">{row.hours}</td>
                    <td className="p-3">{money(row.pay_rate_cents)}/hr</td>
                    <td className="p-3">
                      <StatusBadge status={row.status} label={row.status_label} />
                    </td>
                    {!readOnly && (
                      <td className="p-3">
                        <RowActions batchId={batch.id} row={row} positions={positions} people={people} />
                      </td>
                    )}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {step === 'adjustments' && !readOnly && (
        <div className="card rounded-2xl">
          <div className="card-body space-y-4 p-5">
            <p className="text-muted text-sm">Optionally add incentives or deductions per contractor. Applied when you commit.</p>
            {adjustments.map((a, i) => (
              <div key={i} className="border-default-200 grid grid-cols-1 gap-2 rounded-lg border p-3 md:grid-cols-6">
                <select className="form-select form-select-sm" value={a.row_id} onChange={(e) => updateAdjustment(i, { row_id: Number(e.target.value) })}>
                  {committableRows.map((r) => (
                    <option key={r.id} value={r.id}>
                      {r.name}
                    </option>
                  ))}
                </select>
                <select
                  className="form-select form-select-sm"
                  value={a.adjustment_item_id ?? ''}
                  onChange={(e) => {
                    const item = adjustmentItems.find((it) => it.id === Number(e.target.value))
                    updateAdjustment(i, {
                      adjustment_item_id: e.target.value ? Number(e.target.value) : null,
                      value: item ? item.default_value / 100 : a.value,
                      type: item ? item.type : a.type,
                      is_billable: item ? item.is_billable : a.is_billable,
                    })
                  }}
                >
                  <option value="">Custom…</option>
                  {adjustmentItems.map((it) => (
                    <option key={it.id} value={it.id}>
                      {it.name}
                    </option>
                  ))}
                </select>
                <select className="form-select form-select-sm" value={a.type} onChange={(e) => updateAdjustment(i, { type: e.target.value })}>
                  <option value="incentive">Incentive</option>
                  <option value="deduction">Deduction</option>
                </select>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  className="form-input form-input-sm"
                  placeholder="$"
                  value={a.value}
                  onChange={(e) => updateAdjustment(i, { value: Number(e.target.value) })}
                />
                <label className="flex items-center gap-1 text-xs">
                  <input
                    type="checkbox"
                    checked={a.is_billable}
                    disabled={a.type === 'deduction'}
                    onChange={(e) => updateAdjustment(i, { is_billable: e.target.checked })}
                  />
                  Billable
                </label>
                <button className="btn btn-sm btn-light text-danger" onClick={() => removeAdjustment(i)}>
                  Remove
                </button>
              </div>
            ))}
            <div className="flex gap-2">
              <button className="btn btn-sm btn-light" onClick={addAdjustment} disabled={committableRows.length === 0}>
                + Add adjustment
              </button>
              <button className="btn btn-sm bg-primary text-white" onClick={saveAdjustments}>
                Save &amp; continue
              </button>
            </div>
          </div>
        </div>
      )}

      {step === 'final' && !readOnly && (
        <div className="card rounded-2xl">
          <div className="card-body space-y-4 p-5">
            <div className="grid grid-cols-2 gap-4 md:grid-cols-3">
              <Stat label="Time entries" value={summary.entries} />
              <Stat label="New contractors" value={summary.new_contractors} />
              <Stat label="New work orders" value={summary.new_work_orders} />
              <Stat label="Adjustments" value={summary.adjustments} />
              <Stat label="Total payout" value={money(summary.total_payout)} />
              <Stat label="Total billable" value={money(summary.total_bill)} />
            </div>

            {summary.unresolved > 0 && (
              <p className="text-danger text-sm">
                {summary.unresolved} row(s) still need resolution. Go back to Review and resolve or skip them before committing.
              </p>
            )}

            <button
              className="btn bg-primary px-6 py-2.5 font-semibold text-white disabled:opacity-50"
              disabled={!summary.can_commit || !can.commit}
              onClick={commit}
            >
              Commit Import
            </button>
          </div>
        </div>
      )}
    </>
  )
}

const Stat = ({ label, value }: { label: string; value: string | number }) => (
  <div className="border-default-200 rounded-lg border p-3">
    <div className="text-muted text-xs">{label}</div>
    <div className="text-lg font-semibold">{value}</div>
  </div>
)

export default Page
