import Icon from '@/components/wrappers/Icon'
import { SimpleBar } from '@/components/wrappers/SimpleBar'
import { cn } from '@/utils/helpers'
import { Link } from '@inertiajs/react'

export type ActivityRow = {
  description: string
  event: string | null
  causer: string | null
  when: string | null
}

const eventMarker: Record<string, { icon: string; className: string }> = {
  created: { icon: 'plus', className: 'bg-success' },
  updated: { icon: 'edit', className: 'bg-info' },
  deleted: { icon: 'trash', className: 'bg-danger' },
  login: { icon: 'login', className: 'bg-primary' },
}

/** Recent audit-log timeline (only rendered for audit.activity_log.view holders). */
const ActivityCard = ({ rows }: { rows: ActivityRow[] }) => (
  <div className="card h-full">
    <div className="card-header">
      <h4 className="card-title">Recent activity</h4>
      <Link href="/admin/audit" className="btn btn-sm btn-icon border-default-300 hover:text-primary ms-auto" title="View audit log">
        <Icon icon="activity" className="text-base" />
      </Link>
    </div>

    <SimpleBar className="card-body" style={{ maxHeight: 426 }}>
      {rows.length === 0 ? (
        <p className="text-default-400 text-sm">No activity yet.</p>
      ) : (
        <div>
          {rows.map((row, idx) => {
            const marker = eventMarker[row.event ?? ''] ?? { icon: 'point', className: 'bg-default-400' }

            return (
              <div className="flex gap-x-5" key={idx}>
                <div
                  className={cn('after:border-default-300 relative after:absolute after:start-3.5 after:top-7 after:bottom-0 after:w-0 after:-translate-x-[0.5px] after:border-s after:border-dashed', {
                    'after:content-none': idx === rows.length - 1,
                  })}>
                  <div className="relative z-10">
                    <div className={cn('flex size-7.5 items-center justify-center rounded-full', marker.className)}>
                      <Icon icon={marker.icon} className="text-base text-white" />
                    </div>
                  </div>
                </div>

                <div
                  className={cn('grow', {
                    'pb-7.5': idx !== rows.length - 1,
                  })}>
                  <h5 className="mb-1.25">{row.description}</h5>
                  <p className="text-default-400 mb-1.25">{row.when}</p>
                  <span className="text-primary">By {row.causer ?? 'System'}</span>
                </div>
              </div>
            )
          })}
        </div>
      )}
    </SimpleBar>
  </div>
)

export default ActivityCard
