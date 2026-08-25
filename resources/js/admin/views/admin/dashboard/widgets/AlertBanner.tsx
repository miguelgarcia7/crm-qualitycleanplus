import Icon from '@/components/wrappers/Icon'
import { Link } from '@inertiajs/react'

export type PulseAlert = {
  key: string
  icon: string
  title: string
  body: string
  meta?: string | null
  badge?: string | null
  actionLabel?: string | null
  actionHref?: string | null
}

/** A condition somebody needs to act on before it costs money. */
const AlertBanner = ({ alert }: { alert: PulseAlert }) => (
  <div className="card mb-4">
    <div className="card-body flex flex-wrap items-center gap-4 p-5">
      <span className="bg-danger/15 text-danger flex size-10 shrink-0 items-center justify-center rounded-full">
        <Icon icon={alert.icon} className="size-5" />
      </span>

      <div className="min-w-60 flex-1">
        <h5 className="font-semibold">{alert.title}</h5>
        <p className="text-default-400 mt-0.5 text-sm">
          {alert.body}
          {alert.meta && <span className="text-danger ms-1 font-medium">{alert.meta}.</span>}
        </p>
      </div>

      <div className="flex items-center gap-3">
        {alert.badge && <span className="badge badge-label bg-danger/15 text-danger">{alert.badge}</span>}
        {alert.actionHref && (
          <Link href={alert.actionHref} className="btn bg-primary hover:bg-primary-hover text-white">
            {alert.actionLabel ?? 'Open'}
          </Link>
        )}
      </div>
    </div>
  </div>
)

export default AlertBanner
