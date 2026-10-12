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

export type Tone = 'warning' | 'danger' | 'success' | 'info' | 'muted'

const TONES: Record<Tone, { tint: string; dot: string }> = {
  warning: { tint: 'bg-warning/20', dot: 'bg-warning' },
  danger: { tint: 'bg-danger/12', dot: 'bg-danger' },
  success: { tint: 'bg-success/15', dot: 'bg-success' },
  info: { tint: 'bg-info/15', dot: 'bg-info' },
  muted: { tint: 'bg-light', dot: 'bg-default-400' },
}

/** A status pill: dark text on a tint, with a colored dot, so it passes contrast. */
export const Pill = ({ tone, children }: { tone: Tone; children: string }) => (
  <span className={cn('text-default-900 inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap', TONES[tone].tint)}>
    <span className={cn('size-1.5 rounded-full', TONES[tone].dot)} aria-hidden="true" />
    {children}
  </span>
)

const PILLS: Record<string, { label: string; tone: Tone }> = {
  pending_approval: { label: 'Waiting for you', tone: 'warning' },
  declined: { label: 'Declined', tone: 'danger' },
  approved: { label: 'Invoiced', tone: 'success' },
  invoiced: { label: 'Invoiced', tone: 'success' },
  invoice_sent: { label: 'Invoiced', tone: 'success' },
  voided: { label: 'Voided', tone: 'muted' },
  draft: { label: 'In progress', tone: 'info' },
}

/** A timesheet's status as the property manager reads it. */
export const StatusPill = ({ status, children }: { status: string; children?: string }) => {
  const pill = PILLS[status] ?? PILLS.draft
  return <Pill tone={pill.tone}>{children ?? pill.label}</Pill>
}

/** Where an invoice stands for the client, from its payment fields. */
export type Payment = {
  voided: boolean
  due_date: string
  paid_at: string | null
  is_overdue: boolean
  days_overdue: number | null
  days_until_due: number | null
}

const days = (n: number) => (n === 1 ? '1 day' : `${n} days`)

export const paymentStatus = (p: Payment): { tone: Tone; label: string; note: string } => {
  if (p.voided) return { tone: 'muted', label: 'Voided', note: 'No longer payable' }
  if (p.paid_at) return { tone: 'success', label: `Paid ${monthDay(p.paid_at)}`, note: `Was due ${monthDay(p.due_date)}` }
  if (p.is_overdue) return { tone: 'danger', label: `Overdue · ${days(p.days_overdue ?? 0)}`, note: `Was due ${monthDay(p.due_date)}` }
  return { tone: 'info', label: `Due ${monthDay(p.due_date)}`, note: p.days_until_due ? `In ${days(p.days_until_due)}` : 'Due today' }
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
