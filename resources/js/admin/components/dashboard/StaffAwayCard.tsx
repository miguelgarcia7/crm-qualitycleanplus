import DashCard, { DashEmpty, DashRow, ViewAll } from '@/components/dashboard/DashCard'

export type StaffAway = {
  rows: { id: number; name: string | null; dates: string; kind: string; now: boolean; covers: number }[]
  total: number
}

/** Approved staff time off coming up, and which recruiters leave properties without cover. */
const StaffAwayCard = ({ data, title = 'Staff away', description }: { data: StaffAway; title?: string; description: string }) => (
  <DashCard title={title} description={description} footer={<ViewAll href="/admin/pto">Time off and balances</ViewAll>}>
    {data.rows.length === 0 ? (
      <DashEmpty>Nobody is away in the next 30 days.</DashEmpty>
    ) : (
      data.rows.map((row) => (
        <DashRow key={row.id} end={row.now ? <span className="badge badge-label bg-info/15 text-info">Away now</span> : undefined}>
          <div className="text-default-900 font-medium">{row.name}</div>
          <div className="text-default-500 text-sm">
            {row.dates} · {row.kind}
            {row.covers > 0 && <span className="text-warning"> · {row.covers} {row.covers === 1 ? 'property needs' : 'properties need'} cover</span>}
          </div>
        </DashRow>
      ))
    )}
  </DashCard>
)

export default StaffAwayCard
