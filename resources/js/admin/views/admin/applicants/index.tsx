import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, Link, router } from '@inertiajs/react'

type ApplicationRow = {
  id: number
  name: string
  city: string
  desired_position: string | null
  posting: string | null
  status: string
  status_label: string
  submitted_at: string
}

type Props = {
  applications: ApplicationRow[]
  filter: string
  counts: { pending: number; submitted: number; reviewing: number; promoted: number; rejected: number }
}

const statusBadge: Record<string, string> = {
  submitted: 'badge badge-soft-info',
  reviewing: 'badge badge-soft-warning',
  promoted: 'badge badge-soft-success',
  rejected: 'badge badge-soft-danger',
}

const FILTERS: { key: string; label: string }[] = [
  { key: 'pending', label: 'Pending' },
  { key: 'submitted', label: 'Submitted' },
  { key: 'reviewing', label: 'Reviewing' },
  { key: 'promoted', label: 'Promoted' },
  { key: 'rejected', label: 'Rejected' },
  { key: 'all', label: 'All' },
]

const Page = ({ applications, filter, counts }: Props) => {
  const setFilter = (key: string) => router.get('/admin/applicants', key === 'pending' ? {} : { status: key }, { preserveState: true })

  return (
    <>
      <Head title="Applicants" />
      <PageBreadcrumb title="Applicants" subtitle="Recruiting" />

      <div className="card rounded-2xl">
        <div className="card-body p-0">
          <div className="border-default-200 flex flex-wrap gap-2 border-b p-4">
            {FILTERS.map((f) => (
              <button
                key={f.key}
                className={`btn btn-sm ${filter === f.key ? 'bg-primary text-white' : 'btn-light'}`}
                onClick={() => setFilter(f.key)}
              >
                {f.label}
                {f.key in counts && <span className="ms-1 opacity-75">({counts[f.key as keyof typeof counts]})</span>}
              </button>
            ))}
          </div>

          {applications.length === 0 ? (
            <p className="text-muted p-6">No applications match this filter.</p>
          ) : (
            <table className="w-full text-sm">
              <thead className="border-default-200 text-muted border-b text-left">
                <tr>
                  <th className="p-3">Applicant</th>
                  <th className="p-3">Position</th>
                  <th className="p-3">Posting</th>
                  <th className="p-3">Submitted</th>
                  <th className="p-3">Status</th>
                </tr>
              </thead>
              <tbody>
                {applications.map((a) => (
                  <tr key={a.id} className="border-default-100 border-b">
                    <td className="p-3">
                      <Link href={`/admin/applicants/${a.id}`} className="text-primary font-medium">
                        {a.name}
                      </Link>
                      {a.city && <div className="text-muted text-xs">{a.city}</div>}
                    </td>
                    <td className="p-3">{a.desired_position ?? '—'}</td>
                    <td className="p-3">{a.posting ?? '—'}</td>
                    <td className="p-3">{a.submitted_at}</td>
                    <td className="p-3">
                      <span className={statusBadge[a.status] ?? 'badge'}>{a.status_label}</span>
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
