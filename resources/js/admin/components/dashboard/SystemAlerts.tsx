import Icon from '@/components/wrappers/Icon'
import { Link } from '@inertiajs/react'

export type SystemAlert = { key: string; title: string; body: string; actionLabel: string; actionHref: string }

/** Banners shown only while something is broken. */
const SystemAlerts = ({ alerts }: { alerts: SystemAlert[] }) => (
  <>
    {alerts.map((alert) => (
      <div key={alert.key} role="alert" className="border-danger/30 bg-danger/5 flex flex-wrap items-start gap-4 rounded-xl border px-5 py-4">
        <Icon icon="alert-triangle" className="text-danger mt-0.5 size-5 shrink-0" />
        <div className="min-w-60 flex-1">
          <div className="text-danger font-semibold">{alert.title}</div>
          <div className="text-default-600 text-sm">{alert.body}</div>
        </div>
        <Link href={alert.actionHref} className="btn btn-sm bg-card border-default-300 border">
          {alert.actionLabel}
        </Link>
      </div>
    ))}
  </>
)

export default SystemAlerts
