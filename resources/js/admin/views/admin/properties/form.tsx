import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, Link, useForm } from '@inertiajs/react'
import { FormEvent } from 'react'

type Property = {
  id: number
  name: string
  pm_name: string | null
  pm_phone: string | null
  main_phone: string | null
  address: string | null
  city: string | null
  state: string | null
  zip: string | null
  timezone: string
  latitude: string | null
  longitude: string | null
  geofence_radius_meters: number
  closing_day: number | null
  tax_rate: string | null
  status: string
  time_source: string
}

type Props = {
  property: Property | null
  statuses: { value: string; label: string }[]
  timeSources: { value: string; label: string }[]
}

const TIMEZONES = [
  'America/Phoenix',
  'America/Los_Angeles',
  'America/Denver',
  'America/Chicago',
  'America/New_York',
  'America/Anchorage',
  'Pacific/Honolulu',
]

const Field = ({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) => (
  <div>
    <label className="form-label">{label}</label>
    {children}
    {error && <p className="text-danger mt-1 text-sm">{error}</p>}
  </div>
)

const Page = ({ property, statuses, timeSources }: Props) => {
  const editing = property !== null

  const { data, setData, post, patch, processing, errors } = useForm({
    name: property?.name ?? '',
    pm_name: property?.pm_name ?? '',
    pm_phone: property?.pm_phone ?? '',
    main_phone: property?.main_phone ?? '',
    address: property?.address ?? '',
    city: property?.city ?? '',
    state: property?.state ?? '',
    zip: property?.zip ?? '',
    timezone: property?.timezone ?? 'America/Phoenix',
    latitude: property?.latitude ?? '',
    longitude: property?.longitude ?? '',
    geofence_radius_meters: property?.geofence_radius_meters ?? 300,
    closing_day: property?.closing_day ?? '',
    tax_rate: property?.tax_rate ?? '0',
    status: property?.status ?? 'active',
    time_source: property?.time_source ?? 'clock_in',
  })

  const submit = (e: FormEvent) => {
    e.preventDefault()
    if (editing) {
      patch(`/admin/properties/${property.id}`)
    } else {
      post('/admin/properties')
    }
  }

  return (
    <>
      <Head title={editing ? 'Edit Property' : 'Add Property'} />
      <PageBreadcrumb title={editing ? 'Edit Property' : 'Add Property'} subtitle="Property Bible" />

      <div className="card rounded-2xl">
        <div className="card-body p-6">
          <form onSubmit={submit} className="space-y-6">
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
              <Field label="Name" error={errors.name}>
                <input className="form-input" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
              </Field>
              <Field label="Status" error={errors.status}>
                <select className="form-select" value={data.status} onChange={(e) => setData('status', e.target.value)}>
                  {statuses.map((s) => (
                    <option key={s.value} value={s.value}>
                      {s.label}
                    </option>
                  ))}
                </select>
              </Field>
              <Field label="PM Name" error={errors.pm_name}>
                <input className="form-input" value={data.pm_name} onChange={(e) => setData('pm_name', e.target.value)} />
              </Field>
              <Field label="PM Phone" error={errors.pm_phone}>
                <input className="form-input" value={data.pm_phone} onChange={(e) => setData('pm_phone', e.target.value)} />
              </Field>
              <Field label="Hotel Main Phone" error={errors.main_phone}>
                <input className="form-input" value={data.main_phone} onChange={(e) => setData('main_phone', e.target.value)} />
              </Field>
              <Field label="Address" error={errors.address}>
                <input className="form-input" value={data.address} onChange={(e) => setData('address', e.target.value)} />
              </Field>
              <Field label="City" error={errors.city}>
                <input className="form-input" value={data.city} onChange={(e) => setData('city', e.target.value)} />
              </Field>
              <Field label="State" error={errors.state}>
                <input className="form-input" value={data.state} onChange={(e) => setData('state', e.target.value)} />
              </Field>
              <Field label="Zip" error={errors.zip}>
                <input className="form-input" value={data.zip} onChange={(e) => setData('zip', e.target.value)} />
              </Field>
              <Field label="Timezone" error={errors.timezone}>
                <select className="form-select" value={data.timezone} onChange={(e) => setData('timezone', e.target.value)}>
                  {TIMEZONES.map((tz) => (
                    <option key={tz} value={tz}>
                      {tz}
                    </option>
                  ))}
                </select>
              </Field>
              <Field label="Latitude" error={errors.latitude}>
                <input className="form-input" value={data.latitude} onChange={(e) => setData('latitude', e.target.value)} placeholder="33.4484" />
              </Field>
              <Field label="Longitude" error={errors.longitude}>
                <input className="form-input" value={data.longitude} onChange={(e) => setData('longitude', e.target.value)} placeholder="-112.0740" />
              </Field>
              <Field label="Geofence Radius (m)" error={errors.geofence_radius_meters}>
                <input
                  type="number"
                  className="form-input"
                  value={data.geofence_radius_meters}
                  onChange={(e) => setData('geofence_radius_meters', Number(e.target.value))}
                />
              </Field>
              <Field label="Closing Day (1–31)" error={errors.closing_day}>
                <input type="number" min={1} max={31} className="form-input" value={data.closing_day} onChange={(e) => setData('closing_day', e.target.value)} />
              </Field>
              <Field label="Tax Rate (e.g. 0.0875)" error={errors.tax_rate}>
                <input className="form-input" value={data.tax_rate} onChange={(e) => setData('tax_rate', e.target.value)} />
              </Field>
              <Field label="Time Source" error={errors.time_source}>
                <select className="form-select" value={data.time_source} onChange={(e) => setData('time_source', e.target.value)}>
                  {timeSources.map((s) => (
                    <option key={s.value} value={s.value}>
                      {s.label}
                    </option>
                  ))}
                </select>
                <p className="text-default-400 mt-1 text-xs">Import = no clock-in; hours arrive via the weekly Excel import.</p>
              </Field>
            </div>

            <div className="flex items-center gap-3">
              <button type="submit" className="btn bg-primary hover:bg-primary-hover px-6 py-2.5 font-semibold text-white" disabled={processing}>
                {editing ? 'Save Changes' : 'Create Property'}
              </button>
              <Link href={editing ? `/admin/properties/${property.id}` : '/admin/properties'} className="btn btn-light px-6 py-2.5">
                Cancel
              </Link>
            </div>
          </form>
        </div>
      </div>
    </>
  )
}

export default Page
