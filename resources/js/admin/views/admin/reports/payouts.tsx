import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, router } from '@inertiajs/react'
import ReportTable from './components/ReportTable'

type Row = {
  contractor: string
  property: string
  position: string
  regular_minutes: number
  overtime_minutes: number
  training_minutes: number
  total_pay: number
}

type Props = {
  filters: { week: string | null; property_id: number | null }
  weeks: string[]
  properties: { id: number; name: string }[]
  rows: Row[]
  totals: Pick<Row, 'regular_minutes' | 'overtime_minutes' | 'training_minutes' | 'total_pay'>
}

const money = (cents: number) => `$${(cents / 100).toLocaleString('en-US', { minimumFractionDigits: 2 })}`
const hours = (minutes: number) => (minutes / 60).toFixed(1)

const weekLabel = (week: string) => {
  const start = new Date(`${week}T00:00:00`)
  const end = new Date(start)
  end.setDate(end.getDate() + 6)
  const fmt = (d: Date) => d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
  return `${fmt(start)} – ${fmt(end)}, ${end.getFullYear()}`
}

const Page = ({ filters, weeks, properties, rows, totals }: Props) => {
  const apply = (week: string, propertyId: string) => {
    const params: Record<string, string> = { week, property_id: propertyId }
    router.get(
      '/admin/reports/payouts',
      Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '')),
      { preserveState: true, preserveScroll: true },
    )
  }

  const week = filters.week ?? ''
  const propertyId = filters.property_id ? String(filters.property_id) : ''

  return (
    <>
      <Head title="Payouts by Contractor" />
      <PageBreadcrumb title="Payouts by Contractor" subtitle="Reports" />

      <div className="card">
        <div className="card-header">
          <div className="flex flex-wrap items-end gap-3">
            <div>
              <label className="form-label">Payroll week</label>
              <select className="form-select" value={week} onChange={(e) => apply(e.target.value, propertyId)}>
                {weeks.length === 0 && <option value="">No weeks yet</option>}
                {weeks.map((w) => (
                  <option key={w} value={w}>
                    {weekLabel(w)}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className="form-label">Property</label>
              <select className="form-select" value={propertyId} onChange={(e) => apply(week, e.target.value)}>
                <option value="">All properties</option>
                {properties.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.name}
                  </option>
                ))}
              </select>
            </div>
          </div>
          <div className="flex gap-2">
            <a
              href={`/admin/reports/payouts/export?week=${week}${propertyId ? `&property_id=${propertyId}` : ''}`}
              className="btn btn-light text-nowrap"
            >
              Export Excel
            </a>
            <a
              href={`/admin/reports/payouts/pdf?week=${week}${propertyId ? `&property_id=${propertyId}` : ''}`}
              className="btn btn-light text-nowrap"
            >
              PDF
            </a>
          </div>
        </div>

        <ReportTable
          columns={[
            { label: 'Contractor' },
            { label: 'Property' },
            { label: 'Position' },
            { label: 'Regular (h)', numeric: true },
            { label: 'Overtime (h)', numeric: true },
            { label: 'Training (h)', numeric: true },
            { label: 'Total Pay', numeric: true },
          ]}
          rows={rows.map((r) => [
            <span className="font-semibold">{r.contractor}</span>,
            r.property,
            r.position,
            hours(r.regular_minutes),
            hours(r.overtime_minutes),
            hours(r.training_minutes),
            <span className="font-semibold">{money(r.total_pay)}</span>,
          ])}
          totals={[
            'Total',
            '',
            '',
            hours(totals.regular_minutes),
            hours(totals.overtime_minutes),
            hours(totals.training_minutes),
            money(totals.total_pay),
          ]}
          emptyMessage="No hours recorded for this week."
        />
      </div>
      <p className="text-default-400 mt-3 text-xs">
        Worked-hour payouts at snapshot rates. Inventory charge deductions and incentives are applied on the property grid, not here.
      </p>
    </>
  )
}

export default Page
