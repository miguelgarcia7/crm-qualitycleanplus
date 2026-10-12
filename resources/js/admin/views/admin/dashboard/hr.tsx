import { confirmAction } from '@/components/ConfirmHost'
import DashCard, { DashEmpty, DashRow, ViewAll } from '@/components/dashboard/DashCard'
import DashboardHeader from '@/components/dashboard/DashboardHeader'
import RequestList, { Requests } from '@/components/dashboard/RequestList'
import StaffAwayCard, { StaffAway } from '@/components/dashboard/StaffAwayCard'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Link, router } from '@inertiajs/react'

type Applicant = { id: number; name: string; missing: string[]; role: string; age: string; href: string }
type Termination = { key: string; title: string; detail: string; age: string | null; action: { label: string; href: string } }
type Tier = { id: number; name: string; detail: string; days: number; href: string }

type Home = {
  header: { greeting: string; date: string; contractors: number; staff: number }
  actions: { invite: boolean; newPosting: boolean }
  hiring: { new: number; inReview: number; missingDocs: number; ready: number; placed: number; openPostings: number }
  waiting: Requests
  missing: { rows: Applicant[]; total: number }
  ready: { rows: Applicant[]; total: number; canPromote: boolean }
  terminations: { rows: Termination[]; total: number } | null
  staffAway: StaffAway
  tiers: Tier[]
}

const plural = (n: number, one: string, many: string) => `${n.toLocaleString()} ${n === 1 ? one : many}`

/** Hiring from application to contractor; "In review" is the step HR works. */
const Hiring = ({ hiring }: { hiring: Home['hiring'] }) => {
  const stages = [
    { key: 'new', label: 'New applications', value: hiring.new, note: 'not opened yet', href: '/admin/applicants?status=submitted', mine: false },
    {
      key: 'review',
      label: 'In review',
      value: hiring.inReview,
      note: hiring.missingDocs > 0 ? `${hiring.missingDocs} missing documents` : 'all documents in',
      href: '/admin/applicants?status=reviewing',
      mine: true,
    },
    { key: 'ready', label: 'Ready to promote', value: hiring.ready, note: 'all documents in', href: '#ready', mine: false },
    { key: 'placed', label: 'Became contractors', value: hiring.placed, note: 'this month', href: '/admin/applicants?status=promoted', mine: false },
  ]

  return (
    <DashCard
      flush
      title="Hiring"
      description="From application to contractor. Open a step to work it."
      aside={
        <Link href="/admin/job-postings" className="badge badge-label bg-light text-default-600 shrink-0">
          {plural(hiring.openPostings, 'open posting', 'open postings')}
        </Link>
      }>
      <div className="grid sm:grid-cols-2 lg:grid-cols-4">
        {stages.map((stage) => (
          <Link
            key={stage.key}
            href={stage.href}
            className={cn(
              'border-default-200 hover:bg-default-50 block border-b px-6 py-4 transition-colors lg:border-e lg:border-b-0 lg:last:border-e-0',
              stage.mine && stage.value > 0 && 'bg-warning/10 hover:bg-warning/15 shadow-[inset_0_-3px_0_var(--color-warning)]',
            )}>
            <span className="text-default-500 block text-sm font-medium">{stage.label}</span>
            <span className={cn('mt-1 block text-2xl font-semibold tabular-nums', stage.mine && stage.value > 0 ? 'text-warning' : 'text-default-900')}>
              {stage.value}
            </span>
            <span className={cn('mt-0.5 block text-sm', stage.mine && stage.value > 0 ? 'text-warning' : 'text-default-400')}>{stage.note}</span>
          </Link>
        ))}
      </div>
    </DashCard>
  )
}

const MissingDocuments = ({ data }: { data: Home['missing'] }) => (
  <DashCard
    title="Missing documents"
    description="Applicants in review who can't be promoted yet. Longest waiting first."
    footer={data.total > data.rows.length ? <ViewAll href="/admin/applicants?status=reviewing">All {data.total}</ViewAll> : undefined}>
    {data.rows.length === 0 ? (
      <DashEmpty>Every applicant in review has their documents in.</DashEmpty>
    ) : (
      data.rows.map((row) => (
        <DashRow
          key={row.id}
          top
          end={
            <>
              <span className="text-default-400 hidden text-sm whitespace-nowrap sm:inline">{row.age}</span>
              <Link href={row.href} className="btn btn-sm border-default-300 hover:border-default-400 border">
                Open
              </Link>
            </>
          }>
          <div className="text-default-900 font-medium">{row.name}</div>
          <div className="mt-1 flex flex-wrap gap-1.5">
            {row.missing.map((doc) => (
              <span key={doc} className="border-default-300 text-default-600 rounded-full border px-2 py-0.5 text-xs whitespace-nowrap">
                {doc}
              </span>
            ))}
          </div>
        </DashRow>
      ))
    )}
  </DashCard>
)

const promote = (applicant: Applicant) =>
  confirmAction({
    title: 'Promote to contractor',
    message: (
      <>
        Make <strong className="text-default-900">{applicant.name}</strong> an active contractor? Every onboarding item is in.
      </>
    ),
    confirmLabel: 'Promote',
    tone: 'primary',
    onConfirm: () => router.post(`/admin/applicants/${applicant.id}/promote`, {}, { preserveScroll: true }),
  })

const ReadyToPromote = ({ data }: { data: Home['ready'] }) => (
  <div id="ready" className="scroll-mt-24 xl:col-span-2">
    <DashCard
      title="Ready to promote"
      description="Every onboarding item is in. One click makes them a contractor."
      footer={data.total > data.rows.length ? <ViewAll href="/admin/applicants?status=reviewing">All {data.total}</ViewAll> : undefined}>
      {data.rows.length === 0 ? (
        <DashEmpty>Nobody is ready yet.</DashEmpty>
      ) : (
        data.rows.map((row) => (
          <DashRow
            key={row.id}
            end={
              data.canPromote ? (
                <button type="button" className="btn btn-sm bg-primary hover:bg-primary-hover text-white" onClick={() => promote(row)}>
                  Promote
                </button>
              ) : (
                <Link href={row.href} className="btn btn-sm border-default-300 hover:border-default-400 border">
                  Open
                </Link>
              )
            }>
            <Link href={row.href} className="text-default-900 hover:text-primary font-medium">
              {row.name}
            </Link>
            {row.role && <div className="text-default-400 text-sm">{row.role}</div>}
          </DashRow>
        ))
      )}
    </DashCard>
  </div>
)

const Terminations = ({ data }: { data: NonNullable<Home['terminations']> }) => (
  <DashCard
    title="Terminations in progress"
    description="Each one's next step and who it's waiting on."
    footer={data.total > data.rows.length ? <ViewAll href="/admin/terminations">All {data.total}</ViewAll> : undefined}>
    {data.rows.length === 0 ? (
      <DashEmpty>No terminations in progress.</DashEmpty>
    ) : (
      data.rows.map((row) => (
        <DashRow
          key={row.key}
          end={
            <Link href={row.action.href} className="btn btn-sm border-default-300 hover:border-default-400 border">
              {row.action.label}
            </Link>
          }>
          <div className="text-default-900 font-medium">{row.title}</div>
          <div className="text-default-400 text-sm">
            {row.detail}
            {row.age && ` · started ${row.age === 'today' ? 'today' : `${row.age} ago`}`}
          </div>
        </DashRow>
      ))
    )}
  </DashCard>
)

const Tiers = ({ rows }: { rows: Tier[] }) => (
  <DashCard title="Moving up a time-off tier" description="Staff whose hire anniversary raises how much time off they get, next 45 days.">
    {rows.length === 0 ? (
      <DashEmpty>Nobody changes tier in the next 45 days.</DashEmpty>
    ) : (
      rows.map((row) => (
        <DashRow key={row.id} end={<span className="text-default-400 text-sm whitespace-nowrap">{plural(row.days, 'day', 'days')}</span>}>
          <Link href={row.href} className="text-default-900 hover:text-primary font-medium">
            {row.name}
          </Link>
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
      subtitle={`${home.header.date} · ${plural(home.header.contractors, 'contractor', 'contractors')} · ${home.header.staff} staff`}
      aside={
        (home.actions.invite || home.actions.newPosting) && (
          <div className="flex flex-wrap gap-2">
            {home.actions.invite && (
              <Link href="/admin/people/invite" className="btn border-default-300 hover:border-default-400 border">
                <Icon icon="user-plus" className="size-4.5" />
                Invite staff
              </Link>
            )}
            {home.actions.newPosting && (
              <Link href="/admin/job-postings" className="btn bg-primary hover:bg-primary-hover text-white">
                <Icon icon="plus" className="size-4.5" />
                New job posting
              </Link>
            )}
          </div>
        )
      }
    />

    <div className="flex flex-col gap-6">
      <Hiring hiring={home.hiring} />

      <div className="grid grid-cols-1 items-stretch gap-6 xl:grid-cols-2">
        <RequestList title="Waiting on you" description="Requests HR verifies or approves, oldest first." emptyText="Nothing is waiting on HR." data={home.waiting} />
        <MissingDocuments data={home.missing} />
      </div>

      <div className="grid grid-cols-1 items-stretch gap-6 xl:grid-cols-3">
        <ReadyToPromote data={home.ready} />
        {home.terminations && <Terminations data={home.terminations} />}
      </div>

      <div className="grid grid-cols-1 items-stretch gap-6 xl:grid-cols-2">
        <StaffAwayCard title="Staff away · next 30 days" data={home.staffAway} description="Approved time off." />
        <Tiers rows={home.tiers} />
      </div>
    </div>
  </>
)

export default Page
