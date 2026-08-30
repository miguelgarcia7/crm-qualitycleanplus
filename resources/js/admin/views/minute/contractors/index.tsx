import PageBreadcrumb from '@/components/PageBreadcrumb'
import Icon from '@/components/wrappers/Icon'
import { Head } from '@inertiajs/react'

type DirectHire = {
  threshold_hours: number
  worked_hours: number
  remaining_hours: number
  percent: number
  eligible: boolean
  unrestricted: boolean
}

type Row = {
  id: number
  name: string | null
  position: string | null
  property: string | null
  started_on: string | null
  direct_hire: DirectHire | null
}

type Props = { contractors: Row[] }

const hours = (n: number) => n.toLocaleString(undefined, { maximumFractionDigits: 0 })

const DirectHireCell = ({ dh }: { dh: DirectHire | null }) => {
  if (dh === null) return <span className="text-default-400">—</span>

  if (dh.unrestricted) {
    return <span className="badge badge-label bg-success/15 text-success">No restriction</span>
  }

  if (dh.eligible) {
    return (
      <span className="badge badge-label bg-success/15 text-success">
        <Icon icon="circle-check" className="me-1 size-3.5" />
        Eligible
      </span>
    )
  }

  return (
    <div className="min-w-40">
      <div className="mb-1 flex items-baseline justify-between gap-2 text-xs">
        <span className="text-default-500">
          {hours(dh.worked_hours)} / {hours(dh.threshold_hours)} hrs
        </span>
        <span className="text-default-400">{hours(dh.remaining_hours)} to go</span>
      </div>
      <span className="bg-light block h-1.5 w-full overflow-hidden rounded-full">
        <span className="bg-primary block h-full rounded-full" style={{ width: `${Math.max(2, dh.percent)}%` }} />
      </span>
    </div>
  )
}

const Page = ({ contractors }: Props) => (
  <>
    <Head title="Contractors" />
    <PageBreadcrumb title="Contractors" subtitle="Your properties" />

    <div className="card">
      <div className="card-header">
        <h4 className="card-title">
          Contractors on site
          {contractors.length > 0 && <span className="text-default-400 ms-1 text-sm font-normal">({contractors.length})</span>}
        </h4>
      </div>

      <div className="table-wrapper">
        <table className="table table-hover">
          <thead className="thead-sm">
            <tr className="bg-light/25 text-2xs uppercase">
              <th>Contractor</th>
              <th>Position</th>
              <th>Property</th>
              <th>Started</th>
              <th>Direct-hire eligibility</th>
            </tr>
          </thead>
          <tbody>
            {contractors.length ? (
              contractors.map((row) => (
                <tr key={row.id}>
                  <td className="font-medium">{row.name}</td>
                  <td>{row.position}</td>
                  <td>{row.property}</td>
                  <td>{row.started_on ?? '—'}</td>
                  <td>
                    <DirectHireCell dh={row.direct_hire} />
                  </td>
                </tr>
              ))
            ) : (
              <tr>
                <td colSpan={5} className="text-default-400 py-4 text-center">
                  No contractors are currently placed at your properties.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      <div className="card-footer">
        <p className="text-default-400 text-sm">
          Direct-hire eligibility is the hours a contractor must work at your property before you may offer them a position directly. Hours
          counted are worked hours, not calendar time.
        </p>
      </div>
    </div>
  </>
)

export default Page
