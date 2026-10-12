import DashCard, { DashEmpty, DashRow, ViewAll } from '@/components/dashboard/DashCard'
import DashboardHeader from '@/components/dashboard/DashboardHeader'
import LowStockCard, { LowStock } from '@/components/dashboard/LowStockCard'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Link, router } from '@inertiajs/react'
import { FormEvent, useState } from 'react'

type Supply = { id: number; title: string; detail: string; stock: { text: string; short: boolean }; age: string | null }
type Delivery = { id: number; title: string; detail: string }
type Step = { id: number; title: string; items: string[]; detail: string | null; age: string | null; href: string }
type Applicant = { id: number; name: string; missing: string[]; role: string; age: string; href: string }

type Home = {
  header: { greeting: string; date: string }
  actions: { searchPeople: boolean; searchApplicants: boolean; receive: boolean }
  supplies: { rows: Supply[]; total: number } | null
  deliveries: { rows: Delivery[]; total: number } | null
  terminations: { rows: Step[]; total: number } | null
  documents: { rows: Applicant[]; total: number } | null
  stock: LowStock | null
}

const Chip = ({ children }: { children: string }) => (
  <span className="border-default-300 text-default-600 rounded-full border px-2 py-0.5 text-xs whitespace-nowrap">{children}</span>
)

const OpenButton = ({ href, children, primary }: { href: string; children: string; primary?: boolean }) => (
  <Link
    href={href}
    className={cn(
      'btn btn-sm border whitespace-nowrap',
      primary ? 'bg-primary hover:bg-primary-hover border-transparent text-white' : 'border-default-300 hover:border-default-400',
    )}>
    {children}
  </Link>
)

/** The desk's first move when someone walks in: find them. */
const FindPerson = ({ actions }: { actions: Home['actions'] }) => {
  const [query, setQuery] = useState('')
  const term = query.trim()

  const findContractor = () => term && router.get('/admin/people', { search: term, status: 'all' })
  const findApplicant = () => term && router.get('/admin/applicants', { status: 'all', search: term })

  const submit = (e: FormEvent) => {
    e.preventDefault()
    if (actions.searchPeople) findContractor()
    else findApplicant()
  }

  return (
    <form onSubmit={submit} className="flex flex-wrap items-center gap-2" role="search">
      <div className="input-icon-group w-full sm:w-72">
        <Icon icon="search" className="input-icon" />
        <input className="form-input" placeholder="Find a contractor or applicant…" aria-label="Name, email or phone" value={query} onChange={(e) => setQuery(e.target.value)} />
      </div>
      {actions.searchPeople && (
        <button type="submit" className="btn border-default-300 hover:border-default-400 border" disabled={!term}>
          Contractors
        </button>
      )}
      {actions.searchApplicants && (
        <button type="button" className="btn border-default-300 hover:border-default-400 border" onClick={findApplicant} disabled={!term}>
          Applicants
        </button>
      )}
    </form>
  )
}

const Supplies = ({ data, className }: { data: NonNullable<Home['supplies']>; className?: string }) => (
  <DashCard
    className={className}
    title="Supply requests to fill"
    description="Pick, pack and fill on the Supply Requests page. Oldest first."
    aside={data.total > 0 && <span className="badge badge-label bg-light text-default-600 shrink-0">{data.total} waiting</span>}
    footer={<ViewAll href="/admin/requests">All supply requests</ViewAll>}>
    {data.rows.length === 0 ? (
      <DashEmpty>No supply requests are waiting.</DashEmpty>
    ) : (
      data.rows.map((row) => (
        <DashRow
          key={row.id}
          end={
            <>
              {row.age && <span className="text-default-500 hidden text-sm whitespace-nowrap sm:inline">{row.age}</span>}
              <OpenButton href="/admin/requests" primary>
                Fill
              </OpenButton>
            </>
          }>
          <div className="text-default-900 font-medium">{row.title}</div>
          <div className="text-default-500 text-sm">
            {row.detail && `${row.detail} · `}
            <span className={row.stock.short ? 'text-warning' : ''}>{row.stock.text}</span>
          </div>
        </DashRow>
      ))
    )}
  </DashCard>
)

const Deliveries = ({ data }: { data: NonNullable<Home['deliveries']> }) => (
  <DashCard title="Deliveries expected" description="Purchase orders on the way." footer={<ViewAll href="/admin/inventory/purchase-orders">All purchase orders</ViewAll>}>
    {data.rows.length === 0 ? (
      <DashEmpty>Nothing is on order.</DashEmpty>
    ) : (
      data.rows.map((row) => (
        <DashRow key={row.id} end={<OpenButton href="/admin/inventory/purchase-orders">Receive</OpenButton>}>
          <div className="text-default-900 font-medium">{row.title}</div>
          <div className="text-default-500 text-sm">{row.detail}</div>
        </DashRow>
      ))
    )}
  </DashCard>
)

const Terminations = ({ data }: { data: NonNullable<Home['terminations']> }) => (
  <DashCard
    title="Terminations · your steps"
    description="Get the equipment back, then move the file."
    footer={data.total > data.rows.length ? <ViewAll href="/admin/tasks">All {data.total} in My Tasks</ViewAll> : undefined}>
    {data.rows.length === 0 ? (
      <DashEmpty>No termination steps are waiting on you.</DashEmpty>
    ) : (
      data.rows.map((row) => (
        <DashRow key={row.id} top end={<OpenButton href={row.href}>Open</OpenButton>}>
          <div className="text-default-900 font-medium">{row.title}</div>
          {row.items.length > 0 && (
            <div className="mt-1 flex flex-wrap gap-1.5">
              {row.items.map((item, i) => (
                <Chip key={`${item}-${i}`}>{item}</Chip>
              ))}
            </div>
          )}
          {row.detail && <div className="text-default-500 text-sm">{row.detail}</div>}
        </DashRow>
      ))
    )}
  </DashCard>
)

const Documents = ({ data }: { data: NonNullable<Home['documents']> }) => (
  <DashCard
    title="Documents to collect"
    description="Applicants missing paperwork. Open their checklist to upload it when they come by."
    footer={data.total > data.rows.length ? <ViewAll href="/admin/applicants?status=reviewing">All {data.total}</ViewAll> : undefined}>
    {data.rows.length === 0 ? (
      <DashEmpty>No applicant is missing paperwork.</DashEmpty>
    ) : (
      data.rows.map((row) => (
        <DashRow
          key={row.id}
          top
          end={
            <OpenButton href={row.href} primary>
              Open checklist
            </OpenButton>
          }>
          <div className="text-default-900 font-medium">{row.name}</div>
          <div className="mt-1 flex flex-wrap gap-1.5">
            {row.missing.map((doc) => (
              <Chip key={doc}>{doc}</Chip>
            ))}
          </div>
        </DashRow>
      ))
    )}
  </DashCard>
)

const Page = ({ home }: { home: Home }) => {
  const people = [
    home.terminations && <Terminations key="terminations" data={home.terminations} />,
    home.documents && <Documents key="documents" data={home.documents} />,
    home.stock && <LowStockCard key="stock" data={home.stock} />,
  ].filter(Boolean)

  return (
    <>
      <DashboardHeader
        greeting={home.header.greeting}
        subtitle={home.header.date}
        aside={
          <div className="flex flex-wrap items-center gap-2">
            {(home.actions.searchPeople || home.actions.searchApplicants) && <FindPerson actions={home.actions} />}
            {home.actions.receive && (
              <Link href="/admin/inventory/purchase-orders" className="btn bg-primary hover:bg-primary-hover text-white">
                <Icon icon="truck-delivery" className="size-4.5" />
                Receive a delivery
              </Link>
            )}
          </div>
        }
      />

      <div className="flex flex-col gap-6">
        {(home.supplies || home.deliveries) && (
          <div className="grid grid-cols-1 items-stretch gap-6 xl:grid-cols-3">
            {home.supplies && <Supplies data={home.supplies} className={home.deliveries ? 'xl:col-span-2' : 'xl:col-span-3'} />}
            {home.deliveries && <Deliveries data={home.deliveries} />}
          </div>
        )}

        {people.length > 0 && <div className="grid grid-cols-1 items-stretch gap-6 lg:grid-cols-2 xl:grid-cols-3">{people}</div>}
      </div>
    </>
  )
}

export default Page
