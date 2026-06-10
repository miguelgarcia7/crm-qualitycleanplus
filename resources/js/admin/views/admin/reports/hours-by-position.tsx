import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, router } from '@inertiajs/react'
import { useState } from 'react'
import ReportTable from './components/ReportTable'

type Row = {
  position: string
  regular_minutes: number
  overtime_minutes: number
  training_minutes: number
  total_minutes: number
  weeks: number
}

type Props = {
  filters: { from: string; to: string; property_id: number | null }
  properties: { id: number; name: string }[]
  rows: Row[]
  totals: Omit<Row, 'position' | 'weeks'>
}

const hours = (minutes: number) => (minutes / 60).toFixed(1)

const Page = ({ filters, properties, rows, totals }: Props) => {
  const [from, setFrom] = useState(filters.from)
  const [to, setTo] = useState(filters.to)
  const [propertyId, setPropertyId] = useState(filters.property_id ? String(filters.property_id) : '')

  const apply = (overrides: Record<string, string> = {}) => {
    const params: Record<string, string> = { from, to, property_id: propertyId, ...overrides }
    router.get(
      '/admin/reports/hours-by-position',
      Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '')),
      { preserveState: true, preserveScroll: true },
    )
  }

  return (
    <>
      <Head title="Hours by Position" />
      <PageBreadcrumb title="Hours by Position" subtitle="Reports" />

      <div className="card">
        <div className="card-header">
          <div className="flex flex-wrap items-end gap-3">
            <div>
              <label className="form-label">From</label>
              <input type="date" className="form-input" value={from} onChange={(e) => setFrom(e.target.value)} />
            </div>
            <div>
              <label className="form-label">To</label>
              <input type="date" className="form-input" value={to} onChange={(e) => setTo(e.target.value)} />
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
          <a
            href={`/admin/reports/hours-by-position/export?from=${from}&to=${to}${propertyId ? `&property_id=${propertyId}` : ''}`}
            className="btn btn-light text-nowrap"
          >
            Export Excel
          </a>
        </div>

        <ReportTable
          columns={[
            { label: 'Position' },
            { label: 'Regular (h)', numeric: true },
            { label: 'Overtime (h)', numeric: true },
            { label: 'Training (h)', numeric: true },
            { label: 'Total (h)', numeric: true },
            { label: 'Weeks', numeric: true },
          ]}
          rows={rows.map((r) => [
            r.position,
            hours(r.regular_minutes),
            hours(r.overtime_minutes),
            hours(r.training_minutes),
            <span className="font-semibold">{hours(r.total_minutes)}</span>,
            r.weeks,
          ])}
          totals={['Total', hours(totals.regular_minutes), hours(totals.overtime_minutes), hours(totals.training_minutes), hours(totals.total_minutes), '']}
          emptyMessage="No worked weeks in this range."
        />
      </div>
    </>
  )
}

export default Page
