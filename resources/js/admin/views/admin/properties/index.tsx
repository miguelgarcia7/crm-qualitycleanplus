import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, Link } from '@inertiajs/react'

type PropertyRow = {
  id: number
  name: string
  city: string | null
  state: string | null
  status: string
}

type Props = {
  properties: PropertyRow[]
  can: { create: boolean }
}

const StatusBadge = ({ status }: { status: string }) => (
  <span className={`badge ${status === 'active' ? 'badge-soft-success' : 'badge-soft-secondary'} capitalize`}>{status}</span>
)

const Page = ({ properties, can }: Props) => {
  return (
    <>
      <Head title="Property Bible" />
      <PageBreadcrumb title="Properties" subtitle="Property Bible" />

      <div className="card rounded-2xl">
        <div className="card-header flex items-center justify-between p-6">
          <h4 className="card-title">Properties</h4>
          {can.create && (
            <Link href="/admin/properties/create" className="btn bg-primary hover:bg-primary-hover px-4 py-2 font-semibold text-white">
              Add Property
            </Link>
          )}
        </div>

        <div className="table-wrapper">
          <table className="table table-hover">
            <thead className="thead-sm">
              <tr className="bg-light/25 text-2xs uppercase">
                <th>Name</th>
                <th>City</th>
                <th>State</th>
                <th>Status</th>
                <th className="text-end">Timesheet</th>
              </tr>
            </thead>
            <tbody>
              {properties.length ? (
                properties.map((p) => (
                  <tr key={p.id}>
                    <td>
                      <Link href={`/admin/properties/${p.id}`} className="text-primary font-medium hover:underline">
                        {p.name}
                      </Link>
                    </td>
                    <td>{p.city ?? '—'}</td>
                    <td>{p.state ?? '—'}</td>
                    <td>
                      <StatusBadge status={p.status} />
                    </td>
                    <td className="text-end">
                      <Link href={`/admin/properties/${p.id}/grid`} className="text-primary text-sm hover:underline">
                        Weekly grid →
                      </Link>
                    </td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={5} className="text-default-400 py-4 text-center">
                    No properties yet.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </>
  )
}

export default Page
