import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, router } from '@inertiajs/react'
import { useState } from 'react'
import ReportTable from './components/ReportTable'

type Row = {
  month: string
  property: string
  invoice_count: number
  work_subtotal: number
  adjustment_total: number
  tax_amount: number
  invoiced_total: number
  payout_total: number
}

type Totals = Omit<Row, 'month' | 'property'>

type Props = {
  filters: { from: string; to: string; property_id: number | null }
  properties: { id: number; name: string }[]
  rows: Row[]
  totals: Totals
}

const money = (cents: number) => `$${(cents / 100).toLocaleString('en-US', { minimumFractionDigits: 2 })}`

const Page = ({ filters, properties, rows, totals }: Props) => {
  const [from, setFrom] = useState(filters.from)
  const [to, setTo] = useState(filters.to)
  const [propertyId, setPropertyId] = useState(filters.property_id ? String(filters.property_id) : '')

  const apply = (overrides: Record<string, string> = {}) => {
    const params: Record<string, string> = { from, to, property_id: propertyId, ...overrides }
    router.get(
      '/admin/reports/revenue',
      Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '')),
      { preserveState: true, preserveScroll: true },
    )
  }

  return (
    <>
      <Head title="Revenue by Property" />
      <PageBreadcrumb title="Revenue by Property" subtitle="Reports" />

      <div className="card">
        <div className="card-header">
          <div className="flex flex-wrap items-end gap-3">
            <div>
              <label className="form-label">From</label>
              <input type="month" className="form-input" value={from} onChange={(e) => setFrom(e.target.value)} />
            </div>
            <div>
              <label className="form-label">To</label>
              <input type="month" className="form-input" value={to} onChange={(e) => setTo(e.target.value)} />
            </div>
            <div>
              <label className="form-label">Property</label>
              <select
                className="form-select"
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
          <a href={`/admin/reports/revenue/export?from=${from}&to=${to}${propertyId ? `&property_id=${propertyId}` : ''}`} className="btn btn-light text-nowrap">
            Export Excel
          </a>
        </div>

        <ReportTable
          columns={[
            { label: 'Month' },
            { label: 'Property' },
            { label: 'Invoices', numeric: true },
            { label: 'Work', numeric: true },
            { label: 'Adjustments', numeric: true },
            { label: 'Tax', numeric: true },
            { label: 'Invoiced Total', numeric: true },
            { label: 'Payouts', numeric: true },
          ]}
          rows={rows.map((r) => [
            r.month,
            r.property,
            r.invoice_count,
            money(r.work_subtotal),
            money(r.adjustment_total),
            money(r.tax_amount),
            <span className="font-semibold">{money(r.invoiced_total)}</span>,
            money(r.payout_total),
          ])}
          totals={[
            'Total',
            '',
            totals.invoice_count,
            money(totals.work_subtotal),
            money(totals.adjustment_total),
            money(totals.tax_amount),
            money(totals.invoiced_total),
            money(totals.payout_total),
          ]}
          emptyMessage="No invoiced months in this range."
        />
      </div>
      <p className="text-default-400 mt-3 text-xs">
        Billed truth from frozen invoices (voided invoices excluded). Months are keyed by the payroll week's start date.
      </p>
    </>
  )
}

export default Page
