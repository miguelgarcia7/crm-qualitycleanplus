import { confirmAction } from '@/components/ConfirmHost'
import DashCard, { DashEmpty, DashRow, ViewAll } from '@/components/dashboard/DashCard'
import DashboardHeader from '@/components/dashboard/DashboardHeader'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Link, router } from '@inertiajs/react'

type AdjustmentLine = { key: string; label: string; count: number; amount: number }

type Run = {
  start: string
  label: string
  isDefault: boolean
  previous: string
  next: string | null
  gross: number
  contractors: number
  hours: { regular: number; overtime: number; holiday: number; training: number }
  adjustments: { net: number; lines: AdjustmentLine[] }
  propertiesWorked: number
  propertiesApproved: number
  notApproved: { properties: number; contractors: number }
}

type Check = { key: string; contractor: string | null; personId: number; property: string | null; what: string; tone: 'warning' | 'info' | 'gray'; pay: number; href: string }
type Task = { id: number; title: string; detail: string; age: string | null; href: string }
type Overdue = { id: number; number: string; property: string | null; total: number; daysLate: number; href: string }
type TimeOff = { id: number; name: string | null; detail: string; when: string }

type Home = {
  header: { greeting: string; date: string; properties: number }
  actions: { upload: boolean; payouts: boolean }
  run: Run
  checks: { rows: Check[]; total: number }
  tasks: { rows: Task[]; total: number }
  overdue: { rows: Overdue[]; total: number; amount: number } | null
  timeOff: { rows: TimeOff[]; total: number }
}

const money = (n: number, signed = false) => {
  const text = `$${Math.abs(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
  return signed ? `${n < 0 ? '−' : n > 0 ? '+' : ''}${text}` : text
}

const hours = (n: number) => `${n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} h`

const plural = (n: number, one: string, many: string) => `${n.toLocaleString()} ${n === 1 ? one : many}`

const checkTone: Record<Check['tone'], string> = {
  warning: 'bg-warning/15 text-warning',
  info: 'bg-info/15 text-info',
  gray: 'bg-light text-default-600',
}

const goToWeek = (week: string) => router.get('/admin/dashboard', { week }, { preserveScroll: true, preserveState: true })

const Line = ({ label, value, className }: { label: string; value: string; className?: string }) => (
  <div className="border-default-200 flex justify-between gap-3 border-b border-dashed py-1.5 text-sm last:border-0">
    <span className="text-default-500">{label}</span>
    <span className={cn('text-default-900 font-medium tabular-nums', className)}>{value}</span>
  </div>
)

/** The pay run: what it adds up to, and whether it's ready to export. */
const PayRun = ({ run }: { run: Run }) => {
  const allApproved = run.propertiesWorked > 0 && run.notApproved.properties === 0

  return (
    <DashCard
      flush
      title={`${run.isDefault ? 'This pay run' : 'Pay run'} · ${run.label}`}
      description="Hours, pay and what comes out of it, for every contractor with time that week."
      aside={
        <div className="flex shrink-0 items-center gap-2">
          {run.propertiesWorked > 0 && (
            <span className={cn('badge badge-label', allApproved ? 'bg-success/15 text-success' : 'bg-warning/15 text-warning')}>
              {run.propertiesApproved} of {run.propertiesWorked} properties approved
            </span>
          )}
          <span className="bg-light inline-flex rounded-lg p-0.5" role="group" aria-label="Pay run week">
            <button type="button" className="text-default-600 hover:text-default-900 rounded-md px-2 py-1" onClick={() => goToWeek(run.previous)} aria-label="Previous week">
              <Icon icon="chevron-left" className="size-4" />
            </button>
            <button
              type="button"
              className="text-default-600 hover:text-default-900 rounded-md px-2 py-1 disabled:opacity-40"
              onClick={() => run.next && goToWeek(run.next)}
              disabled={run.next === null}
              aria-label="Next week">
              <Icon icon="chevron-right" className="size-4" />
            </button>
          </span>
        </div>
      }>
      {run.propertiesWorked === 0 ? (
        <div className="px-6 py-2">
          <DashEmpty>No hours were logged that week.</DashEmpty>
        </div>
      ) : (
        <div className="divide-default-200 grid grid-cols-1 divide-y lg:grid-cols-3 lg:divide-x lg:divide-y-0">
          <div className="px-6 py-5">
            <span className="text-default-500 block text-sm font-medium">Gross pay</span>
            <span className="text-default-900 mt-1 block text-2xl font-semibold tabular-nums">{money(run.gross)}</span>
            <span className="text-default-400 block text-sm">
              {plural(run.contractors, 'contractor', 'contractors')} · {hours(run.hours.regular + run.hours.overtime + run.hours.holiday + run.hours.training)}
            </span>
            <div className="mt-3">
              <Line label="Regular" value={hours(run.hours.regular)} />
              <Line label="Overtime" value={hours(run.hours.overtime)} />
              {run.hours.holiday > 0 && <Line label="Holiday" value={hours(run.hours.holiday)} />}
              {run.hours.training > 0 && <Line label="Training" value={hours(run.hours.training)} />}
            </div>
          </div>

          <div className="px-6 py-5">
            <span className="text-default-500 block text-sm font-medium">Taken out and added</span>
            <span className={cn('mt-1 block text-2xl font-semibold tabular-nums', run.adjustments.net < 0 ? 'text-default-900' : 'text-success')}>
              {money(run.adjustments.net, true)}
            </span>
            <span className="text-default-400 block text-sm">net change to pay</span>
            <div className="mt-3">
              {run.adjustments.lines.length === 0 ? (
                <p className="text-default-400 py-1.5 text-sm">No deductions or incentives this week.</p>
              ) : (
                run.adjustments.lines.map((line) => (
                  <Line key={line.key} label={`${line.label} · ${line.count}`} value={money(line.amount, true)} className={line.amount > 0 ? 'text-success' : ''} />
                ))
              )}
            </div>
          </div>

          <div className="px-6 py-5">
            <span className="text-default-500 block text-sm font-medium">Before you export</span>
            {run.notApproved.properties > 0 ? (
              <>
                <span className="text-warning mt-1 block text-2xl font-semibold tabular-nums">{run.notApproved.contractors}</span>
                <span className="text-warning block text-sm">
                  contractors at {plural(run.notApproved.properties, 'property', 'properties')} not approved yet
                </span>
                <p className="text-default-400 mt-3 text-sm">Their hours can still change until the property manager approves.</p>
                <Link href="/admin/timesheets?tab=pending&sort=submitted_at&direction=asc" className="text-primary mt-2 inline-block text-sm font-medium">
                  See what's waiting →
                </Link>
              </>
            ) : (
              <>
                <span className="text-success mt-1 block text-2xl font-semibold">Ready</span>
                <span className="text-default-400 block text-sm">Every property with hours is approved.</span>
              </>
            )}
          </div>
        </div>
      )}
    </DashCard>
  )
}

const Checks = ({ data, run }: { data: Home['checks']; run: Run }) => (
  <DashCard
    className="xl:col-span-2"
    flush
    title="Check before paying"
    description="Hours that look unusual this run: big overtime, long shifts, punches edited by hand, first weeks."
    aside={data.total > 0 && <span className="badge badge-label bg-light text-default-600 shrink-0">{data.total} to check</span>}
    footer={
      data.total > data.rows.length ? (
        <div className="flex w-full flex-wrap items-center justify-between gap-2">
          <span className="text-default-400">{data.total - data.rows.length} more this run</span>
          <ViewAll href={`/admin/reports/payouts?week=${run.start}`}>Payouts report</ViewAll>
        </div>
      ) : undefined
    }>
    {data.rows.length === 0 ? (
      <div className="px-6 py-2">
        <DashEmpty>Nothing unusual this run.</DashEmpty>
      </div>
    ) : (
      <div className="relative overflow-x-auto">
        <table className="table">
          <thead>
            <tr>
              <th className="ps-6">Contractor</th>
              <th>Property</th>
              <th>What</th>
              <th className="text-end">Pay</th>
              <th className="pe-6">
                <span className="sr-only">Action</span>
              </th>
            </tr>
          </thead>
          <tbody>
            {data.rows.map((row) => (
              <tr key={row.key}>
                <td className="ps-6">
                  <Link href={`/admin/people/${row.personId}`} className="text-default-900 hover:text-primary font-medium">
                    {row.contractor}
                  </Link>
                </td>
                <td>{row.property}</td>
                <td>
                  <span className={cn('badge badge-label whitespace-nowrap', checkTone[row.tone])}>{row.what}</span>
                </td>
                <td className="text-end whitespace-nowrap tabular-nums">{money(row.pay)}</td>
                <td className="pe-6 text-end">
                  <Link href={row.href} className="btn btn-sm border-default-300 hover:border-default-400 border whitespace-nowrap">
                    Open week
                  </Link>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    )}
  </DashCard>
)

const Tasks = ({ data }: { data: Home['tasks'] }) => (
  <DashCard
    title="Your tasks"
    description="From terminations and other workflows."
    footer={data.total > data.rows.length ? <ViewAll href="/admin/tasks">View all {data.total} in My Tasks</ViewAll> : undefined}>
    {data.rows.length === 0 ? (
      <DashEmpty>No tasks are waiting on payroll.</DashEmpty>
    ) : (
      data.rows.map((task) => (
        <DashRow
          key={task.id}
          end={
            <>
              {task.age && <span className="text-default-400 hidden text-sm whitespace-nowrap sm:inline">{task.age}</span>}
              <Link href={task.href} className="btn btn-sm border-default-300 hover:border-default-400 border">
                Open
              </Link>
            </>
          }>
          <div className="text-default-900 font-medium">{task.title}</div>
          {task.detail && <div className="text-default-400 text-sm">{task.detail}</div>}
        </DashRow>
      ))
    )}
  </DashCard>
)

const markPaid = (invoice: Overdue) =>
  confirmAction({
    title: 'Mark paid',
    message: (
      <>
        Mark <strong className="text-default-900">{invoice.number}</strong> ({invoice.property}, {money(invoice.total)}) as paid?
      </>
    ),
    confirmLabel: 'Mark paid',
    tone: 'primary',
    onConfirm: () => router.post(`/admin/invoices/${invoice.id}/paid`, {}, { preserveScroll: true }),
  })

const OverdueInvoices = ({ data }: { data: NonNullable<Home['overdue']> }) => (
  <DashCard
    className="xl:col-span-2"
    flush
    title="Overdue invoices"
    description="Mark them paid as payments arrive. Oldest first."
    aside={data.total > 0 && <span className="badge badge-label bg-danger/15 text-danger shrink-0">{money(data.amount)} overdue</span>}
    footer={<ViewAll href="/admin/invoices?payment=unpaid">All unpaid invoices</ViewAll>}>
    {data.rows.length === 0 ? (
      <div className="px-6 py-2">
        <DashEmpty>No invoice is overdue.</DashEmpty>
      </div>
    ) : (
      <div className="relative overflow-x-auto">
        <table className="table">
          <thead>
            <tr>
              <th className="ps-6">Invoice</th>
              <th>Property</th>
              <th className="text-end">Total</th>
              <th>Late</th>
              <th className="pe-6">
                <span className="sr-only">Action</span>
              </th>
            </tr>
          </thead>
          <tbody>
            {data.rows.map((invoice) => (
              <tr key={invoice.id}>
                <td className="ps-6">
                  <Link href={invoice.href} className="text-default-900 hover:text-primary font-medium whitespace-nowrap">
                    {invoice.number}
                  </Link>
                </td>
                <td>{invoice.property}</td>
                <td className="text-end whitespace-nowrap tabular-nums">{money(invoice.total)}</td>
                <td>
                  <span className={cn('badge badge-label whitespace-nowrap', invoice.daysLate > 30 ? 'bg-danger/15 text-danger' : 'bg-warning/15 text-warning')}>
                    {plural(invoice.daysLate, 'day', 'days')}
                  </span>
                </td>
                <td className="pe-6 text-end">
                  <button type="button" className="btn btn-sm border-default-300 hover:border-default-400 border whitespace-nowrap" onClick={() => markPaid(invoice)}>
                    Mark paid
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    )}
  </DashCard>
)

const StaffTimeOff = ({ data }: { data: Home['timeOff'] }) => (
  <DashCard
    title="Staff time off"
    description="Approved time off in this run and coming up."
    footer={<ViewAll href="/admin/pto">Time-off balances</ViewAll>}>
    {data.rows.length === 0 ? (
      <DashEmpty>No approved time off coming up.</DashEmpty>
    ) : (
      data.rows.map((row) => (
        <DashRow key={row.id} end={<span className={cn('text-sm whitespace-nowrap', row.when === 'this run' ? 'text-info font-medium' : 'text-default-400')}>{row.when}</span>}>
          <div className="text-default-900 font-medium">{row.name}</div>
          <div className="text-default-400 text-sm">{row.detail}</div>
        </DashRow>
      ))
    )}
  </DashCard>
)

const Page = ({ home }: { home: Home }) => (
  <>
    <DashboardHeader
      greeting={home.header.greeting}
      subtitle={`${home.header.date} · Paying the week of ${home.run.label} · All ${home.header.properties} properties`}
      aside={
        (home.actions.upload || home.actions.payouts) && (
          <div className="flex flex-wrap gap-2">
            {home.actions.upload && (
              <Link href="/admin/imports/create" className="btn border-default-300 hover:border-default-400 border">
                <Icon icon="upload" className="size-4.5" />
                Upload hours
              </Link>
            )}
            {home.actions.payouts && (
              <Link href={`/admin/reports/payouts?week=${home.run.start}`} className="btn bg-primary hover:bg-primary-hover text-white">
                <Icon icon="download" className="size-4.5" />
                Export payouts
              </Link>
            )}
          </div>
        )
      }
    />

    <div className="flex flex-col gap-6">
      <PayRun run={home.run} />

      <div className="grid grid-cols-1 items-stretch gap-6 xl:grid-cols-3">
        <Checks data={home.checks} run={home.run} />
        <Tasks data={home.tasks} />
      </div>

      <div className="grid grid-cols-1 items-stretch gap-6 xl:grid-cols-3">
        {home.overdue && <OverdueInvoices data={home.overdue} />}
        <StaffTimeOff data={home.timeOff} />
      </div>
    </div>
  </>
)

export default Page
