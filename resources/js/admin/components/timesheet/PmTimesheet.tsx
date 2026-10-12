import { cn } from '@/utils/helpers'

/** Pieces shared by the property manager's timesheet list and week page (QC Minute). */

export const hrs = (min: number | null | undefined) => ((min ?? 0) / 60).toFixed(2)
export const money = (cents: number) => `$${(cents / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`

const at = (d: string) => new Date(d + 'T12:00:00')
export const monthDay = (d: string) => at(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
export const weekday = (d: string) => at(d).toLocaleDateString('en-US', { weekday: 'short' })
export const isWeekend = (d: string) => [0, 6].includes(at(d).getDay())

/** 7:59a, as the back-office grid writes punch times in a narrow cell. */
export const shortClock = (t: string | null) => {
  if (!t) return '—'
  const [h, m] = t.split(':').map(Number)
  return `${h % 12 || 12}:${String(m).padStart(2, '0')}${h < 12 ? 'a' : 'p'}`
}

/** "Sep 28 – Oct 4", or "Sep 21 – 27" inside one month. */
export const weekRange = (start: string, end: string) =>
  start.slice(0, 7) === end.slice(0, 7) ? `${monthDay(start)} – ${at(end).getDate()}` : `${monthDay(start)} – ${monthDay(end)}`

/** "you" comes from the server when the viewer did it. */
export const byWhom = (who: string | null, verb: string, date: string | null) =>
  who === 'you' ? `You ${verb} ${date ? monthDay(date) : ''}`.trim() : `${verb[0].toUpperCase()}${verb.slice(1)} ${date ? monthDay(date) : ''}${who ? ` by ${who}` : ''}`

const PILLS: Record<string, { label: string; tone: string; dot: string }> = {
  pending_approval: { label: 'Waiting for you', tone: 'bg-warning/20', dot: 'bg-warning' },
  declined: { label: 'Declined', tone: 'bg-danger/12', dot: 'bg-danger' },
  approved: { label: 'Invoiced', tone: 'bg-success/15', dot: 'bg-success' },
  invoiced: { label: 'Invoiced', tone: 'bg-success/15', dot: 'bg-success' },
  invoice_sent: { label: 'Invoiced', tone: 'bg-success/15', dot: 'bg-success' },
  voided: { label: 'Voided', tone: 'bg-light', dot: 'bg-default-400' },
  draft: { label: 'In progress', tone: 'bg-info/15', dot: 'bg-info' },
}

/** Status as the property manager reads it. Dark text on a tint, so it passes contrast. */
export const StatusPill = ({ status, children }: { status: string; children?: string }) => {
  const pill = PILLS[status] ?? PILLS.draft
  return (
    <span className={cn('text-default-900 inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap', pill.tone)}>
      <span className={cn('size-1.5 rounded-full', pill.dot)} aria-hidden="true" />
      {children ?? pill.label}
    </span>
  )
}

export const Chip = ({ tone, children }: { tone: 'secondary' | 'info' | 'success'; children: string }) => (
  <span
    className={cn(
      'text-default-900 rounded px-1.5 text-[11.5px] font-semibold whitespace-nowrap',
      tone === 'secondary' && 'bg-secondary/15',
      tone === 'info' && 'bg-info/15',
      tone === 'success' && 'bg-success/15',
    )}>
    {children}
  </span>
)
