import GeofenceMapCard from '@/components/GeofenceMapCard'
import { PROPERTY_TIMEZONES } from '@/config/constants'
import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, Link, useForm } from '@inertiajs/react'
import { FormEvent } from 'react'

/**
 * Add Property. Editing an existing property happens on its page's Settings
 * tab, a card at a time, so this form only ever creates.
 */
type Props = {
  statuses: { value: string; label: string }[]
  timeSources: { value: string; label: string }[]
  defaultDirectHireThresholdHours: number
}

const Field = ({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) => (
  <div>
    <label className="form-label">{label}</label>
    {children}
    {error && <p className="text-danger mt-1 text-sm">{error}</p>}
  </div>
)

const Page = ({ statuses, timeSources, defaultDirectHireThresholdHours }: Props) => {
  const { data, setData, post, processing, errors } = useForm({
    name: '',
    pm_name: '',
    pm_phone: '',
    main_phone: '',
    billing_email: '',
    address: '',
    city: '',
    state: '',
    zip: '',
    timezone: 'America/Phoenix',
    latitude: '',
    longitude: '',
    geofence_radius_meters: 300,
    qr_clock_enabled: false,
    closing_day: '',
    tax_rate: '0',
    direct_hire_threshold_hours: '',
    status: 'active',
    time_source: 'clock_in',
  })

  const submit = (e: FormEvent) => {
    e.preventDefault()
    post('/admin/properties')
  }

  return (
    <>
      <Head title="Add Property" />
      <PageBreadcrumb title="Add Property" subtitle="Property Bible" />

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
              <Field label="Billing Email" error={errors.billing_email}>
                <input
                  type="email"
                  className="form-input"
                  value={data.billing_email}
                  onChange={(e) => setData('billing_email', e.target.value)}
                  placeholder="ap@hotel.com"
                />
                <p className="text-default-400 mt-1 text-xs">Where invoices are sent by default.</p>
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
                  {PROPERTY_TIMEZONES.map((tz) => (
                    <option key={tz} value={tz}>
                      {tz}
                    </option>
                  ))}
                </select>
              </Field>
              <Field label="Week Ends On" error={errors.closing_day}>
                <select className="form-select" value={data.closing_day} onChange={(e) => setData('closing_day', e.target.value)}>
                  <option value="">Sunday (default — Mon–Sun week)</option>
                  <option value="1">Monday</option>
                  <option value="2">Tuesday</option>
                  <option value="3">Wednesday</option>
                  <option value="4">Thursday</option>
                  <option value="5">Friday</option>
                  <option value="6">Saturday</option>
                  <option value="7">Sunday</option>
                </select>
                <p className="text-default-400 mt-1 text-xs">
                  The property's closing day. Timesheet weeks start the next day — ends Wednesday → Thursday-to-Wednesday weeks.
                </p>
              </Field>
              <Field label="Tax Rate (e.g. 0.0875)" error={errors.tax_rate}>
                <input className="form-input" value={data.tax_rate} onChange={(e) => setData('tax_rate', e.target.value)} />
              </Field>
              <Field label="Direct-Hire Threshold (hours)" error={errors.direct_hire_threshold_hours}>
                <input
                  type="number"
                  min="0"
                  className="form-input"
                  placeholder={`Default ${defaultDirectHireThresholdHours}`}
                  value={data.direct_hire_threshold_hours}
                  onChange={(e) => setData('direct_hire_threshold_hours', e.target.value)}
                />
                <p className="text-default-400 mt-1 text-xs">
                  Hours a contractor must work here before this property may hire them directly, from their contract. Leave blank to use the
                  default of {defaultDirectHireThresholdHours}. Copied onto each new work order — changing it does not affect placements already running.
                </p>
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

            <div className="space-y-4">
              <div>
                <h5 className="font-semibold">Location &amp; Geofence</h5>
                <p className="text-default-400 text-sm">
                  Where the property is and how close a contractor's phone must be to QR clock-in.
                </p>
              </div>

              <GeofenceMapCard
                latitude={data.latitude}
                longitude={data.longitude}
                radius={data.geofence_radius_meters}
                addressQuery={[data.address, data.city, data.state].filter(Boolean).join(', ')}
                onCoordinates={(lat, lng) => setData((d) => ({ ...d, latitude: lat, longitude: lng }))}
              />

              <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                <Field label="Latitude" error={errors.latitude}>
                  <input className="form-input" value={data.latitude} onChange={(e) => setData('latitude', e.target.value)} placeholder="33.4484" />
                </Field>
                <Field label="Longitude" error={errors.longitude}>
                  <input className="form-input" value={data.longitude} onChange={(e) => setData('longitude', e.target.value)} placeholder="-112.0740" />
                </Field>
                <Field label="Geofence Radius (m)" error={errors.geofence_radius_meters}>
                  <input
                    type="number"
                    min={50}
                    max={5000}
                    className="form-input"
                    value={data.geofence_radius_meters}
                    onChange={(e) => setData('geofence_radius_meters', Number(e.target.value))}
                  />
                </Field>
              </div>

              <div className="border-default-200 rounded-lg border p-4">
                <label className="flex items-center gap-2.5" title={!data.latitude ? 'Set coordinates first' : undefined}>
                  <input
                    type="checkbox"
                    className="form-switch"
                    checked={data.qr_clock_enabled}
                    disabled={!data.latitude && !data.qr_clock_enabled}
                    onChange={(e) => setData('qr_clock_enabled', e.target.checked)}
                  />
                  <span className="font-medium">Enable QR clock-in</span>
                </label>
                <p className="text-default-400 mt-1 ps-10 text-sm">
                  Contractors punch in by scanning this property's poster with their phone. Requires coordinates.
                </p>
                <p className={`mt-2 flex items-center gap-2 ps-10 text-sm font-medium ${data.qr_clock_enabled ? 'text-warning' : 'text-default-400'}`}>
                  <span className={`size-2 rounded-full ${data.qr_clock_enabled ? 'bg-warning' : 'bg-default-300'}`} />
                  {data.qr_clock_enabled ? 'A QR code is created when you save' : 'Off'}
                </p>
                {errors.qr_clock_enabled && <p className="text-danger mt-1 ps-10 text-sm">{errors.qr_clock_enabled}</p>}
              </div>
            </div>

            <div className="flex items-center gap-3">
              <button type="submit" className="btn bg-primary hover:bg-primary-hover px-6 py-2.5 font-semibold text-white" disabled={processing}>
                Create Property
              </button>
              <Link href="/admin/properties" className="btn btn-light px-6 py-2.5">
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
