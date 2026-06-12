import { Link } from '@inertiajs/react'

export type ClockedInRow = {
  person: string | null
  person_id: number
  avatar: string | null
  property: string | null
  property_id: number
  minutes: number
}

const initialsOf = (name: string) =>
  name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0])
    .join('')
    .toUpperCase()

const elapsed = (minutes: number) => {
  const h = Math.floor(minutes / 60)
  const m = minutes % 60
  return h > 0 ? `${h}h ${m}m` : `${m}m`
}

/** Live ops pulse: who is on the clock right now (open clock entries). */
const ClockedInCard = ({ rows }: { rows: ClockedInRow[] }) => (
  <div className="card h-full rounded-2xl">
    <div className="card-header flex items-center justify-between p-5 pb-2">
      <h4 className="card-title flex items-center gap-2">
        <span className="relative flex size-2.5">
          <span className="bg-success absolute inline-flex h-full w-full animate-ping rounded-full opacity-60"></span>
          <span className="bg-success relative inline-flex size-2.5 rounded-full"></span>
        </span>
        On the clock now
      </h4>
      <span className="text-default-400 text-sm">{rows.length}</span>
    </div>
    <div className="card-body p-5 pt-0">
      {rows.length === 0 ? (
        <p className="text-default-400 py-4 text-sm">No one is clocked in right now.</p>
      ) : (
        rows.map((row) => (
          <Link
            key={`${row.person_id}-${row.property_id}`}
            href={`/admin/people/${row.person_id}`}
            className="border-default-100 hover:bg-default-50 flex items-center gap-3 border-b px-1 py-2.5 last:border-0"
          >
            {row.avatar ? (
              <img src={row.avatar} alt={row.person ?? ''} className="size-9 shrink-0 rounded-full object-cover" />
            ) : (
              <span className="bg-success/10 text-success flex size-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold">
                {initialsOf(row.person ?? '?')}
              </span>
            )}
            <span className="min-w-0 flex-1">
              <span className="block truncate text-sm font-medium">{row.person}</span>
              <span className="text-default-400 block truncate text-xs">{row.property}</span>
            </span>
            <span className="text-success shrink-0 text-sm font-semibold">{elapsed(row.minutes)}</span>
          </Link>
        ))
      )}
    </div>
  </div>
)

export default ClockedInCard
