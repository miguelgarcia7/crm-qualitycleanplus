import Icon from '@/components/wrappers/Icon'
import { Link } from '@inertiajs/react'

export type ActivityRow = {
  description: string
  event: string | null
  causer: string | null
  when: string | null
}

const eventIcon: Record<string, { icon: string; className: string }> = {
  created: { icon: 'plus', className: 'bg-success/15 text-success' },
  updated: { icon: 'edit', className: 'bg-info/15 text-info' },
  deleted: { icon: 'trash', className: 'bg-danger/15 text-danger' },
  login: { icon: 'login', className: 'bg-primary/15 text-primary' },
}

/** Recent audit-log timeline (only rendered for audit.activity_log.view holders). */
const ActivityCard = ({ rows }: { rows: ActivityRow[] }) => (
  <div className="card h-full rounded-2xl">
    <div className="card-header flex items-center justify-between p-5 pb-2">
      <h4 className="card-title">Recent activity</h4>
      <Link href="/admin/audit" className="text-primary text-sm">
        Audit log →
      </Link>
    </div>
    <div className="card-body p-5 pt-2">
      {rows.length === 0 ? (
        <p className="text-default-400 py-4 text-sm">No activity yet.</p>
      ) : (
        <ol className="relative">
          {rows.map((row, i) => {
            const marker = eventIcon[row.event ?? ''] ?? { icon: 'point', className: 'bg-light text-default-500' }
            return (
              <li key={i} className="relative flex gap-3 pb-4 last:pb-0">
                {i < rows.length - 1 && <span className="bg-default-200 absolute start-3.5 top-8 h-full w-px" aria-hidden />}
                <span className={`z-1 flex size-7 shrink-0 items-center justify-center rounded-full ${marker.className}`}>
                  <Icon icon={marker.icon} className="size-3.5" />
                </span>
                <span className="min-w-0">
                  <span className="block text-sm">{row.description}</span>
                  <span className="text-default-400 block text-xs">
                    {row.causer ?? 'System'} · {row.when}
                  </span>
                </span>
              </li>
            )
          })}
        </ol>
      )}
    </div>
  </div>
)

export default ActivityCard
