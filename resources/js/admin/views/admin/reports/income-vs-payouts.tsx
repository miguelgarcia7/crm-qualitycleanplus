import PageBreadcrumb from '@/components/PageBreadcrumb'
import Icon from '@/components/wrappers/Icon'
import { Head, router } from '@inertiajs/react'
import { useState } from 'react'
import ReportTable from './components/ReportTable'

type Row = {
  property: string
  total_minutes: number
  total_bill: number
  total_pay: number
  margin: number
}

type Props = {
  filters: { from: string; to: string; property_id: number | null }
  properties: { id: number; name: string }[]
  rows: Row[]
  totals: Omit<Row, 'property'>
}

const money = (cents: number) => `$${(cents / 100).toLocaleString('en-US', { minimumFractionDigits: 2 })}`
const hours = (minutes: number) => (minutes / 60).toFixed(1)
const pct = (margin: number, bill: number) => (bill > 0 ? `${((margin / bill) * 100).toFixed(1)}%` : '—')

const Page = ({ filters, properties, rows, totals }: Props) => {
  const [from, setFrom] = useState(filters.from)
  const [to, setTo] = useState(filters.to)
  const [propertyId, setPropertyId] = useState(filters.property_id ? String(filters.property_id) : '')

  const apply = (overrides: Record<string, string> = {}) => {
    const params: Record<string, string> = { from, to, property_id: propertyId, ...overrides }
    router.get(
      '/admin/reports/income-vs-payouts',
      Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '')),
      { preserveState: true, preserveScroll: true },
    )
  }

  return (
    <>
      <Head title="Gross Income vs Payouts" />
      <PageBreadcrumb title="Gross Income vs Payouts" subtitle="Reports" />

      <div className="card">
        <div className="card-header">
          <div className="flex flex-wrap items-center gap-3">
            <span className="me-1 font-semibold text-nowrap">Filter By:</span>
            <label className="flex items-center gap-2">
              <span className="text-default-500 text-sm">From</span>
              <input type="date" className="form-input w-auto" value={from} onChange={(e) => setFrom(e.target.value)} />
            </label>
            <label className="flex items-center gap-2">
              <span className="text-default-500 text-sm">To</span>
              <input type="date" className="form-input w-auto" value={to} onChange={(e) => setTo(e.target.value)} />
            </label>
            <div className="input-icon-group">
              <Icon icon="building" className="input-icon" />
              <select
                className="form-select !w-auto min-w-44"
                value={propertyId}
                onChange={(e) => {
                  setPropertyId(e.target.value)
                  apply({ property_id: e.target.value })
                }}
              >
                <option value="">All properties</option>
                {properties.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.name}
                  </option>
                ))}
              </select>
            </div>
            <button type="button" className="btn bg-primary hover:bg-primary-hover text-white" onClick={() => apply()}>
              Apply
            </button>
          </div>
          <a
            href={`/admin/reports/income-vs-payouts/export?from=${from}&to=${to}${propertyId ? `&property_id=${propertyId}` : ''}`}
            className="btn btn-light text-nowrap"
          >
            <Icon icon="download" className="me-1 size-4" /> Export Excel
          </a>
        </div>

        <ReportTable
          columns={[
            { label: 'Property' },
            { label: 'Hours', numeric: true },
            { label: 'Billed', numeric: true },
            { label: 'Paid Out', numeric: true },
            { label: 'Margin', numeric: true },
            { label: 'Margin %', numeric: true },
          ]}
          rows={rows.map((r) => [
            r.property,
            hours(r.total_minutes),
            money(r.total_bill),
            money(r.total_pay),
            <span className={r.margin >= 0 ? 'text-success font-semibold' : 'text-danger font-semibold'}>{money(r.margin)}</span>,
            pct(r.margin, r.total_bill),
          ])}
          totals={[
            'Total',
            hours(totals.total_minutes),
            money(totals.total_bill),
            money(totals.total_pay),
            money(totals.margin),
            pct(totals.margin, totals.total_bill),
          ]}
          emptyMessage="No worked weeks in this range."
          footer={
            <span className="text-default-400 text-sm">
              {rows.length} propert{rows.length === 1 ? 'y' : 'ies'} · {filters.from} → {filters.to}
            </span>
          }
        />
      </div>
      <p className="text-default-400 mt-3 text-xs">
        Operational truth from weekly time rollups (worked hours at snapshot rates, before invoicing adjustments and tax).
      </p>
    </>
  )
}

export default Page
