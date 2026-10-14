import PageBreadcrumb from '@/components/PageBreadcrumb'
import Icon from '@/components/wrappers/Icon'
import { Head, Link, useForm } from '@inertiajs/react'
import { FormEvent, ReactNode, useMemo, useState } from 'react'

type Period = { id: number; label: string; week_start: string; ended: boolean; people: number; holidays: string[] }
type ImportProperty = { id: number; name: string; periods: Period[] }

type Props = {
  properties: ImportProperty[]
  prefill: { property_id: number | null; payroll_period_id: number | null }
}

const Field = ({ id, label, error, children }: { id: string; label: string; error?: string; children: ReactNode }) => (
  <div>
    <label htmlFor={id} className="form-label">
      {label}
    </label>
    {children}
    {error && <p className="text-danger mt-1 text-sm">{error}</p>}
  </div>
)

const Step = ({ n, title, children }: { n: number; title: string; children: ReactNode }) => (
  <div className="card">
    <div className="card-body flex gap-4">
      <span className="bg-default-900 flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white" aria-hidden="true">
        {n}
      </span>
      <div className="min-w-0 flex-1">
        <h4 className="card-title">{title}</h4>
        {children}
      </div>
    </div>
  </div>
)

/**
 * New hour import: pick the hotel and week, download that week's file
 * (already filled in with everyone working there), then upload it once the
 * hours are typed in. A hotel's own payroll export works too.
 */
/** The week hours usually come in for: the latest one that has ended. */
const lastWeek = (p: ImportProperty | undefined) => String(p?.periods.find((w) => w.ended)?.id ?? '')

const Page = ({ properties, prefill }: Props) => {
  const [propertyId, setPropertyId] = useState<string>(prefill.property_id ? String(prefill.property_id) : properties.length === 1 ? String(properties[0].id) : '')

  const { data, setData, post, processing, errors, progress } = useForm<{
    property_id: string
    payroll_period_id: string
    file: File | null
  }>({
    property_id: propertyId,
    payroll_period_id: prefill.payroll_period_id ? String(prefill.payroll_period_id) : lastWeek(properties.find((p) => String(p.id) === propertyId)),
    file: null,
  })

  const property = useMemo(() => properties.find((p) => String(p.id) === propertyId), [properties, propertyId])
  const periods = property?.periods ?? []
  const period = periods.find((p) => String(p.id) === data.payroll_period_id)

  const onProperty = (value: string) => {
    setPropertyId(value)
    setData((prev) => ({ ...prev, property_id: value, payroll_period_id: lastWeek(properties.find((p) => String(p.id) === value)) }))
  }

  const submit = (e: FormEvent) => {
    e.preventDefault()
    post('/admin/imports', { forceFormData: true })
  }

  const templateHref =
    property && period ? `/admin/imports/template?${new URLSearchParams({ property_id: String(property.id), payroll_period_id: String(period.id) }).toString()}` : null

  return (
    <>
      <Head title="New Import" />
      <PageBreadcrumb title="New import" subtitle="Hour Imports" />

      {properties.length === 0 ? (
        <div className="card max-w-2xl">
          <div className="card-body">
            <p className="text-default-500">
              No import-only properties are available to you. Set a property's <strong>Time Source</strong> to “Import” in the Property Bible first.
            </p>
          </div>
        </div>
      ) : (
        <>
          <p className="text-default-500 mb-5 max-w-3xl">
            For hotels that send their hours instead of clocking in. Download the week's file, type each person's hours, then upload it here.
          </p>

          <div className="grid items-start gap-5 xl:grid-cols-3">
            <form onSubmit={submit} className="space-y-5 xl:col-span-2">
              <div className="card">
                <div className="card-body space-y-4">
                  <h4 className="card-title">Hotel and week</h4>
                  <div className="grid gap-4 sm:grid-cols-2">
                    <Field id="import-property" label="Hotel" error={errors.property_id}>
                      <select id="import-property" className="form-select" value={data.property_id} onChange={(e) => onProperty(e.target.value)} required>
                        <option value="">Select a hotel…</option>
                        {properties.map((p) => (
                          <option key={p.id} value={p.id}>
                            {p.name}
                          </option>
                        ))}
                      </select>
                    </Field>
                    <Field id="import-period" label="Week" error={errors.payroll_period_id}>
                      <select
                        id="import-period"
                        className="form-select"
                        value={data.payroll_period_id}
                        onChange={(e) => setData('payroll_period_id', e.target.value)}
                        disabled={!propertyId}
                        required
                      >
                        <option value="">{propertyId ? 'Select a week…' : 'Pick a hotel first'}</option>
                        {periods.map((p) => (
                          <option key={p.id} value={p.id}>
                            {p.label}
                          </option>
                        ))}
                      </select>
                      {propertyId && periods.length === 0 && <p className="text-warning mt-1 text-sm">No open weeks for this hotel.</p>}
                    </Field>
                  </div>
                  {period && period.holidays.length > 0 && (
                    <div className="flex flex-wrap items-center gap-2">
                      {period.holidays.map((h) => (
                        <span key={h} className="badge badge-label bg-warning/15 text-warning">
                          {h}
                        </span>
                      ))}
                      <span className="text-default-500 text-sm">Hours that day are paid and billed as holiday hours.</span>
                    </div>
                  )}
                </div>
              </div>

              <Step n={1} title="Download this week's file">
                <p className="text-default-500 mt-1 mb-4">
                  {period && property
                    ? period.people > 0
                      ? `Already filled in with the ${period.people === 1 ? 'person' : `${period.people} people`} working at ${property.name} this week: name, hotel ID, position and pay rate. You only type hours.`
                      : `Nobody has a work order at ${property.name} this week, so the file comes with empty rows to fill in.`
                    : 'Pick the hotel and week first.'}
                </p>
                {templateHref ? (
                  <a href={templateHref} className="btn border-default-300 hover:border-default-400 border">
                    <Icon icon="download" className="size-4" /> Download the {property?.name} file
                  </a>
                ) : (
                  <button type="button" className="btn border-default-300 border opacity-50" disabled>
                    <Icon icon="download" className="size-4" /> Download the file
                  </button>
                )}
              </Step>

              <Step n={2} title="Upload the filled-in file">
                <p className="text-default-500 mt-1 mb-4">Our file, or the hotel's own payroll export with weekly totals. Nothing is saved until you review it.</p>
                <Field id="import-file" label="Excel file (.xlsx)" error={errors.file}>
                  <input id="import-file" type="file" className="form-input" accept=".xlsx,.xls" onChange={(e) => setData('file', e.target.files?.[0] ?? null)} required />
                  {progress && <progress value={progress.percentage} max="100" className="mt-2 w-full" />}
                </Field>
                <div className="mt-4 flex items-center gap-3">
                  <button type="submit" className="btn bg-primary hover:bg-primary-hover font-semibold text-white" disabled={processing}>
                    Check the file
                  </button>
                  <Link href="/admin/imports" className="btn border-default-300 border">
                    Cancel
                  </Link>
                </div>
              </Step>
            </form>

            <div className="card">
              <div className="card-body">
                <h4 className="card-title">What the file needs</h4>
                <ul className="text-default-900 mt-3 list-disc space-y-2 ps-5 text-sm">
                  <li>One row per person.</li>
                  <li>
                    Hours for each day of the week, like <strong>7.5</strong> for 7 h 30 min. Leave days off blank.
                  </li>
                  <li>No overtime column: anything over 40 hours in the week becomes overtime on its own.</li>
                  <li>
                    Keep each person's <strong>hotel ID</strong>. It's how we know who is who.
                  </li>
                  <li>Someone new? Add a row with their name, hotel ID and position. You'll link or add them on the next step.</li>
                  <li>A different pay rate than their work order? You'll pick which one wins on the next step.</li>
                </ul>
                <p className="text-default-500 mt-4 text-sm">The file's second tab, "How to fill this in", says the same.</p>
              </div>
            </div>
          </div>
        </>
      )}
    </>
  )
}

export default Page
