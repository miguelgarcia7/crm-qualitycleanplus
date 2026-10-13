import { Pill } from '@/components/timesheet/PmTimesheet'

export type PayLine = { label: string; minutes: number; rate: number; amount: number }
export type HoursWeek = {
  start: string
  end: string
  status: 'in_progress' | 'waiting' | 'approved'
  may_change: boolean
  minutes: number
  overtime_minutes: number
  holiday: boolean
  gross: number
  jobs: { work_order_id: number; label: string; lines: PayLine[]; total: number }[]
  adjustments: { label: string; amount: number }[]
  after_adjustments: number
}

/** "49.9": one decimal reads well in a list; pay lines keep two. */
export const hours1 = (minutes: number) => (minutes / 60).toFixed(1)

/** "9 h 57 m", as a clock reads, for punches and days. */
export const clockSpan = (minutes: number) => {
  const h = Math.floor(minutes / 60)
  const m = String(minutes % 60).padStart(2, '0')
  return h ? `${h} h ${m} m` : `${minutes % 60} m`
}

/** Where a week stands. Who approved it is never shown. */
export const WeekStatusPill = ({ week }: { week: Pick<HoursWeek, 'status' | 'holiday'> }) => {
  const holiday = week.holiday ? ' · Holiday' : ''
  if (week.status === 'in_progress') return <Pill tone="muted">{`This week, so far${holiday}`}</Pill>
  if (week.status === 'waiting') return <Pill tone="warning">{`Waiting on the hotel${holiday}`}</Pill>
  return <Pill tone="success">{`Approved${holiday}`}</Pill>
}
