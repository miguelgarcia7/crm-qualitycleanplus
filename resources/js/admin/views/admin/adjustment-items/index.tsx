import PageBreadcrumb from '@/components/PageBreadcrumb'
import SidePanel from '@/components/SidePanel'
import StatusFilter from '@/components/table/StatusFilter'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head, router, useForm } from '@inertiajs/react'
import { FormEvent, ReactNode, useMemo, useState } from 'react'

type Item = {
  id: number
  name: string
  type: 'incentive' | 'deduction'
  default_value: number | null
  is_billable: boolean
  active: boolean
  used_90: number
  history: { id: number; at: string | null; text: string; by: string | null }[]
}

type Props = { items: Item[] }

const money = (cents: number) => `$${(cents / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`

const TypeBadge = ({ type }: { type: Item['type'] }) => (
  <span className={cn('badge badge-label', type === 'deduction' ? 'bg-danger/15 text-danger' : 'bg-success/15 text-success')}>
    {type === 'deduction' ? 'Deduction' : 'Incentive'}
  </span>
)

/**
 * Billing → Adjustment Items: the ready-made incentives and deductions
 * recruiters pick from on the weekly timesheet and in hour imports. A bounded
 * list, filtered here; add and edit in a side panel.
 */
const Page = ({ items }: Props) => {
  const [status, setStatus] = useState('active')
  const [type, setType] = useState('')
  const [search, setSearch] = useState('')
  // null: closed; 'new': adding; a number: editing that item.
  const [open, setOpen] = useState<'new' | number | null>(null)

  const counts = {
    active: items.filter((i) => i.active).length,
    inactive: items.filter((i) => !i.active).length,
    all: items.length,
  }

  const shown = useMemo(() => {
    const q = search.trim().toLowerCase()
    return items.filter(
      (i) =>
        (status === 'all' || (status === 'active' ? i.active : !i.active)) &&
        (type === '' || i.type === type) &&
        (q === '' || i.name.toLowerCase().includes(q)),
    )
  }, [items, status, type, search])

  const editing = typeof open === 'number' ? (items.find((i) => i.id === open) ?? null) : null

  return (
    <>
      <Head title="Adjustment Items" />
      <PageBreadcrumb title="Adjustment Items" subtitle="Billing" />

      <div className="mb-5 flex flex-wrap items-end justify-between gap-3">
        <p className="text-default-500 max-w-2xl">
          The ready-made bonuses and deductions recruiters pick when they add an adjustment on a weekly timesheet or an hour import.
        </p>
        <button type="button" className="btn bg-primary hover:bg-primary-hover font-semibold text-white" onClick={() => setOpen('new')}>
          <Icon icon="plus" className="size-4" /> Add item
        </button>
      </div>

      <div className="card">
        <div className="card-header flex flex-wrap items-center gap-3">
          <StatusFilter
            label="Status"
            value={status}
            onChange={setStatus}
            options={[
              { key: 'active', label: 'Active', count: counts.active },
              { key: 'inactive', label: 'Inactive', count: counts.inactive },
              { key: 'all', label: 'All', count: counts.all },
            ]}
          />
          <StatusFilter
            label="Type"
            value={type}
            onChange={setType}
            options={[
              { key: '', label: 'Both' },
              { key: 'incentive', label: 'Incentives' },
              { key: 'deduction', label: 'Deductions' },
            ]}
          />
          <div className="input-icon-group ms-auto">
            <Icon icon="search" className="input-icon" />
            <input className="form-input" placeholder="Search items" aria-label="Search items" value={search} onChange={(e) => setSearch(e.target.value)} />
          </div>
        </div>
        <div className="table-wrapper">
          <table className="table">
            <thead className="thead-sm">
              <tr className="bg-light/25 text-xs uppercase">
                <th>Item</th>
                <th>Type</th>
                <th className="text-end">Default amount</th>
                <th>Billed to hotel</th>
                <th className="text-end">Used, last 90 days</th>
                <th>Status</th>
                <th>
                  <span className="sr-only">Actions</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {shown.length === 0 ? (
                <tr>
                  <td colSpan={7} className="text-default-500 py-6 text-center">
                    {items.length === 0 ? 'No items yet. Add the first one.' : 'No items match.'}
                  </td>
                </tr>
              ) : (
                shown.map((i) => (
                  <tr key={i.id}>
                    <td className="text-default-900 font-semibold">{i.name}</td>
                    <td>
                      <TypeBadge type={i.type} />
                    </td>
                    <td className="text-end tabular-nums">{i.default_value === null ? <span className="text-default-500">Varies</span> : money(i.default_value)}</td>
                    <td>{i.type === 'deduction' ? <span className="text-default-500">Never</span> : i.is_billable ? 'Yes' : 'No'}</td>
                    <td className="text-end tabular-nums">{i.used_90}</td>
                    <td>
                      <span className={cn('badge badge-label', i.active ? 'bg-success/15 text-success' : 'bg-secondary/15 text-secondary')}>
                        {i.active ? 'Active' : 'Inactive'}
                      </span>
                    </td>
                    <td className="text-end">
                      <button type="button" className="btn btn-sm border-default-300 hover:border-default-400 border" onClick={() => setOpen(i.id)}>
                        Edit
                      </button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      <div className="card mt-5">
        <div className="card-body flex items-start gap-3">
          <Icon icon="info-circle" className="text-primary mt-0.5 size-5 shrink-0" />
          <div>
            <h4 className="text-default-900 font-semibold">Not on this list: uniform and equipment charges</h4>
            <p className="text-default-500 mt-1 text-sm">Those come from Supply Requests, split over 1 to 4 pay weeks, so a recruiter can’t charge them twice by hand.</p>
          </div>
        </div>
      </div>

      {open !== null && (open === 'new' || editing) && <ItemPanel key={open} item={editing} onClose={() => setOpen(null)} />}
    </>
  )
}

const Field = ({ id, label, hint, error, children }: { id?: string; label: string; hint?: string; error?: string; children: ReactNode }) => (
  <div>
    <label htmlFor={id} className="form-label">
      {label}
    </label>
    {children}
    {hint && <p className="text-default-500 mt-1 text-sm">{hint}</p>}
    {error && <p className="text-danger mt-1 text-sm">{error}</p>}
  </div>
)

/** Add or edit one item. Changes only apply to adjustments added from now on. */
const ItemPanel = ({ item, onClose }: { item: Item | null; onClose: () => void }) => {
  const form = useForm({
    name: item?.name ?? '',
    type: item?.type ?? 'incentive',
    default_amount: item?.default_value != null ? (item.default_value / 100).toFixed(2) : '',
    is_billable: item?.is_billable ?? false,
    active: item?.active ?? true,
  })
  const { data, setData, errors, processing } = form

  const save = (e?: FormEvent) => {
    e?.preventDefault()
    const options = { preserveScroll: true, onSuccess: onClose }
    if (item) form.put(`/admin/adjustment-items/${item.id}`, options)
    else form.post('/admin/adjustment-items', options)
  }

  // Saves only the switch, from the item as stored, so unsaved edits above don't go with it.
  const setActive = (active: boolean) => {
    if (!item) return
    router.put(
      `/admin/adjustment-items/${item.id}`,
      {
        name: item.name,
        type: item.type,
        default_amount: item.default_value != null ? (item.default_value / 100).toFixed(2) : '',
        is_billable: item.is_billable,
        active,
      },
      { preserveScroll: true, onSuccess: onClose },
    )
  }

  return (
    <SidePanel
      title={item ? item.name : 'New adjustment item'}
      subtitle={item ? `Used ${item.used_90} ${item.used_90 === 1 ? 'time' : 'times'} in the last 90 days` : 'Recruiters pick it when they add an adjustment.'}
      onClose={onClose}
      footer={
        <>
          <span />
          <div className="flex gap-2">
            <button type="button" className="btn border-default-300 border" onClick={onClose}>
              Cancel
            </button>
            <button type="submit" form="adjustment-item-form" className="btn bg-primary hover:bg-primary-hover font-semibold text-white" disabled={processing}>
              {item ? 'Save item' : 'Add item'}
            </button>
          </div>
        </>
      }
    >
      <form id="adjustment-item-form" onSubmit={save} className="space-y-5">
        <Field id="item-name" label="Name" hint="Recruiters see this in the dropdown. It shows on the timesheet and the payroll export." error={errors.name}>
          <input id="item-name" className="form-input" maxLength={255} value={data.name} onChange={(e) => setData('name', e.target.value)} required autoFocus={!item} />
        </Field>

        <div>
          <span className="form-label" id="item-type-label">
            Type
          </span>
          <div className="bg-light inline-flex rounded-lg p-0.5" role="group" aria-labelledby="item-type-label">
            {(['incentive', 'deduction'] as const).map((t) => (
              <button
                key={t}
                type="button"
                aria-pressed={data.type === t}
                onClick={() => setData('type', t)}
                className={cn('rounded-md px-3 py-1.5 text-sm', data.type === t ? 'bg-card text-default-900 font-semibold shadow-sm' : 'text-default-500')}
              >
                {t === 'incentive' ? 'Incentive (adds to pay)' : 'Deduction (takes from pay)'}
              </button>
            ))}
          </div>
          {errors.type && <p className="text-danger mt-1 text-sm">{errors.type}</p>}
        </div>

        <Field
          id="item-amount"
          label="Default amount ($)"
          hint="Filled in for the recruiter, who can still change it. Leave it blank when the amount is different every time."
          error={errors.default_amount}
        >
          <input
            id="item-amount"
            type="number"
            step="0.01"
            min="0.01"
            className="form-input max-w-40"
            value={data.default_amount}
            onChange={(e) => setData('default_amount', e.target.value)}
          />
        </Field>

        {data.type === 'incentive' ? (
          <fieldset>
            <legend className="form-label">Billed to the hotel?</legend>
            <div className="space-y-2">
              {[
                { value: true, title: 'Yes, bill the hotel', note: 'On the hotel’s invoice and paid to the contractor.' },
                { value: false, title: 'No, QCP pays it', note: 'Paid to the contractor only. The hotel never sees it.' },
              ].map((o) => (
                <label
                  key={String(o.value)}
                  className={cn('flex cursor-pointer items-start gap-3 rounded-lg border p-3', data.is_billable === o.value ? 'border-primary ring-primary ring-1' : 'border-default-300')}
                >
                  <input type="radio" name="is_billable" className="form-radio mt-1" checked={data.is_billable === o.value} onChange={() => setData('is_billable', o.value)} />
                  <span>
                    <span className="text-default-900 block font-semibold">{o.title}</span>
                    <span className="text-default-500 text-sm">{o.note}</span>
                  </span>
                </label>
              ))}
            </div>
          </fieldset>
        ) : (
          <p className="text-default-500 text-sm">Deductions are never billed to the hotel.</p>
        )}
      </form>

      {item && (
        <section className="border-default-300 mt-6 border-t pt-5">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h5 className="text-default-900 font-semibold">{item.active ? 'Active' : 'Inactive'}</h5>
              <p className="text-default-500 mt-1 max-w-sm text-sm">
                {item.active
                  ? 'Make it inactive to take it out of the dropdown. Adjustments already added keep their name and amount.'
                  : 'Not in the dropdown. Make it active to offer it again.'}
              </p>
            </div>
            <button type="button" className="btn border-default-300 border" disabled={processing} onClick={() => setActive(!item.active)}>
              {item.active ? 'Make inactive' : 'Make active'}
            </button>
          </div>
        </section>
      )}

      {item && (
        <section className="border-default-300 mt-6 border-t pt-5">
          <h5 className="text-default-900 mb-2 font-semibold">History</h5>
          {item.history.length === 0 ? (
            <p className="text-default-500 text-sm">No changes recorded.</p>
          ) : (
            <ol className="space-y-2 text-sm">
              {item.history.map((h) => (
                <li key={h.id} className="grid grid-cols-[6.5rem_minmax(0,1fr)] gap-3">
                  <span className="text-default-500">{h.at}</span>
                  <span className="text-default-900">
                    {h.text}
                    {h.by && <span className="text-default-500"> · {h.by}</span>}
                  </span>
                </li>
              ))}
            </ol>
          )}
          <p className="text-default-500 mt-4 text-xs">Changing an item only changes new adjustments. Ones already added stay as they were.</p>
        </section>
      )}
    </SidePanel>
  )
}

export default Page
