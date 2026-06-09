import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, Link, router, useForm } from '@inertiajs/react'

type Device = {
  id: number
  name: string
  property: string | null
  activation_code: string | null
  is_activated: boolean
  last_seen_at: string | null
  app_version: string | null
}

type Props = {
  devices: Device[]
  properties: { id: number; name: string }[]
}

const Page = ({ devices, properties }: Props) => {
  const { data, setData, post, processing, reset, errors } = useForm({ name: 'Front Desk Tablet', property_id: '' })

  const create = (e: React.FormEvent) => {
    e.preventDefault()
    post('/admin/devices', { preserveScroll: true, onSuccess: () => reset() })
  }

  const regenerate = (id: number) => router.post(`/admin/devices/${id}/regenerate`, {}, { preserveScroll: true })
  const revoke = (id: number) => {
    if (confirm('Revoke this device? Its token is invalidated and it must be re-paired.')) {
      router.delete(`/admin/devices/${id}`, { preserveScroll: true })
    }
  }

  return (
    <>
      <Head title="Devices" />
      <PageBreadcrumb title="Devices" subtitle="Clock-In Tablets" />

      <div className="grid gap-4 lg:grid-cols-3">
        <div className="lg:col-span-2">
          <div className="card rounded-2xl">
            <div className="card-body p-0">
              {devices.length === 0 ? (
                <p className="text-muted p-6">No devices yet. Create one, then pair the tablet at <code>/device</code>.</p>
              ) : (
                <table className="w-full text-sm">
                  <thead className="border-default-200 text-muted border-b text-left">
                    <tr>
                      <th className="p-3">Device</th>
                      <th className="p-3">Property</th>
                      <th className="p-3">Status</th>
                      <th className="p-3">Last seen</th>
                      <th className="p-3 text-right">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {devices.map((d) => (
                      <tr key={d.id} className="border-default-100 border-b">
                        <td className="p-3 font-medium">{d.name}</td>
                        <td className="p-3">{d.property}</td>
                        <td className="p-3">
                          {d.is_activated ? (
                            <span className="badge badge-soft-success">Paired</span>
                          ) : (
                            <span className="text-warning font-mono text-base font-bold tracking-widest">{d.activation_code}</span>
                          )}
                        </td>
                        <td className="p-3">{d.last_seen_at ?? '—'}</td>
                        <td className="space-x-2 p-3 text-right">
                          <button className="btn btn-sm btn-light" onClick={() => regenerate(d.id)}>
                            New code
                          </button>
                          <button className="btn btn-sm btn-light text-danger" onClick={() => revoke(d.id)}>
                            Revoke
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </div>
          </div>
        </div>

        <div>
          <div className="card rounded-2xl">
            <div className="card-body p-5">
              <h4 className="card-title mb-3">Add a tablet</h4>
              <form onSubmit={create} className="space-y-3">
                <div>
                  <label className="form-label">Name</label>
                  <input className="form-input w-full" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                  {errors.name && <p className="text-danger text-sm">{errors.name}</p>}
                </div>
                <div>
                  <label className="form-label">Property</label>
                  <select className="form-select w-full" value={data.property_id} onChange={(e) => setData('property_id', e.target.value)} required>
                    <option value="">Select…</option>
                    {properties.map((p) => (
                      <option key={p.id} value={p.id}>
                        {p.name}
                      </option>
                    ))}
                  </select>
                  {errors.property_id && <p className="text-danger text-sm">{errors.property_id}</p>}
                </div>
                <button className="btn bg-primary w-full py-2 font-semibold text-white" disabled={processing}>
                  Create device
                </button>
              </form>
              <p className="text-default-400 mt-4 text-xs">
                After creating, open <Link href="/device" className="text-primary">the kiosk</Link> on the tablet and enter the code shown here.
              </p>
            </div>
          </div>
        </div>
      </div>
    </>
  )
}

export default Page
