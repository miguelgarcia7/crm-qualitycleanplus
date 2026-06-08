import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, Link, useForm } from '@inertiajs/react'
import { FormEvent, useMemo, useState } from 'react'

type Period = { id: number; label: string; week_start: string }
type ImportProperty = { id: number; name: string; periods: Period[] }

type Props = {
  properties: ImportProperty[]
  prefill: { property_id: number | null; payroll_period_id: number | null }
}

const Field = ({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) => (
  <div>
    <label className="form-label">{label}</label>
    {children}
    {error && <p className="text-danger mt-1 text-sm">{error}</p>}
  </div>
)

const Page = ({ properties, prefill }: Props) => {
  const [propertyId, setPropertyId] = useState<string>(prefill.property_id ? String(prefill.property_id) : '')

  const { data, setData, post, processing, errors, progress } = useForm<{
    property_id: string
    payroll_period_id: string
    file: File | null
  }>({
    property_id: prefill.property_id ? String(prefill.property_id) : '',
    payroll_period_id: prefill.payroll_period_id ? String(prefill.payroll_period_id) : '',
    file: null,
  })

  const periods = useMemo(() => properties.find((p) => String(p.id) === propertyId)?.periods ?? [], [properties, propertyId])

  const onProperty = (value: string) => {
    setPropertyId(value)
    setData((prev) => ({ ...prev, property_id: value, payroll_period_id: '' }))
  }

  const submit = (e: FormEvent) => {
    e.preventDefault()
    post('/admin/imports', { forceFormData: true })
  }

  return (
    <>
      <Head title="New Import" />
      <PageBreadcrumb title="New Import" subtitle="Hour Imports" />

      <div className="card mx-auto max-w-2xl rounded-2xl">
        <div className="card-body p-6">
          {properties.length === 0 ? (
            <p className="text-muted">
              No import-only properties are available to you. Set a property's <strong>Time Source</strong> to
              “Import” in the Property Bible first.
            </p>
          ) : (
            <form onSubmit={submit} className="space-y-5">
              <Field label="Property" error={errors.property_id}>
                <select className="form-select" value={data.property_id} onChange={(e) => onProperty(e.target.value)} required>
                  <option value="">Select a property…</option>
                  {properties.map((p) => (
                    <option key={p.id} value={p.id}>
                      {p.name}
                    </option>
                  ))}
                </select>
              </Field>

              <Field label="Payroll Period (week)" error={errors.payroll_period_id}>
                <select
                  className="form-select"
                  value={data.payroll_period_id}
                  onChange={(e) => setData('payroll_period_id', e.target.value)}
                  disabled={!propertyId}
                  required
                >
                  <option value="">{propertyId ? 'Select a week…' : 'Pick a property first'}</option>
                  {periods.map((period) => (
                    <option key={period.id} value={period.id}>
                      {period.label}
                    </option>
                  ))}
                </select>
                {propertyId && periods.length === 0 && (
                  <p className="text-warning mt-1 text-sm">No open payroll periods for this property.</p>
                )}
              </Field>

              <Field label="Excel File (.xlsx)" error={errors.file}>
                <input
                  type="file"
                  className="form-input"
                  accept=".xlsx,.xls"
                  onChange={(e) => setData('file', e.target.files?.[0] ?? null)}
                  required
                />
                {progress && <progress value={progress.percentage} max="100" className="mt-2 w-full" />}
              </Field>

              <div className="flex items-center gap-3">
                <button
                  type="submit"
                  className="btn bg-primary hover:bg-primary-hover px-6 py-2.5 font-semibold text-white"
                  disabled={processing}
                >
                  Parse File
                </button>
                <Link href="/admin/imports" className="btn btn-light px-6 py-2.5">
                  Cancel
                </Link>
              </div>
            </form>
          )}
        </div>
      </div>
    </>
  )
}

export default Page
