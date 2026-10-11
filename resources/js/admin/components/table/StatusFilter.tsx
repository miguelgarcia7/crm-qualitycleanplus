import { cn } from '@/utils/helpers'

type Option = { key: string; label: string; count?: number }

/**
 * The segmented switch that narrows a list by status, as on Work Orders and
 * People. A row of pills in the toolbar means "filter this list"; tabs under
 * the page title mean "a different list" — so status choices use this, never
 * tabs.
 */
const StatusFilter = ({ options, value, onChange, label }: { options: Option[]; value: string; onChange: (key: string) => void; label: string }) => (
  <div className="bg-light inline-flex shrink-0 rounded-lg p-0.5" role="group" aria-label={label}>
    {options.map((o) => (
      <button
        key={o.key}
        type="button"
        aria-pressed={value === o.key}
        onClick={() => onChange(o.key)}
        className={cn('rounded-md px-3 py-1 text-sm whitespace-nowrap', value === o.key ? 'bg-card text-default-900 font-semibold shadow-sm' : 'text-default-500')}>
        {o.label}
        {o.count !== undefined && <span className="ms-1 tabular-nums opacity-70">{o.count}</span>}
      </button>
    ))}
  </div>
)

export default StatusFilter
