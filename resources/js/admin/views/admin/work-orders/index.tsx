import { confirmAction } from '@/components/ConfirmHost'
import PageBreadcrumb from '@/components/PageBreadcrumb'
import DataTable from '@/components/table/DataTable'
import ServerPagination, { PaginationMeta } from '@/components/table/ServerPagination'
import WorkOrderPanel, { WorkOrderPanelData } from '@/components/work-orders/WorkOrderPanel'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head, Link, router, useForm } from '@inertiajs/react'
import { createColumnHelper, getCoreRowModel, Row as TableRow, SortingState, useReactTable } from '@tanstack/react-table'
import { FormEvent, useEffect, useMemo, useRef, useState } from 'react'

type DirectHire = {
  threshold_hours: number
  worked_hours: number
  remaining_hours: number
  percent: number
  eligible: boolean
  unrestricted: boolean
}

type WorkOrderRow = {
  id: number
  person_id: number
  contractor: string | null
  property_id: number
  property: string | null
  position_id: number
  position: string | null
  pay_rate: number
  bill_rate: number
  ot_pay_rate: number
  ot_bill_rate: number
  status: string
  is_temporary_assignment: boolean
  direct_hire: DirectHire | null
  start_date: string
  end_date: string | null
}
type Option = { id: number; name: string }
/** An open PM staffing request a transfer can fill. */
type StaffingRequest = { id: number; property_id: number; position_id: number; summary: string; label: string }
type Catalogs = { properties: Option[]; positions: Option[]; recruiters: Option[]; moreStaffRequests?: StaffingRequest[] }
type Filters = {
  search: string
  status: string
  property_id: number | null
  sort: string
  direction: string
  per_page: number
}
type Props = {
  workOrders: WorkOrderRow[]
  pagination: PaginationMeta
  filters: Filters
  statuses: { value: string; label: string }[]
  /** Rows per status under the other filters; `all` is every status. */
  counts: Record<string, number>
  /** The work order open in the side panel (?panel=<id>), or null. */
  panel: WorkOrderPanelData | null
  catalogs: Catalogs
  can: { create: boolean; edit: boolean; close: boolean; transfer: boolean; temp: boolean }
}

/** Drop empties so the URL carries only what is actually filtering. */
const queryFrom = (filters: Filters, page: number): Record<string, string> => {
  const params: Record<string, string> = {}
  if (filters.search !== '') params.search = filters.search
  if (filters.status !== 'active') params.status = filters.status
  if (filters.property_id) params.property_id = String(filters.property_id)
  if (filters.sort !== 'created_at') params.sort = filters.sort
  if (filters.direction !== 'desc') params.direction = filters.direction
  if (filters.per_page !== 25) params.per_page = String(filters.per_page)
  if (page > 1) params.page = String(page)
  return params
}

const money = (cents: number) => `$${(cents / 100).toFixed(2)}`
// Noon, so no timezone offset can push the date to a neighbouring day.
const longDate = (d: string) => new Date(d + 'T12:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
const shortDate = (d: string) => new Date(d + 'T12:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
const statusBadge = (s: string) =>
  s === 'active' ? 'bg-success/15 text-success' : s === 'closed' ? 'bg-secondary/15 text-secondary' : 'bg-warning/15 text-warning'

/** Running first, ended last. */
const STATUS_ORDER = ['active', 'suspended', 'closed']

type ModalState = { kind: 'transfer' | 'temp'; wo: WorkOrderRow } | null

const columnHelper = createColumnHelper<WorkOrderRow>()

const Page = ({ workOrders, pagination, filters, statuses, counts, panel, catalogs, can }: Props) => {
  const [modal, setModal] = useState<ModalState>(null)
  // "Transfer someone in" on a staffing request lands here with
  // ?staffing_request=<id>: the Transfer form then starts on that request.
  const [fillingRequest, setFillingRequest] = useState<StaffingRequest | null>(() => {
    if (typeof window === 'undefined') return null
    const id = Number(new URLSearchParams(window.location.search).get('staffing_request'))
    return (catalogs.moreStaffRequests ?? []).find((r) => r.id === id) ?? null
  })
  const [search, setSearch] = useState(filters.search)

  /**
   * Every control routes through here: the query string is the state, so a
   * filtered view is a link someone can send and Back steps through filters.
   * Only the props that actually change come back down the wire.
   */
  const visit = (changes: Partial<Filters>, page = 1, replace = false) => {
    router.get('/admin/work-orders', queryFrom({ ...filters, ...changes }, page), {
      preserveState: true,
      preserveScroll: true,
      replace,
      // A filter or page change closes the panel: its row may not be on screen any more.
      only: ['workOrders', 'pagination', 'filters', 'counts', 'panel'],
    })
  }

  /**
   * The panel lives in the URL (?panel=<id>), so an open work order is a link
   * and Back closes it. Only the panel's data comes down; the list stays.
   */
  const showPanel = (id: number | null) => {
    router.get(
      '/admin/work-orders',
      { ...queryFrom(filters, pagination.current_page), ...(id === null ? {} : { panel: String(id) }) },
      { preserveState: true, preserveScroll: true, only: ['panel'] },
    )
  }

  /** The panel's work order in the shape the Transfer and Temporary dialogs take. */
  const panelRow = (w: WorkOrderPanelData): WorkOrderRow => ({
    id: w.id,
    person_id: w.person_id,
    contractor: w.contractor,
    property_id: w.property_id,
    property: w.property,
    position_id: w.position_id,
    position: w.position,
    pay_rate: w.pay_rate,
    bill_rate: w.bill_rate,
    ot_pay_rate: w.ot_pay_rate,
    ot_bill_rate: w.ot_bill_rate,
    status: w.status,
    is_temporary_assignment: w.is_temporary_assignment,
    direct_hire: w.direct_hire,
    start_date: w.start_date,
    end_date: w.end_date,
  })

  // Debounced so typing does not fire a request per keystroke; replaces
  // history rather than pushing, so Back does not walk the search letter by
  // letter.
  const typed = useRef(false)
  useEffect(() => {
    if (!typed.current || search === filters.search) return
    const timer = setTimeout(() => visit({ search }, 1, true), 300)
    return () => clearTimeout(timer)
  }, [search]) // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => setSearch(filters.search), [filters.search])

  const sorting: SortingState = useMemo(
    () => (filters.sort === 'created_at' ? [] : [{ id: filters.sort, desc: filters.direction === 'desc' }]),
    [filters.sort, filters.direction],
  )

  // Preline binds its dropdowns to the DOM it can see; rows re-rendered by a
  // filter or page change need binding again.
  useEffect(() => {
    window.HSStaticMethods?.autoInit()
  }, [workOrders])

  const closeWorkOrder = (w: WorkOrderRow) =>
    confirmAction({
      title: 'Close work order',
      message: (
        <>
          End <strong>{w.contractor}</strong>&apos;s placement as {w.position} at <strong>{w.property}</strong> today? They can no longer clock in on it; its
          hours and invoices stay.
        </>
      ),
      confirmLabel: 'Close Work Order',
      onConfirm: () => router.post(`/admin/work-orders/${w.id}/close`, {}, { preserveScroll: true }),
    })

  const columns = useMemo(
    () => [
      columnHelper.accessor('contractor', {
        header: 'Contractor',
        cell: ({ row }) => (
          <Link href={`/admin/people/${row.original.person_id}`} className="hover:text-primary text-default-900 font-semibold">
            {row.original.contractor}
          </Link>
        ),
      }),
      columnHelper.accessor('property', {
        header: 'Property',
        cell: ({ row }) => (
          <Link href={`/admin/properties/${row.original.property_id}`} className="hover:text-primary">
            {row.original.property}
          </Link>
        ),
      }),
      columnHelper.accessor('position', {
        header: 'Position',
      }),
      columnHelper.accessor('pay_rate', {
        header: 'Pay',
        cell: ({ row }) => <span className="tabular-nums">{money(row.original.pay_rate)}</span>,
      }),
      columnHelper.accessor('bill_rate', {
        header: 'Bill',
        cell: ({ row }) => <span className="tabular-nums">{money(row.original.bill_rate)}</span>,
      }),
      columnHelper.accessor('start_date', {
        header: 'Started',
        cell: ({ row }) => <span className="whitespace-nowrap">{longDate(row.original.start_date)}</span>,
      }),
      columnHelper.accessor('direct_hire', {
        header: 'Toward direct hire',
        enableSorting: false,
        cell: ({ row }) => {
          const w = row.original
          const dh = w.direct_hire
          // A placement that has ended is past the question of direct hire.
          if (w.status === 'closed') return <span className="text-default-400 whitespace-nowrap">{w.end_date ? `Ended ${shortDate(w.end_date)}` : 'Ended'}</span>
          if (dh === null) return <span className="text-default-400">—</span>
          if (dh.unrestricted) return <span className="badge badge-label bg-light text-default-600">No threshold</span>
          if (dh.eligible) return <span className="badge badge-label bg-success/15 text-success">Eligible</span>

          return (
            <div className="min-w-32">
              <span className="text-default-400 mb-1 block text-xs">
                {Math.round(dh.worked_hours).toLocaleString()} of {Math.round(dh.threshold_hours).toLocaleString()} hrs
              </span>
              <span
                className="bg-default-100 flex h-1.5 w-full overflow-hidden rounded"
                role="progressbar"
                aria-label="Progress toward direct-hire eligibility"
                aria-valuenow={dh.percent}
                aria-valuemin={0}
                aria-valuemax={100}>
                <span
                  className="bg-primary flex flex-col justify-center overflow-hidden transition duration-500"
                  style={{ width: `${Math.max(2, dh.percent)}%` }}
                />
              </span>
            </div>
          )
        },
      }),
      columnHelper.accessor('status', {
        header: 'Status',
        cell: ({ row }) => (
          <span className="whitespace-nowrap">
            <span className={cn('badge badge-label capitalize', statusBadge(row.original.status))}>{row.original.status}</span>
            {row.original.is_temporary_assignment && <span className="badge badge-label bg-info/15 text-info ms-1">Temporary</span>}
          </span>
        ),
      }),
      {
        id: 'actions',
        header: 'Actions',
        enableSorting: false,
        cell: ({ row }: { row: TableRow<WorkOrderRow> }) => {
          const w = row.original
          const active = w.status === 'active'
          const menu = [
            active && can.transfer && { label: 'Transfer or change position', icon: 'arrows-exchange', onClick: () => setModal({ kind: 'transfer', wo: w }) },
            active && can.temp && !w.is_temporary_assignment && { label: 'Temporary assignment', icon: 'clock-share', onClick: () => setModal({ kind: 'temp', wo: w }) },
            w.status !== 'closed' && can.close && { label: 'Close work order', icon: 'circle-x', danger: true, onClick: () => closeWorkOrder(w) },
          ].filter(Boolean) as { label: string; icon: string; danger?: boolean; onClick: () => void }[]

          return (
            <div className="flex items-center justify-end gap-1.5 whitespace-nowrap">
              {can.edit && (
                <Link href={`/admin/work-orders/${w.id}/edit`} className="btn btn-sm border-default-300 hover:border-default-400 border">
                  <Icon icon="edit" className="size-4" /> Edit
                </Link>
              )}
              {menu.length > 0 && (
                <div className="hs-dropdown [--placement:bottom-right] relative inline-flex">
                  <button
                    type="button"
                    className="hs-dropdown-toggle btn btn-icon border-default-300 hover:border-default-400 border"
                    aria-haspopup="menu"
                    aria-expanded="false"
                    aria-label={`More actions for ${w.contractor}`}>
                    <Icon icon="dots" className="text-base" />
                  </button>
                  <div className="hs-dropdown-menu min-w-56" role="menu">
                    {menu.map((item) => (
                      <button
                        key={item.label}
                        type="button"
                        role="menuitem"
                        className={cn('dropdown-item w-full', item.danger && 'text-danger')}
                        onClick={item.onClick}>
                        <Icon icon={item.icon} className="size-4" /> {item.label}
                      </button>
                    ))}
                  </div>
                </div>
              )}
            </div>
          )
        },
      },
    ],
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [can.transfer, can.temp, can.close, can.edit],
  )

  const table = useReactTable({
    data: workOrders,
    columns,
    state: { sorting },
    // The database orders and slices; the table only renders what it is given.
    manualSorting: true,
    manualPagination: true,
    pageCount: pagination.last_page,
    onSortingChange: (updater) => {
      const next = typeof updater === 'function' ? updater(sorting) : updater
      const [first] = next
      visit(first ? { sort: first.id, direction: first.desc ? 'desc' : 'asc' } : { sort: 'created_at', direction: 'desc' })
    },
    getCoreRowModel: getCoreRowModel(),
  })

  return (
    <>
      <Head title="Work Orders" />
      <PageBreadcrumb title="Work Orders" subtitle="Operations" />

      {fillingRequest && (
        <div className="bg-primary/10 text-primary mb-4 flex flex-wrap items-center justify-between gap-2 rounded-md px-4 py-3 text-sm">
          <span>
            Transferring someone in for <strong>{fillingRequest.summary}</strong>: find the contractor below and choose <strong>Transfer</strong> — it&apos;s linked to
            the request.
          </span>
          <span className="flex gap-4">
            <Link href="/admin/staffing-requests" className="font-medium hover:underline">Back to requests</Link>
            <button type="button" className="font-medium hover:underline" onClick={() => setFillingRequest(null)}>Dismiss</button>
          </span>
        </div>
      )}

      <div className="card">
        <div className="card-header">
          <div className="flex flex-wrap gap-3">
            <div className="input-icon-group">
              <Icon icon="search" className="input-icon" />
              <input
                className="form-input w-80 max-w-full"
                placeholder="Search contractor, property or position…"
                aria-label="Search work orders"
                value={search}
                onChange={(e) => {
                  typed.current = true
                  setSearch(e.target.value)
                }}
              />
            </div>

            {can.create && (
              <Link href="/admin/work-orders/create" className="btn bg-primary hover:bg-primary-hover text-white">
                <Icon icon="plus" />
                Add Work Order
              </Link>
            )}
          </div>

          <div className="flex flex-wrap items-center gap-3 md:flex-nowrap">
            <div className="bg-light inline-flex rounded-lg p-0.5" role="group" aria-label="Show work orders">
              {[...[...statuses].sort((a, b) => STATUS_ORDER.indexOf(a.value) - STATUS_ORDER.indexOf(b.value)), { value: 'all', label: 'All' }].map((st) => (
                <button
                  key={st.value}
                  type="button"
                  aria-pressed={filters.status === st.value}
                  onClick={() => visit({ status: st.value })}
                  className={cn(
                    'rounded-md px-3 py-1 text-sm whitespace-nowrap',
                    filters.status === st.value ? 'bg-card text-default-900 font-semibold shadow-sm' : 'text-default-500',
                  )}>
                  {st.label} <span className="tabular-nums opacity-70">{counts[st.value] ?? 0}</span>
                </button>
              ))}
            </div>
            <select
              className="form-select w-auto min-w-40"
              value={filters.property_id ?? ''}
              onChange={(e) => visit({ property_id: e.target.value === '' ? null : Number(e.target.value) })}>
              <option value="">All properties</option>
              {catalogs.properties.map((property) => (
                <option key={property.id} value={property.id}>
                  {property.name}
                </option>
              ))}
            </select>
            <select className="form-select w-20" value={filters.per_page} onChange={(e) => visit({ per_page: Number(e.target.value) })}>
              {[10, 25, 50].map((size) => (
                <option key={size}>{size}</option>
              ))}
            </select>
          </div>
        </div>

        <DataTable
          table={table}
          emptyMessage={counts.all ? 'No work orders match.' : 'No work orders yet.'}
          onRowClick={(w) => showPanel(w.id)}
        />

        {pagination.total > 0 && (
          <div className="card-footer">
            <ServerPagination meta={pagination} itemsName="work orders" onPageChange={(page) => visit({}, page)} />
          </div>
        )}
      </div>

      {panel && (
        <WorkOrderPanel
          workOrder={panel}
          onClose={() => showPanel(null)}
          onTransfer={() => setModal({ kind: 'transfer', wo: panelRow(panel) })}
          onTemp={() => setModal({ kind: 'temp', wo: panelRow(panel) })}
          onCloseWorkOrder={() => closeWorkOrder(panelRow(panel))}
          escapeDisabled={modal !== null}
        />
      )}

      {modal?.kind === 'transfer' && <TransferModal wo={modal.wo} catalogs={catalogs} filling={fillingRequest} onClose={() => setModal(null)} />}
      {modal?.kind === 'temp' && <TempModal wo={modal.wo} catalogs={catalogs} onClose={() => setModal(null)} />}
    </>
  )
}

// Fetch the Bible rate for a (property, position) and fill the form's rate fields.
const useRateLookup = (setData: (key: string, value: string) => void) => {
  return (propertyId: number | string, positionId: number | string) => {
    if (!propertyId || !positionId) return
    fetch(`/admin/work-orders/rate-lookup?property_id=${propertyId}&position_id=${positionId}`, { headers: { Accept: 'application/json' } })
      .then((r) => (r.ok ? r.json() : null))
      .then((rate) => {
        if (!rate) return
        setData('pay_rate', String(rate.pay_rate / 100))
        setData('bill_rate', String(rate.bill_rate / 100))
        setData('ot_pay_rate', String(rate.ot_pay_rate / 100))
        setData('ot_bill_rate', String(rate.ot_bill_rate / 100))
      })
      .catch(() => undefined)
  }
}

const RateFields = ({ data, setData }: { data: any; setData: (k: any, v: any) => void }) => (
  <div className="grid grid-cols-2 gap-3">
    {(['pay_rate', 'bill_rate', 'ot_pay_rate', 'ot_bill_rate'] as const).map((k) => (
      <div key={k}>
        <label className="form-label capitalize">{k.replaceAll('_', ' ')} ($/hr)</label>
        <input type="number" step="0.01" min="0" className="form-input" value={data[k]} onChange={(e) => setData(k, e.target.value)} required />
      </div>
    ))}
  </div>
)

const Shell = ({ title, subtitle, onClose, children }: { title: string; subtitle: string; onClose: () => void; children: React.ReactNode }) => (
  <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
    <div className="card w-full max-w-lg" onClick={(e) => e.stopPropagation()}>
      <div className="card-header">
        <div>
          <h4 className="card-title">{title}</h4>
          <p className="text-default-500 text-sm">{subtitle}</p>
        </div>
      </div>
      <div className="card-body max-h-[75vh] overflow-y-auto">{children}</div>
    </div>
  </div>
)

const TransferModal = ({ wo, catalogs, filling, onClose }: { wo: WorkOrderRow; catalogs: Catalogs; filling: StaffingRequest | null; onClose: () => void }) => {
  const { data, setData, post, processing, errors } = useForm({
    effective_date: new Date().toISOString().slice(0, 10),
    new_property_id: (filling?.property_id ?? wo.property_id) as number | string,
    new_position_id: (filling?.position_id ?? wo.position_id) as number | string,
    more_staff_request_id: (filling?.id ?? '') as number | string,
    new_recruiter_id: '' as number | string,
    pay_rate: String(wo.pay_rate / 100),
    bill_rate: String(wo.bill_rate / 100),
    ot_pay_rate: String(wo.ot_pay_rate / 100),
    ot_bill_rate: String(wo.ot_bill_rate / 100),
    reason: '',
    notes: '',
  })
  const lookup = useRateLookup(setData as any)
  // Starting from a staffing request: price the move at the new property's rates.
  useEffect(() => {
    if (filling) lookup(filling.property_id, filling.position_id)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  // Open requests at the property they're moving to — a transfer can fill one.
  const requestsHere = (catalogs.moreStaffRequests ?? []).filter((r) => String(r.property_id) === String(data.new_property_id))
  const pickProperty = (propertyId: string) => {
    setData((d) => ({ ...d, new_property_id: propertyId, more_staff_request_id: '' }))
    lookup(propertyId, data.new_position_id)
  }

  const submit = (e: FormEvent) => {
    e.preventDefault()
    post(`/admin/work-orders/${wo.id}/transfer`, { preserveScroll: true, onSuccess: onClose })
  }

  return (
    <Shell title="Transfer / Position Change" subtitle={`${wo.contractor} — currently ${wo.property}`} onClose={onClose}>
      <form onSubmit={submit} className="space-y-4">
        <div className="grid grid-cols-2 gap-3">
          <div>
            <label className="form-label">New property</label>
            <select className="form-select" value={data.new_property_id} onChange={(e) => pickProperty(e.target.value)}>
              {catalogs.properties.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
            </select>
          </div>
          <div>
            <label className="form-label">New position</label>
            <select className="form-select" value={data.new_position_id} onChange={(e) => { setData('new_position_id', e.target.value); lookup(data.new_property_id, e.target.value) }}>
              {catalogs.positions.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
            </select>
          </div>
        </div>
        <div className="grid grid-cols-2 gap-3">
          <div>
            <label className="form-label">Effective date</label>
            <input type="date" className="form-input" value={data.effective_date} onChange={(e) => setData('effective_date', e.target.value)} required />
          </div>
          <div>
            <label className="form-label">New recruiter (optional)</label>
            <select className="form-select" value={data.new_recruiter_id} onChange={(e) => setData('new_recruiter_id', e.target.value)}>
              <option value="">— keep current —</option>
              {catalogs.recruiters.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
            </select>
          </div>
        </div>
        <RateFields data={data} setData={setData} />
        {requestsHere.length > 0 && (
          <div>
            <label className="form-label">Fills a staffing request (optional)</label>
            <select className="form-select" value={data.more_staff_request_id} onChange={(e) => setData('more_staff_request_id', e.target.value)}>
              <option value="">No — not for a request</option>
              {requestsHere.map((r) => (
                <option key={r.id} value={r.id}>{r.label}</option>
              ))}
            </select>
            <p className="text-default-400 mt-1 text-xs">Counts toward the request, and its property manager is told who is coming.</p>
            {errors.more_staff_request_id && <p className="text-danger mt-1 text-sm">{errors.more_staff_request_id}</p>}
          </div>
        )}
        <div>
          <label className="form-label">Reason</label>
          <input className="form-input" value={data.reason} onChange={(e) => setData('reason', e.target.value)} required />
          {errors.reason && <p className="text-danger mt-1 text-sm">{errors.reason}</p>}
        </div>
        <div className="flex justify-end gap-2">
          <button type="button" className="btn btn-light px-4 py-2" onClick={onClose}>Cancel</button>
          <button type="submit" className="btn bg-primary hover:bg-primary-hover px-4 py-2 font-semibold text-white" disabled={processing}>Apply Transfer</button>
        </div>
      </form>
    </Shell>
  )
}

const TempModal = ({ wo, catalogs, onClose }: { wo: WorkOrderRow; catalogs: Catalogs; onClose: () => void }) => {
  const { data, setData, post, processing, errors } = useForm({
    new_property_id: '' as number | string,
    home_property_id: wo.property_id,
    position_id: wo.position_id as number | string,
    start_date: new Date().toISOString().slice(0, 10),
    end_date: '',
    pay_rate: String(wo.pay_rate / 100),
    bill_rate: String(wo.bill_rate / 100),
    ot_pay_rate: String(wo.ot_pay_rate / 100),
    ot_bill_rate: String(wo.ot_bill_rate / 100),
    reason: '',
  })
  const lookup = useRateLookup(setData as any)

  const submit = (e: FormEvent) => {
    e.preventDefault()
    post(`/admin/work-orders/${wo.id}/temporary-assignment`, { preserveScroll: true, onSuccess: onClose })
  }

  return (
    <Shell title="Temporary Assignment" subtitle={`${wo.contractor} — home: ${wo.property}`} onClose={onClose}>
      <form onSubmit={submit} className="space-y-4">
        <div className="grid grid-cols-2 gap-3">
          <div>
            <label className="form-label">Host property</label>
            <select className="form-select" value={data.new_property_id} onChange={(e) => { setData('new_property_id', e.target.value); lookup(e.target.value, data.position_id) }} required>
              <option value="">Select…</option>
              {catalogs.properties.filter((p) => p.id !== wo.property_id).map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
            </select>
            {errors.new_property_id && <p className="text-danger mt-1 text-sm">{errors.new_property_id}</p>}
          </div>
          <div>
            <label className="form-label">Position</label>
            <select className="form-select" value={data.position_id} onChange={(e) => { setData('position_id', e.target.value); lookup(data.new_property_id, e.target.value) }}>
              {catalogs.positions.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
            </select>
          </div>
        </div>
        <div className="grid grid-cols-2 gap-3">
          <div>
            <label className="form-label">Start date</label>
            <input type="date" className="form-input" value={data.start_date} onChange={(e) => setData('start_date', e.target.value)} required />
          </div>
          <div>
            <label className="form-label">End date</label>
            <input type="date" className="form-input" value={data.end_date} onChange={(e) => setData('end_date', e.target.value)} required />
            {errors.end_date && <p className="text-danger mt-1 text-sm">{errors.end_date}</p>}
          </div>
        </div>
        <RateFields data={data} setData={setData} />
        <div>
          <label className="form-label">Reason</label>
          <input className="form-input" value={data.reason} onChange={(e) => setData('reason', e.target.value)} required />
        </div>
        <div className="flex justify-end gap-2">
          <button type="button" className="btn btn-light px-4 py-2" onClick={onClose}>Cancel</button>
          <button type="submit" className="btn bg-primary hover:bg-primary-hover px-4 py-2 font-semibold text-white" disabled={processing}>Create Temp Assignment</button>
        </div>
      </form>
    </Shell>
  )
}

export default Page
