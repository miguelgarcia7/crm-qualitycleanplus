import { confirmAction } from '@/components/ConfirmHost'
import DashCard, { DashEmpty, DashRow, ViewAll } from '@/components/dashboard/DashCard'
import DashboardHeader from '@/components/dashboard/DashboardHeader'
import LowStockCard, { LowStock } from '@/components/dashboard/LowStockCard'
import SystemAlerts, { SystemAlert } from '@/components/dashboard/SystemAlerts'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Link, router } from '@inertiajs/react'

type ReadyInvoice = { id: number; number: string; propertyId: number; property: string | null; email: string | null; total: number; href: string }

type Pipeline = {
  notSubmitted: number
  notSubmittedWeeks: number
  awaiting: number
  awaitingOverdue: number
  ready: number
  readyAmount: number
  sent: number
  canSend: boolean
  invoices: ReadyInvoice[]
}

type Punch = { id: number; contractor: string; personId: number; property: string; clockedIn: string | null; recruiter: string | null; href: string }
type ImportRow = { id: number; name: string; in: boolean; detail: string; action: { label: string; href: string } }
type Supply = { id: number; title: string; detail: string; age: string | null }
type Inquiry = { key: string; type: 'business' | 'job_seeker'; title: string; detail: string; age: string | null }

type Home = {
  header: { greeting: string; date: string; properties: number }
  actions: { newWorkOrder: boolean; upload: boolean }
  alerts: SystemAlert[]
  pipeline: Pipeline
  punches: { rows: Punch[]; total: number; hours: number }
  imports: { rows: ImportRow[]; total: number; in: number } | null
  supplies: { rows: Supply[]; total: number } | null
  stock: LowStock | null
  inquiries: { rows: Inquiry[]; total: number } | null
}

const money = (n: number) => `$${n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
const plural = (n: number, one: string, many: string) => `${n.toLocaleString()} ${n === 1 ? one : many}`

const send = (invoice: ReadyInvoice) =>
  confirmAction({
    title: 'Send invoice',
    message: (
      <>
        Email <strong className="text-default-900">{invoice.number}</strong> ({invoice.property}, {money(invoice.total)}) to{' '}
        <strong className="text-default-900">{invoice.email}</strong>?
      </>
    ),
    confirmLabel: 'Send',
    tone: 'primary',
    onConfirm: () => router.post(`/admin/invoices/${invoice.id}/send`, { recipient: invoice.email }, { preserveScroll: true }),
  })

/** Hours on their way to invoices across all properties, and the invoices ready to send. */
const HoursToInvoices = ({ p }: { p: Pipeline }) => {
  const stages = [
    {
      key: 'draft',
      label: 'Not submitted',
      value: p.notSubmitted,
      note: p.notSubmitted > 0 ? `properties · ${plural(p.notSubmittedWeeks, 'finished week', 'finished weeks')}` : 'Every finished week is in',
      href: '/admin/timesheets?tab=draft',
      highlight: false,
    },
    {
      key: 'awaiting',
      label: 'Awaiting PM approval',
      value: p.awaiting,
      note: p.awaitingOverdue > 0 ? `${p.awaitingOverdue} past 3 days` : p.awaiting > 0 ? 'All within 3 days' : 'Nothing waiting',
      href: '/admin/timesheets?tab=pending&sort=submitted_at&direction=asc',
      highlight: false,
    },
    { key: 'ready', label: 'Ready to send', value: p.ready, note: p.ready > 0 ? `${money(p.readyAmount)} · list below` : 'Nothing to send', href: '#send', highlight: p.ready > 0 },
    { key: 'sent', label: 'Sent', value: p.sent, note: 'this week', href: '/admin/invoices?status=invoice_sent', highlight: false },
  ]

  return (
    <DashCard
      flush
      title="Hours to invoices"
      description="Across all properties. Invoices that are ready are yours to send."
      footer={p.ready > p.invoices.length ? <ViewAll href="/admin/invoices?status=invoiced">All {p.ready} ready to send</ViewAll> : undefined}>
      <div className="border-default-200 grid border-b sm:grid-cols-2 lg:grid-cols-4">
        {stages.map((stage) => (
          <Link
            key={stage.key}
            href={stage.href}
            className={cn(
              'border-default-200 hover:bg-default-50 block border-b px-6 py-4 transition-colors lg:border-e lg:border-b-0 lg:last:border-e-0',
              stage.highlight && 'bg-primary/5 hover:bg-primary/10 shadow-[inset_0_-3px_0_var(--color-primary)]',
            )}>
            <span className="text-default-500 block text-sm font-medium">{stage.label}</span>
            <span className="text-default-900 mt-1 block text-2xl font-semibold tabular-nums">{stage.value}</span>
            <span className={cn('mt-0.5 block text-sm', stage.key === 'awaiting' && p.awaitingOverdue > 0 ? 'text-warning' : 'text-default-400')}>{stage.note}</span>
          </Link>
        ))}
      </div>

      <div id="send" className="scroll-mt-24 px-6 pt-4 pb-1">
        <h5 className="text-default-900 font-semibold">Ready to send · oldest first</h5>
      </div>
      {p.invoices.length === 0 ? (
        <div className="px-6 pb-2">
          <DashEmpty>No invoices are waiting to be sent.</DashEmpty>
        </div>
      ) : (
        <div className="relative overflow-x-auto">
          <table className="table">
            <thead>
              <tr>
                <th className="ps-6">Invoice</th>
                <th>Property</th>
                <th>Billing email</th>
                <th className="text-end">Total</th>
                <th className="pe-6">
                  <span className="sr-only">Action</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {p.invoices.map((invoice) => (
                <tr key={invoice.id}>
                  <td className="ps-6">
                    <Link href={invoice.href} className="text-default-900 hover:text-primary font-medium whitespace-nowrap">
                      {invoice.number}
                    </Link>
                  </td>
                  <td>{invoice.property}</td>
                  <td>{invoice.email ?? <span className="badge badge-label bg-warning/15 text-warning">No billing email</span>}</td>
                  <td className="text-end whitespace-nowrap tabular-nums">{money(invoice.total)}</td>
                  <td className="pe-6 text-end">
                    {!p.canSend ? (
                      <Link href={invoice.href} className="btn btn-sm border-default-300 hover:border-default-400 border">
                        Open
                      </Link>
                    ) : invoice.email ? (
                      <button type="button" className="btn btn-sm bg-primary hover:bg-primary-hover text-white" onClick={() => send(invoice)}>
                        Send
                      </button>
                    ) : (
                      <Link href={`/admin/properties/${invoice.propertyId}#settings/billing`} className="btn btn-sm border-default-300 hover:border-default-400 border whitespace-nowrap">
                        Add email
                      </Link>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </DashCard>
  )
}

const Punches = ({ data, className }: { data: Home['punches']; className?: string }) => (
  <DashCard
    className={className}
    flush
    title="Punches to fix"
    description={`Clocked in more than ${data.hours} hours ago and never clocked out.`}
    aside={data.total > 0 && <span className="badge badge-label bg-danger/15 text-danger shrink-0">{data.total} open</span>}
    footer={data.total > data.rows.length ? <span className="text-default-400">{data.total - data.rows.length} more open punches</span> : undefined}>
    {data.rows.length === 0 ? (
      <div className="px-6 py-2">
        <DashEmpty>No punches are left open.</DashEmpty>
      </div>
    ) : (
      <div className="relative overflow-x-auto">
        <table className="table">
          <thead>
            <tr>
              <th className="ps-6">Contractor</th>
              <th>Property</th>
              <th>Clocked in</th>
              <th>Recruiter</th>
              <th className="pe-6">
                <span className="sr-only">Action</span>
              </th>
            </tr>
          </thead>
          <tbody>
            {data.rows.map((punch) => (
              <tr key={punch.id}>
                <td className="ps-6">
                  <Link href={`/admin/people/${punch.personId}`} className="text-default-900 hover:text-primary font-medium">
                    {punch.contractor}
                  </Link>
                </td>
                <td>{punch.property}</td>
                <td className="whitespace-nowrap">{punch.clockedIn}</td>
                <td>{punch.recruiter ?? <span className="text-default-400">None assigned</span>}</td>
                <td className="pe-6 text-end">
                  <Link href={punch.href} className="btn btn-sm border-default-300 hover:border-default-400 border">
                    Fix
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

const Imports = ({ data }: { data: NonNullable<Home['imports']> }) => (
  <DashCard
    title="Hour imports"
    description="Properties that send a spreadsheet instead of clocking in. Last week's file."
    aside={data.total > 0 && <span className={cn('badge badge-label shrink-0', data.in === data.total ? 'bg-success/15 text-success' : 'bg-light text-default-600')}>{data.in} of {data.total} in</span>}
    footer={<ViewAll href="/admin/imports">All imports</ViewAll>}>
    {data.rows.length === 0 ? (
      <DashEmpty>No property sends hours by spreadsheet.</DashEmpty>
    ) : (
      data.rows.map((row) => (
        <DashRow
          key={row.id}
          end={
            <Link
              href={row.action.href}
              className={cn('btn btn-sm border', row.in ? 'border-default-300 hover:border-default-400' : 'bg-primary hover:bg-primary-hover border-transparent text-white')}>
              {row.action.label}
            </Link>
          }>
          <div className="text-default-900 font-medium">{row.name}</div>
          <div className={cn('text-sm', row.in ? 'text-default-400' : 'text-warning')}>{row.detail}</div>
        </DashRow>
      ))
    )}
  </DashCard>
)

const Supplies = ({ data }: { data: NonNullable<Home['supplies']> }) => (
  <DashCard title="Supply requests" description="Waiting to be filled, oldest first." footer={<ViewAll href="/admin/requests">All supply requests{data.total > data.rows.length ? ` (${data.total})` : ''}</ViewAll>}>
    {data.rows.length === 0 ? (
      <DashEmpty>No supply requests are waiting.</DashEmpty>
    ) : (
      data.rows.map((row) => (
        <DashRow key={row.id} end={row.age && <span className="text-default-400 text-sm whitespace-nowrap">{row.age}</span>}>
          <div className="text-default-900 font-medium">{row.title}</div>
          {row.detail && <div className="text-default-400 text-sm">{row.detail}</div>}
        </DashRow>
      ))
    )}
  </DashCard>
)

const Inquiries = ({ data }: { data: NonNullable<Home['inquiries']> }) => (
  <DashCard title="Website inquiries" description="New messages from the contact forms." footer={<ViewAll href="/admin/inquiries">All inquiries</ViewAll>}>
    {data.rows.length === 0 ? (
      <DashEmpty>No new inquiries.</DashEmpty>
    ) : (
      data.rows.map((row) => (
        <DashRow key={row.key} end={row.age && <span className="text-default-400 text-sm whitespace-nowrap">{row.age}</span>}>
          <div className="flex items-start gap-3">
            <span
              className={cn(
                'badge badge-label mt-0.5 w-24 shrink-0 justify-center',
                row.type === 'business' ? 'bg-success/15 text-success' : 'bg-light text-default-600',
              )}>
              {row.type === 'business' ? 'Business' : 'Job seeker'}
            </span>
            <div className="min-w-0">
              <div className="text-default-900 font-medium">{row.title}</div>
              {row.detail && <div className="text-default-400 truncate text-sm">{row.detail}</div>}
            </div>
          </div>
        </DashRow>
      ))
    )}
  </DashCard>
)

const Page = ({ home }: { home: Home }) => {
  const bottom = [
    home.supplies && <Supplies key="supplies" data={home.supplies} />,
    home.stock && <LowStockCard key="stock" data={home.stock} />,
    home.inquiries && <Inquiries key="inquiries" data={home.inquiries} />,
  ].filter(Boolean)

  return (
    <>
      <DashboardHeader
        greeting={home.header.greeting}
        subtitle={`${home.header.date} · All ${home.header.properties} properties`}
        aside={
          (home.actions.newWorkOrder || home.actions.upload) && (
            <div className="flex flex-wrap gap-2">
              {home.actions.newWorkOrder && (
                <Link href="/admin/work-orders/create" className="btn border-default-300 hover:border-default-400 border">
                  <Icon icon="plus" className="size-4.5" />
                  New work order
                </Link>
              )}
              {home.actions.upload && (
                <Link href="/admin/imports/create" className="btn bg-primary hover:bg-primary-hover text-white">
                  <Icon icon="upload" className="size-4.5" />
                  Upload hours
                </Link>
              )}
            </div>
          )
        }
      />

      <div className="flex flex-col gap-6">
        <SystemAlerts alerts={home.alerts} />

        <HoursToInvoices p={home.pipeline} />

        <div className="grid grid-cols-1 items-stretch gap-6 xl:grid-cols-3">
          <Punches data={home.punches} className={home.imports ? 'xl:col-span-2' : 'xl:col-span-3'} />
          {home.imports && <Imports data={home.imports} />}
        </div>

        {bottom.length > 0 && <div className="grid grid-cols-1 items-stretch gap-6 lg:grid-cols-2 xl:grid-cols-3">{bottom}</div>}
      </div>
    </>
  )
}

export default Page
