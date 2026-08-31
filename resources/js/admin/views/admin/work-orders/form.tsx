import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, Link, useForm } from '@inertiajs/react'
import { FormEvent, useEffect, useState } from 'react'

type Option = { id: number; name: string }
type WorkOrder = {
  id: number
  person_id: number
  property_id: number
  position_id: number
  pay_rate: number
  bill_rate: number
  ot_pay_rate: number
  ot_bill_rate: number
  start_date: string
  end_date: string | null
  status: string
  notes: string | null
  direct_hire_threshold_hours: number | null
}

type MoreStaffOption = { id: number; property_id: number; label: string }
type Props = {
  workOrder: WorkOrder | null
  catalogs: { contractors: Option[]; properties: Option[]; positions: Option[]; moreStaffRequests?: MoreStaffOption[] }
}

const toDollars = (cents: number) => (cents / 100).toFixed(2)

const otFromBase = (value: string) => {
  const n = parseFloat(value)
  return isNaN(n) ? '' : (n * 1.5).toFixed(2)
}

// Whether stored cent values deviate from the standard 1.5× OT convention.
const otDeviates = (pay: number, bill: number, otPay: number, otBill: number) =>
  Math.abs(otPay - pay * 1.5) > 1 || Math.abs(otBill - bill * 1.5) > 1

const Field = ({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) => (
  <div>
    <label className="form-label">{label}</label>
    {children}
    {error && <p className="text-danger mt-1 text-sm">{error}</p>}
  </div>
)

const Page = ({ workOrder, catalogs }: Props) => {
  const editing = workOrder !== null

  const { data, setData, post, patch, processing, errors } = useForm({
    person_id: workOrder?.person_id ? String(workOrder.person_id) : '',
    property_id: workOrder?.property_id ? String(workOrder.property_id) : '',
    position_id: workOrder?.position_id ? String(workOrder.position_id) : '',
    pay_rate: workOrder ? toDollars(workOrder.pay_rate) : '',
    bill_rate: workOrder ? toDollars(workOrder.bill_rate) : '',
    ot_pay_rate: workOrder ? toDollars(workOrder.ot_pay_rate) : '',
    ot_bill_rate: workOrder ? toDollars(workOrder.ot_bill_rate) : '',
    start_date: workOrder?.start_date ?? new Date().toISOString().slice(0, 10),
    end_date: workOrder?.end_date ?? '',
    status: workOrder?.status ?? 'active',
    notes: workOrder?.notes ?? '',
    direct_hire_threshold_hours: workOrder?.direct_hire_threshold_hours ?? '',
    more_staff_request_id: '',
  })

  const linkableRequests = (catalogs.moreStaffRequests ?? []).filter((r) => String(r.property_id) === data.property_id)

  // Positions come from the Property Bible: only those with a current rate at
  // the chosen property are offered (create only; edit shows the stored one).
  const [positionOptions, setPositionOptions] = useState<Option[]>(editing ? catalogs.positions : [])
  useEffect(() => {
    if (editing) return
    if (!data.property_id) {
      setPositionOptions([])
      return
    }
    let stale = false
    fetch(`/admin/work-orders/position-lookup?property_id=${data.property_id}`, { headers: { Accept: 'application/json' } })
      .then((r) => r.json())
      .then((options: Option[]) => {
        if (stale) return
        setPositionOptions(options)
        setData((d) => (options.some((o) => String(o.id) === d.position_id) ? d : { ...d, position_id: '' }))
      })
      .catch(() => {})
    return () => {
      stale = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [data.property_id])

  // OT is 1.5× by default; override unlocks the fields when a contract (or the
  // Bible rate) deviates. Editing an off-standard WO starts in override mode.
  const [overrideOt, setOverrideOt] = useState(
    () => workOrder !== null && otDeviates(workOrder.pay_rate, workOrder.bill_rate, workOrder.ot_pay_rate, workOrder.ot_bill_rate),
  )
  useEffect(() => {
    if (overrideOt) return
    setData((d) => ({ ...d, ot_pay_rate: otFromBase(d.pay_rate), ot_bill_rate: otFromBase(d.bill_rate) }))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [data.pay_rate, data.bill_rate, overrideOt])

  // Auto-fill rates from the Property Bible when property + position are chosen
  // (create only; don't clobber an existing WO's rates). Overridable.
  useEffect(() => {
    if (editing || !data.property_id || !data.position_id) return
    const params = new URLSearchParams({ property_id: data.property_id, position_id: data.position_id })
    fetch(`/admin/work-orders/rate-lookup?${params}`, { headers: { Accept: 'application/json' } })
      .then((r) => r.json())
      .then((rate) => {
        if (!rate) return
        setData((d) => ({
          ...d,
          pay_rate: toDollars(rate.pay_rate),
          bill_rate: toDollars(rate.bill_rate),
          ot_pay_rate: toDollars(rate.ot_pay_rate),
          ot_bill_rate: toDollars(rate.ot_bill_rate),
        }))
        // A Bible rate with non-standard OT carries its override into the WO.
        setOverrideOt(otDeviates(rate.pay_rate, rate.bill_rate, rate.ot_pay_rate, rate.ot_bill_rate))
      })
      .catch(() => {})
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [data.property_id, data.position_id])

  const submit = (e: FormEvent) => {
    e.preventDefault()
    editing ? patch(`/admin/work-orders/${workOrder.id}`) : post('/admin/work-orders')
  }

  return (
    <>
      <Head title={editing ? 'Edit Work Order' : 'Add Work Order'} />
      <PageBreadcrumb title={editing ? 'Edit Work Order' : 'Add Work Order'} subtitle="Work Orders" />

      <div className="card rounded-2xl">
        <div className="card-body p-6">
          <form onSubmit={submit} className="space-y-6">
            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
              <Field label="Contractor" error={errors.person_id}>
                <select className="form-select" value={data.person_id} onChange={(e) => setData('person_id', e.target.value)} disabled={editing} required>
                  <option value="">Select…</option>
                  {catalogs.contractors.map((o) => (
                    <option key={o.id} value={o.id}>{o.name}</option>
                  ))}
                </select>
              </Field>
              <Field label="Property" error={errors.property_id}>
                <select className="form-select" value={data.property_id} onChange={(e) => setData('property_id', e.target.value)} disabled={editing} required>
                  <option value="">Select…</option>
                  {catalogs.properties.map((o) => (
                    <option key={o.id} value={o.id}>{o.name}</option>
                  ))}
                </select>
              </Field>
              <Field label="Position" error={errors.position_id}>
                <select
                  className="form-select"
                  value={data.position_id}
                  onChange={(e) => setData('position_id', e.target.value)}
                  disabled={editing || !data.property_id}
                  required
                >
                  <option value="">{data.property_id || editing ? 'Select…' : 'Select a property first'}</option>
                  {positionOptions.map((o) => (
                    <option key={o.id} value={o.id}>{o.name}</option>
                  ))}
                </select>
                {!editing && data.property_id && positionOptions.length === 0 && (
                  <p className="text-warning mt-1 text-sm">
                    This property has no positions with Bible rates yet —{' '}
                    <Link href={`/admin/properties/${data.property_id}`} className="underline">
                      add rates on the property
                    </Link>{' '}
                    first.
                  </p>
                )}
              </Field>

              <Field label="Pay Rate ($)" error={errors.pay_rate}>
                <input className="form-input" value={data.pay_rate} onChange={(e) => setData('pay_rate', e.target.value)} required />
              </Field>
              <Field label="Bill Rate ($)" error={errors.bill_rate}>
                <input className="form-input" value={data.bill_rate} onChange={(e) => setData('bill_rate', e.target.value)} required />
              </Field>
              <div />
              <Field label={overrideOt ? 'OT Pay Rate ($)' : 'OT Pay Rate ($) · auto 1.5×'} error={errors.ot_pay_rate}>
                <input
                  className={`form-input ${overrideOt ? '' : 'opacity-60'}`}
                  value={data.ot_pay_rate}
                  onChange={(e) => setData('ot_pay_rate', e.target.value)}
                  readOnly={!overrideOt}
                  tabIndex={overrideOt ? undefined : -1}
                  required
                />
              </Field>
              <Field label={overrideOt ? 'OT Bill Rate ($)' : 'OT Bill Rate ($) · auto 1.5×'} error={errors.ot_bill_rate}>
                <input
                  className={`form-input ${overrideOt ? '' : 'opacity-60'}`}
                  value={data.ot_bill_rate}
                  onChange={(e) => setData('ot_bill_rate', e.target.value)}
                  readOnly={!overrideOt}
                  tabIndex={overrideOt ? undefined : -1}
                  required
                />
              </Field>
              <div className="flex items-end pb-2">
                <label className="flex items-center gap-2 text-sm">
                  <input type="checkbox" className="form-checkbox" checked={overrideOt} onChange={(e) => setOverrideOt(e.target.checked)} />
                  Override OT rates (≠ 1.5×)
                </label>
              </div>

              <Field label="Start Date" error={errors.start_date}>
                <input type="date" className="form-input" value={data.start_date} onChange={(e) => setData('start_date', e.target.value)} required />
              </Field>
              <Field label="End Date" error={errors.end_date}>
                <input type="date" className="form-input" value={data.end_date} onChange={(e) => setData('end_date', e.target.value)} />
              </Field>
              {editing && (
                <Field label="Status" error={errors.status}>
                  <select className="form-select" value={data.status} onChange={(e) => setData('status', e.target.value)}>
                    <option value="active">Active</option>
                    <option value="suspended">Suspended</option>
                    <option value="closed">Closed</option>
                  </select>
                </Field>
              )}
            </div>

            {!editing && linkableRequests.length > 0 && (
              <Field label="Link to staffing request (optional)" error={errors.more_staff_request_id}>
                <select className="form-select" value={data.more_staff_request_id} onChange={(e) => setData('more_staff_request_id', e.target.value)}>
                  <option value="">No — standalone work order</option>
                  {linkableRequests.map((r) => (
                    <option key={r.id} value={r.id}>{r.label}</option>
                  ))}
                </select>
              </Field>
            )}

            <Field label="Direct-Hire Threshold (hours)" error={errors.direct_hire_threshold_hours}>
              <input
                type="number"
                min="0"
                className="form-input"
                placeholder="Use the property default"
                value={data.direct_hire_threshold_hours}
                onChange={(e) => setData('direct_hire_threshold_hours', e.target.value)}
              />
              <p className="text-default-400 mt-1 text-xs">
                Hours this contractor must work before the property may hire them directly. Leave blank to take the property&apos;s
                contracted value. Counts worked hours, not calendar time.
              </p>
            </Field>
            <Field label="Notes" error={errors.notes}>
              <textarea className="form-input" rows={2} value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
            </Field>

            <div className="flex items-center gap-3">
              <button type="submit" className="btn bg-primary hover:bg-primary-hover px-6 py-2.5 font-semibold text-white" disabled={processing}>
                {editing ? 'Save Changes' : 'Create Work Order'}
              </button>
              <Link href="/admin/work-orders" className="btn btn-light px-6 py-2.5">Cancel</Link>
            </div>
          </form>
        </div>
      </div>
    </>
  )
}

export default Page
