import { confirmAction } from '@/components/ConfirmHost'
import PageBreadcrumb from '@/components/PageBreadcrumb'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head, Link, router, useForm } from '@inertiajs/react'
import { FormEvent, ReactNode, useEffect, useState } from 'react'

type Option = { id: number; name: string }
type PropertyOption = Option & { direct_hire_hours: number }
type Rate = { pay_rate: number; bill_rate: number }
type WorkOrder = {
  id: number
  contractor: string | null
  property: string | null
  position: string | null
  bible_rate: Rate | null
  property_direct_hire_hours: number
  can_close: boolean
  /** True once hours are logged: rates then change only by pay increase or a new work order. */
  rates_locked: boolean
  can_pay_increase: boolean
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
  direct_hire: DirectHire | null
}

type MoreStaffOption = { id: number; property_id: number; label: string }
type DirectHire = {
  threshold_hours: number
  worked_hours: number
  remaining_hours: number
  percent: number
  eligible: boolean
  unrestricted: boolean
}

/** The staffing request a new work order is filling ("Place contractor"). */
type PlacingFor = { id: number; property_id: number; position_id: number; summary: string; requested_by: string | null; progress: string }

type Props = {
  placingFor?: PlacingFor | null
  /** Set when "New work order" was clicked on a property's page. */
  propertyId?: number | null
  workOrder: WorkOrder | null
  catalogs: { contractors: Option[]; properties: PropertyOption[]; positions: Option[]; moreStaffRequests?: MoreStaffOption[] }
}

const toDollars = (cents: number) => (cents / 100).toFixed(2)
const money = (cents: number) => `$${(cents / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
/** Dollars typed in a field, as cents; null when it isn't a number yet. */
const cents = (value: string) => {
  const n = parseFloat(value)
  return isNaN(n) ? null : Math.round(n * 100)
}

const otFromBase = (value: string) => {
  const n = parseFloat(value)
  return isNaN(n) ? '' : (n * 1.5).toFixed(2)
}

// Whether stored cent values deviate from the standard 1.5× OT convention.
const otDeviates = (pay: number, bill: number, otPay: number, otBill: number) =>
  Math.abs(otPay - pay * 1.5) > 1 || Math.abs(otBill - bill * 1.5) > 1

const Field = ({ label, htmlFor, error, help, children }: { label: ReactNode; htmlFor?: string; error?: string; help?: ReactNode; children: ReactNode }) => (
  <div>
    <label htmlFor={htmlFor} className="form-label">
      {label}
    </label>
    {children}
    {help && <p className="text-default-500 mt-1 text-xs">{help}</p>}
    {error && <p className="text-danger mt-1 text-sm">{error}</p>}
  </div>
)

/** A titled card, as on the property's Settings tab. */
const Section = ({ title, description, children }: { title: string; description: ReactNode; children: ReactNode }) => (
  <section className="card mb-0">
    <div className="card-header">
      <div>
        <h4 className="card-title">{title}</h4>
        <p className="text-default-500 mt-1 text-sm">{description}</p>
      </div>
    </div>
    <div className="card-body">{children}</div>
  </section>
)

/** A read-only value in the Placement card when editing. */
const Fixed = ({ label, children }: { label: string; children: ReactNode }) => (
  <div>
    <div className="text-default-500 text-xs uppercase">{label}</div>
    <div className="text-default-900 font-medium">{children}</div>
  </div>
)

const Page = ({ workOrder, catalogs, placingFor = null, propertyId = null }: Props) => {
  const directHire = workOrder?.direct_hire ?? null
  const editing = workOrder !== null

  const { data, setData, post, patch, processing, errors } = useForm({
    person_id: workOrder?.person_id ? String(workOrder.person_id) : '',
    property_id: workOrder?.property_id
      ? String(workOrder.property_id)
      : placingFor
        ? String(placingFor.property_id)
        : propertyId && catalogs.properties.some((p) => p.id === propertyId)
          ? String(propertyId)
          : '',
    position_id: workOrder?.position_id ? String(workOrder.position_id) : placingFor ? String(placingFor.position_id) : '',
    pay_rate: workOrder ? toDollars(workOrder.pay_rate) : '',
    bill_rate: workOrder ? toDollars(workOrder.bill_rate) : '',
    ot_pay_rate: workOrder ? toDollars(workOrder.ot_pay_rate) : '',
    ot_bill_rate: workOrder ? toDollars(workOrder.ot_bill_rate) : '',
    start_date: workOrder?.start_date ?? new Date().toISOString().slice(0, 10),
    end_date: workOrder?.end_date ?? '',
    status: workOrder?.status ?? 'active',
    notes: workOrder?.notes ?? '',
    direct_hire_threshold_hours: workOrder?.direct_hire_threshold_hours != null ? String(workOrder.direct_hire_threshold_hours) : '',
    more_staff_request_id: placingFor ? String(placingFor.id) : '',
  })

  const linkableRequests = (catalogs.moreStaffRequests ?? []).filter((r) => String(r.property_id) === data.property_id)
  const property = catalogs.properties.find((p) => String(p.id) === data.property_id) ?? null
  const propertyHours = workOrder?.property_direct_hire_hours ?? property?.direct_hire_hours ?? null

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

  // The property's Bible rate for the position: the stored one when editing,
  // looked up (and filled in) when creating.
  const [bibleRate, setBibleRate] = useState<Rate | null>(workOrder?.bible_rate ?? null)

  // Auto-fill rates from the Property Bible when property + position are chosen
  // (create only; don't clobber an existing WO's rates). Overridable.
  useEffect(() => {
    if (editing) return
    if (!data.property_id || !data.position_id) {
      setBibleRate(null)
      return
    }
    const params = new URLSearchParams({ property_id: data.property_id, position_id: data.position_id })
    fetch(`/admin/work-orders/rate-lookup?${params}`, { headers: { Accept: 'application/json' } })
      .then((r) => r.json())
      .then((rate) => {
        setBibleRate(rate ? { pay_rate: rate.pay_rate, bill_rate: rate.bill_rate } : null)
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

  // Closing records today as the end date and logs it, which the Status field
  // alone never did — so it is an action, not a dropdown choice.
  const closeWorkOrder = () =>
    workOrder &&
    confirmAction({
      title: 'Close work order',
      message: (
        <>
          End <strong>{workOrder.contractor}</strong>&apos;s placement as {workOrder.position} at <strong>{workOrder.property}</strong> today? They can no
          longer clock in on it; its hours and invoices stay.
        </>
      ),
      confirmLabel: 'Close Work Order',
      onConfirm: () => router.post(`/admin/work-orders/${workOrder.id}/close`, {}, { preserveScroll: true }),
    })

  const ratesLocked = editing && workOrder.rates_locked
  const pay = cents(data.pay_rate)
  const bill = cents(data.bill_rate)
  const margin = pay !== null && bill !== null ? bill - pay : null
  const payVsBible = pay !== null && bibleRate ? pay - bibleRate.pay_rate : null
  const positionName = editing ? workOrder.position : (positionOptions.find((o) => String(o.id) === data.position_id)?.name ?? null)

  const title = editing ? `Work order #${workOrder.id}${workOrder.contractor ? ` · ${workOrder.contractor}` : ''}` : 'Add Work Order'

  return (
    <>
      <Head title={title} />
      <PageBreadcrumb title={title} subtitle="Work Orders" />

      {placingFor && (
        <div className="bg-primary/10 text-primary mb-6 flex flex-wrap items-center justify-between gap-2 rounded-md px-4 py-3 text-sm">
          <span>
            Placing for <strong>{placingFor.summary}</strong>
            {placingFor.requested_by ? ` — ${placingFor.requested_by}'s staffing request` : ''} ({placingFor.progress}). Pick the contractor and confirm the
            rates; it's linked to the request.
          </span>
          <Link href="/admin/staffing-requests" className="font-medium hover:underline">
            Back to requests
          </Link>
        </div>
      )}

      <form onSubmit={submit} className="gap-base grid items-start xl:grid-cols-3">
        <div className="flex flex-col gap-6 xl:col-span-2">
          <Section
            title="Placement"
            description={
              editing ? (
                <>
                  Who works where. To move them to another property or position, use Transfer from the{' '}
                  <Link href={`/admin/work-orders?panel=${workOrder.id}`} className="text-primary hover:underline">
                    work order panel
                  </Link>
                  .
                </>
              ) : (
                'Who works where. Positions come from the property’s Bible rates.'
              )
            }>
            {editing ? (
              <div className="grid gap-5 md:grid-cols-3">
                <Fixed label="Contractor">
                  <Link href={`/admin/people/${workOrder.person_id}`} className="hover:text-primary">
                    {workOrder.contractor}
                  </Link>
                </Fixed>
                <Fixed label="Property">
                  <Link href={`/admin/properties/${workOrder.property_id}`} className="hover:text-primary">
                    {workOrder.property}
                  </Link>
                </Fixed>
                <Fixed label="Position">{workOrder.position}</Fixed>
              </div>
            ) : (
              <div className="grid gap-5 md:grid-cols-3">
                <Field label="Contractor" htmlFor="wo-contractor" error={errors.person_id}>
                  <select id="wo-contractor" className="form-select" value={data.person_id} onChange={(e) => setData('person_id', e.target.value)} required>
                    <option value="">Select…</option>
                    {catalogs.contractors.map((o) => (
                      <option key={o.id} value={o.id}>
                        {o.name}
                      </option>
                    ))}
                  </select>
                </Field>
                <Field label="Property" htmlFor="wo-property" error={errors.property_id}>
                  <select id="wo-property" className="form-select" value={data.property_id} onChange={(e) => setData('property_id', e.target.value)} required>
                    <option value="">Select…</option>
                    {catalogs.properties.map((o) => (
                      <option key={o.id} value={o.id}>
                        {o.name}
                      </option>
                    ))}
                  </select>
                </Field>
                <Field
                  label="Position"
                  htmlFor="wo-position"
                  error={errors.position_id}
                  help={
                    data.property_id && positionOptions.length === 0 ? (
                      <span className="text-warning">
                        This property has no positions with Bible rates yet —{' '}
                        <Link href={`/admin/properties/${data.property_id}`} className="underline">
                          add rates on the property
                        </Link>{' '}
                        first.
                      </span>
                    ) : undefined
                  }>
                  <select
                    id="wo-position"
                    className="form-select"
                    value={data.position_id}
                    onChange={(e) => setData('position_id', e.target.value)}
                    disabled={!data.property_id}
                    required>
                    <option value="">{data.property_id ? 'Select…' : 'Select a property first'}</option>
                    {positionOptions.map((o) => (
                      <option key={o.id} value={o.id}>
                        {o.name}
                      </option>
                    ))}
                  </select>
                </Field>
                {linkableRequests.length > 0 && (
                  <div className="md:col-span-3">
                    <Field label="Link to staffing request (optional)" htmlFor="wo-request" error={errors.more_staff_request_id}>
                      <select id="wo-request" className="form-select" value={data.more_staff_request_id} onChange={(e) => setData('more_staff_request_id', e.target.value)}>
                        <option value="">No — standalone work order</option>
                        {linkableRequests.map((r) => (
                          <option key={r.id} value={r.id}>
                            {r.label}
                          </option>
                        ))}
                      </select>
                    </Field>
                  </div>
                )}
              </div>
            )}
          </Section>

          <Section title="Rates" description="What the contractor is paid and what the property is billed, per hour.">
            {ratesLocked ? (
              <>
                <div className="grid grid-cols-2 gap-5 md:grid-cols-4">
                  <Fixed label="Pay">{money(workOrder.pay_rate)}</Fixed>
                  <Fixed label="Bill">{money(workOrder.bill_rate)}</Fixed>
                  <Fixed label="OT pay">{money(workOrder.ot_pay_rate)}</Fixed>
                  <Fixed label="OT bill">{money(workOrder.ot_bill_rate)}</Fixed>
                </div>
                <div className="bg-light/60 text-default-600 mt-5 flex items-start gap-3 rounded-lg px-4 py-3 text-sm">
                  <Icon icon="lock" className="text-default-500 mt-0.5 size-4 shrink-0" />
                  <p>
                    Rates are locked once hours are logged, so hours already worked keep the rate they were worked at. To raise them,{' '}
                    {workOrder.can_pay_increase ? (
                      <Link href={`/admin/pay-increases?work_order=${workOrder.id}`} className="text-primary font-medium hover:underline">
                        start a pay increase
                      </Link>
                    ) : (
                      'start a pay increase'
                    )}
                    . To lower them, close this work order at the end of a pay week and create a new one.
                  </p>
                </div>
              </>
            ) : (
              <>
                <div className="grid gap-5 md:grid-cols-2">
                  <Field label="Pay ($)" htmlFor="wo-pay" error={errors.pay_rate}>
                    <input id="wo-pay" className="form-input" inputMode="decimal" value={data.pay_rate} onChange={(e) => setData('pay_rate', e.target.value)} required />
                  </Field>
                  <Field label="Bill ($)" htmlFor="wo-bill" error={errors.bill_rate}>
                    <input id="wo-bill" className="form-input" inputMode="decimal" value={data.bill_rate} onChange={(e) => setData('bill_rate', e.target.value)} required />
                  </Field>
                  <Field
                    label={
                      <>
                        OT pay ($){!overrideOt && <span className="text-default-500"> · 1.5×</span>}
                      </>
                    }
                    htmlFor="wo-ot-pay"
                    error={errors.ot_pay_rate}>
                    <input
                      id="wo-ot-pay"
                      className={cn('form-input', !overrideOt && 'opacity-60')}
                      inputMode="decimal"
                      value={data.ot_pay_rate}
                      onChange={(e) => setData('ot_pay_rate', e.target.value)}
                      readOnly={!overrideOt}
                      tabIndex={overrideOt ? undefined : -1}
                      required
                    />
                  </Field>
                  <Field
                    label={
                      <>
                        OT bill ($){!overrideOt && <span className="text-default-500"> · 1.5×</span>}
                      </>
                    }
                    htmlFor="wo-ot-bill"
                    error={errors.ot_bill_rate}>
                    <input
                      id="wo-ot-bill"
                      className={cn('form-input', !overrideOt && 'opacity-60')}
                      inputMode="decimal"
                      value={data.ot_bill_rate}
                      onChange={(e) => setData('ot_bill_rate', e.target.value)}
                      readOnly={!overrideOt}
                      tabIndex={overrideOt ? undefined : -1}
                      required
                    />
                  </Field>
                </div>

                <label className="mt-4 flex items-center gap-2 text-sm">
                  <input type="checkbox" className="form-checkbox" checked={overrideOt} onChange={(e) => setOverrideOt(e.target.checked)} />
                  Set OT rates by hand (the contract isn’t 1.5×)
                </label>
                {editing && (
                  <p className="text-default-500 mt-2 text-xs">Rates can be changed until the first hours are logged. After that they lock.</p>
                )}
              </>
            )}

            {(margin !== null || payVsBible !== null) && (
              <div className="border-default-200 mt-5 space-y-1 border-t pt-4 text-sm">
                {margin !== null && bill !== null && (
                  <p>
                    Margin <span className="text-default-900 font-semibold">{money(margin)}</span> an hour
                    {bill > 0 ? ` · ${((margin / bill) * 100).toFixed(1)}%` : ''}
                  </p>
                )}
                {payVsBible !== null && bibleRate && (
                  <p className={payVsBible === 0 ? 'text-default-500' : 'text-default-700'}>
                    {payVsBible === 0
                      ? `Matches ${editing ? workOrder.property : property?.name}'s current ${positionName ?? 'position'} rate.`
                      : `${money(Math.abs(payVsBible))} ${payVsBible > 0 ? 'above' : 'below'} ${editing ? workOrder.property : property?.name}'s current ${positionName ?? 'position'} pay of ${money(bibleRate.pay_rate)}.`}
                  </p>
                )}
              </div>
            )}
          </Section>

          <Section title="Notes" description="Anything the next person to open this work order should know.">
            <Field label="Notes" htmlFor="wo-notes" error={errors.notes}>
              <textarea id="wo-notes" className="form-textarea" rows={3} value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
            </Field>
          </Section>
        </div>

        <div className="flex flex-col gap-6">
          <Section title={editing ? 'Dates & status' : 'Dates'} description={editing ? 'When the placement runs, and whether it is running now.' : 'When the placement starts, and ends if it is fixed-term.'}>
            <div className="grid gap-5">
              <Field label="Start date" htmlFor="wo-start" error={errors.start_date}>
                <input id="wo-start" type="date" className="form-input" value={data.start_date} onChange={(e) => setData('start_date', e.target.value)} required />
              </Field>
              <Field label="End date" htmlFor="wo-end" error={errors.end_date} help="Optional. Leave blank for an open-ended placement.">
                <input id="wo-end" type="date" className="form-input" value={data.end_date} onChange={(e) => setData('end_date', e.target.value)} />
              </Field>
              {editing && (
                <Field
                  label="Status"
                  htmlFor="wo-status"
                  error={errors.status}
                  help={workOrder.status === 'closed' ? 'Set it back to Active to reopen the placement.' : 'Suspended pauses clock-in without ending the placement.'}>
                  <select id="wo-status" className="form-select" value={data.status} onChange={(e) => setData('status', e.target.value)}>
                    <option value="active">Active</option>
                    <option value="suspended">Suspended</option>
                    {workOrder.status === 'closed' && <option value="closed">Closed</option>}
                  </select>
                </Field>
              )}
              {editing && workOrder.can_close && (
                <div className="border-default-200 border-t pt-4">
                  <button type="button" className="text-danger text-sm font-medium hover:underline" onClick={closeWorkOrder}>
                    Close work order…
                  </button>
                  <p className="text-default-500 mt-1 text-xs">Ends the placement today and records the end date.</p>
                </div>
              )}
            </div>
          </Section>

          <Section title="Direct hire" description="Hours this contractor must work before the property may hire them directly.">
            <Field
              label="Hours required"
              htmlFor="wo-direct-hire"
              error={errors.direct_hire_threshold_hours}
              help={
                <>
                  Counts worked hours, not calendar time. 0 means no restriction.
                  {!editing && propertyHours !== null && ` Leave blank to use ${property?.name}'s contract: ${propertyHours.toLocaleString()} h.`}
                </>
              }>
              <div className="flex items-center gap-2">
                <input
                  id="wo-direct-hire"
                  type="number"
                  min={0}
                  max={20000}
                  step={1}
                  className="form-input"
                  placeholder={!editing && propertyHours !== null ? `${propertyHours.toLocaleString()} (property)` : undefined}
                  value={data.direct_hire_threshold_hours}
                  onChange={(e) => setData('direct_hire_threshold_hours', e.target.value)}
                />
                <span className="text-default-500 text-sm">h</span>
              </div>
            </Field>
            {editing && propertyHours !== null && String(propertyHours) !== data.direct_hire_threshold_hours && (
              <button
                type="button"
                className="text-primary mt-2 text-sm font-medium hover:underline"
                onClick={() => setData('direct_hire_threshold_hours', String(propertyHours))}>
                Use {workOrder.property}'s contract: {propertyHours.toLocaleString()} h
              </button>
            )}

            {directHire && !directHire.unrestricted && (
              <div className="border-default-200 mt-4 border-t pt-4">
                <div className="mb-1 flex items-baseline justify-between text-sm">
                  <span className="text-default-500">
                    <span className="text-default-900 font-semibold">{Math.round(directHire.worked_hours).toLocaleString()}</span> of{' '}
                    {directHire.threshold_hours.toLocaleString()} h worked
                  </span>
                  <span className={directHire.eligible ? 'text-success font-semibold' : 'text-default-500'}>
                    {directHire.eligible ? 'Eligible' : `${directHire.percent}%`}
                  </span>
                </div>
                <div
                  className="bg-default-100 flex h-1.5 w-full overflow-hidden rounded"
                  role="progressbar"
                  aria-label="Progress toward direct-hire eligibility"
                  aria-valuenow={directHire.percent}
                  aria-valuemin={0}
                  aria-valuemax={100}>
                  <div className={cn('h-1.5 transition duration-500', directHire.eligible ? 'bg-success' : 'bg-primary')} style={{ width: `${Math.max(2, directHire.percent)}%` }} />
                </div>
              </div>
            )}
          </Section>
        </div>

        <div className="flex items-center gap-3 xl:col-span-3">
          <button type="submit" className="btn bg-primary hover:bg-primary-hover px-6 py-2.5 font-semibold text-white" disabled={processing}>
            <Icon icon="check" className="size-4" /> {editing ? 'Save Changes' : 'Create Work Order'}
          </button>
          <Link href="/admin/work-orders" className="btn btn-light px-6 py-2.5">
            Cancel
          </Link>
        </div>
      </form>
    </>
  )
}

export default Page
