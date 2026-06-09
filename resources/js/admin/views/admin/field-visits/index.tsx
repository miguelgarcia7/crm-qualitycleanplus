import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, router } from '@inertiajs/react'

type Visit = {
  id: number
  recruiter: string | null
  property: string | null
  status: string
  check_in_at: string
  check_out_at: string | null
  duration: number | null
  inside_geofence: boolean
  late_close: boolean
}

type Props = {
  visits: Visit[]
  filter: string
  can: { viewAll: boolean }
}

const FILTERS = [
  { key: '', label: 'All' },
  { key: 'open', label: 'Still open' },
  { key: 'off_geofence', label: 'Off-geofence' },
  { key: 'late', label: 'Late close' },
]

const fmtDuration = (m: number | null) => (m === null ? '—' : m >= 60 ? `${Math.floor(m / 60)}h ${m % 60}m` : `${m}m`)

const Page = ({ visits, filter, can }: Props) => {
  const setFilter = (key: string) => router.get('/admin/field-visits', key ? { filter: key } : {}, { preserveState: true, preserveScroll: true })

  return (
    <>
      <Head title="Field Visits" />
      <PageBreadcrumb title="Field Visits" subtitle="Recruiter Activity" />

      <div className="mb-4 flex flex-wrap gap-2">
        {FILTERS.map((f) => (
          <button
            key={f.key}
            className={`btn btn-sm ${filter === f.key ? 'bg-primary text-white' : 'btn-light'}`}
            onClick={() => setFilter(f.key)}
          >
            {f.label}
          </button>
        ))}
      </div>

      <div className="card rounded-2xl">
        <div className="card-body p-0">
          {visits.length === 0 ? (
            <p className="text-muted p-6">No visits found.</p>
          ) : (
            <table className="w-full text-sm">
              <thead className="border-default-200 text-muted border-b text-left">
                <tr>
                  {can.viewAll && <th className="p-3">Recruiter</th>}
                  <th className="p-3">Property</th>
                  <th className="p-3">Checked in</th>
                  <th className="p-3">Checked out</th>
                  <th className="p-3">Duration</th>
                  <th className="p-3">Flags</th>
                </tr>
              </thead>
              <tbody>
                {visits.map((v) => (
                  <tr key={v.id} className="border-default-100 border-b">
                    {can.viewAll && <td className="p-3">{v.recruiter}</td>}
                    <td className="p-3 font-medium">{v.property}</td>
                    <td className="p-3">{v.check_in_at}</td>
                    <td className="p-3">{v.check_out_at ?? <span className="text-warning">Open</span>}</td>
                    <td className="p-3">{fmtDuration(v.duration)}</td>
                    <td className="space-x-1 p-3">
                      {!v.inside_geofence && <span className="badge badge-soft-danger">Off-geofence</span>}
                      {v.late_close && <span className="badge badge-soft-warning">Late close</span>}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>
    </>
  )
}

export default Page
