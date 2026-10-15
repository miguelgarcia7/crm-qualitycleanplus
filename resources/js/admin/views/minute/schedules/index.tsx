import { confirmAction } from '@/components/ConfirmHost'
import PageBreadcrumb from '@/components/PageBreadcrumb'
import SidePanel from '@/components/SidePanel'
import { Pill } from '@/components/timesheet/PmTimesheet'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head, router, useForm } from '@inertiajs/react'
import { DragEvent, FormEvent, useRef, useState } from 'react'

type ScheduleFile = { id: number; name: string; type: string; size: number | null; by: string | null; at: string | null }
type Day = { date: string; day: number; in_month: boolean; today: boolean }
type Week = {
  week_start: string
  label: string
  long_label: string
  when: string | null
  is_past: boolean
  is_current: boolean
  is_next: boolean
  can_change: boolean
  days: Day[]
  note: string | null
  files: ScheduleFile[]
}
type Calendar = { label: string; previous: string; next: string | null; days: string[]; weeks: Week[] }

type Props = {
  properties: { id: number; name: string }[]
  property: { id: number; name: string } | null
  calendar: Calendar | null
  month: string | null
}

const TYPE_TONE: Record<string, string> = {
  PDF: 'bg-danger/15 text-danger',
  XLS: 'bg-success/15 text-success',
  DOC: 'bg-info/15 text-info',
  JPG: 'bg-secondary/15 text-secondary',
  PNG: 'bg-secondary/15 text-secondary',
}

const FileBadge = ({ type }: { type: string }) => (
  <span className={cn('flex h-8 w-7 shrink-0 items-center justify-center rounded text-[10px] font-bold', TYPE_TONE[type] ?? 'bg-light text-default-900')}>{type}</span>
)

/**
 * QC Minute → Schedules. The property manager adds their hotel's schedule for
 * each work week, in whatever files they keep it: a month calendar with a
 * "Schedule for the week" column on a computer, the weeks as cards on a phone.
 * Their recruiter is told and can download it.
 */
const Page = ({ properties, property, calendar, month }: Props) => {
  const [uploading, setUploading] = useState<Week | null>(null)
  const [showEarlier, setShowEarlier] = useState(false)

  const go = (params: Record<string, string>) =>
    router.get('/schedules', { ...(property && properties.length > 1 ? { property: String(property.id) } : {}), ...(month ? { month } : {}), ...params }, { preserveScroll: true })

  const removeFile = (file: ScheduleFile, week: Week) =>
    confirmAction({
      title: 'Remove file',
      message: (
        <>
          Remove <strong className="text-default-900">{file.name}</strong> from the schedule for {week.label}?
        </>
      ),
      confirmLabel: 'Remove',
      onConfirm: () => router.delete(`/schedules/files/${file.id}`, { preserveScroll: true }),
    })

  if (property === null || calendar === null) {
    return (
      <>
        <Head title="Schedules" />
        <PageBreadcrumb title="Schedules" />
        <div className="card">
          <div className="card-body text-default-500">You're not set up for a property yet. Your QCP contact can add you.</div>
        </div>
      </>
    )
  }

  const fileList = (week: Week, compact = false) =>
    week.files.map((f) => (
      <div key={f.id} className={cn('bg-card border-default-300 flex items-center gap-2 rounded-lg border', compact ? 'px-2 py-1.5' : 'min-h-11 px-2.5 py-1.5')}>
        <FileBadge type={f.type} />
        <a href={`/schedules/files/${f.id}`} className="text-default-900 hover:text-primary min-w-0 flex-1 truncate text-sm font-semibold" title={`${f.name}${f.by ? ` · ${f.by}` : ''}${f.at ? `, ${f.at}` : ''}`}>
          {f.name}
        </a>
        {week.can_change && (
          <button type="button" className="text-default-500 hover:text-danger flex size-8 items-center justify-center rounded" aria-label={`Remove ${f.name}`} onClick={() => removeFile(f, week)}>
            <Icon icon="x" className="size-4" />
          </button>
        )}
      </div>
    ))

  // The week column on a computer: its files, then the way to add more.
  const slot = (week: Week) => (
    <div className="flex flex-col gap-2">
      {(week.is_current || week.is_next) && (
        <span className="self-start">
          {week.files.length > 0 ? (
            <Pill tone="success">{`${week.when} · ${week.files.length} ${week.files.length === 1 ? 'file' : 'files'}`}</Pill>
          ) : (
            <Pill tone={week.is_next ? 'warning' : 'muted'}>{`${week.when} · not uploaded`}</Pill>
          )}
        </span>
      )}
      {fileList(week, true)}
      {week.can_change &&
        (week.files.length === 0 && (week.is_current || week.is_next) ? (
          <button
            type="button"
            className="border-default-400 bg-card text-default-900 hover:border-primary flex items-center justify-center gap-2 rounded-lg border-2 border-dashed px-3 py-3 text-sm font-semibold"
            onClick={() => setUploading(week)}>
            <Icon icon="upload" className="text-primary size-4" /> Upload schedule
          </button>
        ) : (
          <button type="button" className="text-primary inline-flex items-center gap-1.5 self-start text-sm font-semibold" onClick={() => setUploading(week)}>
            <Icon icon="plus" className="size-4" /> {week.files.length ? 'Add a file' : 'Upload early'}
          </button>
        ))}
      {week.note && <p className="text-default-500 text-xs">Note: {week.note}</p>}
    </div>
  )

  const past = calendar.weeks.filter((w) => w.is_past)
  const coming = calendar.weeks.filter((w) => !w.is_past)

  return (
    <>
      <Head title="Schedules" />
      <PageBreadcrumb title="Schedules" subtitle={property.name} />

      <div className="mb-5 flex flex-wrap items-end justify-between gap-3">
        <p className="text-default-500 max-w-2xl">
          Add your schedule for each work week, in whatever form you keep it: a PDF, a spreadsheet, a photo. A week can have several files. Your QCP recruiter gets them right away.
        </p>
        <div className="flex flex-wrap items-center gap-2">
          {properties.length > 1 && (
            <select className="form-select w-auto" aria-label="Property" value={property.id} onChange={(e) => router.get('/schedules', { property: e.target.value })}>
              {properties.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name}
                </option>
              ))}
            </select>
          )}
          <button type="button" className="btn btn-icon border-default-300 border" aria-label="Previous month" onClick={() => go({ month: calendar.previous })}>
            <Icon icon="chevron-left" />
          </button>
          <span className="text-default-900 min-w-32 text-center font-semibold">{calendar.label}</span>
          <button type="button" className="btn btn-icon border-default-300 border disabled:opacity-40" aria-label="Next month" disabled={!calendar.next} onClick={() => calendar.next && go({ month: calendar.next })}>
            <Icon icon="chevron-right" />
          </button>
        </div>
      </div>

      {/* A computer (xl and up): the month, one row per work week, its schedule on the right. */}
      <div className="card hidden overflow-x-auto xl:block">
        <div className="grid min-w-[880px]" style={{ gridTemplateColumns: 'repeat(7, minmax(0, 1fr)) minmax(340px, 1.6fr)' }}>
          {calendar.days.map((d) => (
            <div key={d} className="text-default-500 border-default-300 border-b px-3 py-2.5 text-xs font-semibold tracking-wider uppercase">
              {d}
            </div>
          ))}
          <div className="text-default-500 border-default-300 bg-light/40 border-b px-4 py-2.5 text-xs font-semibold tracking-wider uppercase">Schedule for the week</div>

          {calendar.weeks.map((w) => (
            <div key={w.week_start} className="contents">
              {w.days.map((d) => (
                <div
                  key={d.date}
                  className={cn(
                    'border-default-200 min-h-24 border-e border-b px-3 py-2 text-sm font-semibold',
                    d.in_month ? 'text-default-900' : 'text-default-500',
                    w.is_next && w.files.length === 0 && 'bg-warning/5',
                  )}>
                  {d.today ? <span className="bg-default-900 inline-flex size-6 items-center justify-center rounded-full text-white">{d.day}</span> : d.day}
                </div>
              ))}
              <div className={cn('border-default-200 bg-light/40 border-b px-4 py-3', w.is_next && w.files.length === 0 && 'bg-warning/10')}>{slot(w)}</div>
            </div>
          ))}
        </div>
      </div>
      <p className="text-default-500 mt-3 hidden text-sm xl:block">
        Each row is one work week at {property.name}. Files belong to the week, not to a day. You can remove a file until the week is over.
      </p>

      {/* A tablet or phone: no calendar, the weeks as cards. */}
      <div className="space-y-3 xl:hidden">
        {coming.map((w) => (
          <section key={w.week_start} className={cn('card mb-0', w.is_next && w.files.length === 0 && 'ring-warning ring-2')} aria-label={w.long_label}>
            <div className="card-body space-y-3 p-4">
              <div className="flex items-center justify-between gap-2">
                <span className="text-default-500 text-xs font-semibold tracking-wider uppercase">{w.when ?? 'Week'}</span>
                {w.files.length > 0 ? (
                  <Pill tone="success">{`${w.files.length} ${w.files.length === 1 ? 'file' : 'files'}`}</Pill>
                ) : (
                  (w.is_current || w.is_next) && <Pill tone="warning">Not uploaded</Pill>
                )}
              </div>
              <div className="text-default-900 text-lg font-semibold">{w.long_label}</div>
              {fileList(w)}
              {w.note && <p className="text-default-500 text-sm">Note: {w.note}</p>}
              {w.can_change && (
                <button
                  type="button"
                  className={cn('btn w-full py-3 font-semibold', w.files.length === 0 && (w.is_current || w.is_next) ? 'bg-primary hover:bg-primary-hover text-white' : 'border-default-300 border')}
                  onClick={() => setUploading(w)}>
                  {w.files.length ? 'Add a file' : w.is_current || w.is_next ? 'Upload schedule' : 'Upload early'}
                </button>
              )}
            </div>
          </section>
        ))}
        {past.length > 0 &&
          (showEarlier ? (
            past.map((w) => (
              <section key={w.week_start} className="card mb-0" aria-label={w.long_label}>
                <div className="card-body space-y-3 p-4">
                  <div className="text-default-900 font-semibold">{w.long_label}</div>
                  {w.files.length ? fileList(w) : <p className="text-default-500 text-sm">No schedule uploaded.</p>}
                </div>
              </section>
            ))
          ) : (
            <button type="button" className="btn border-default-300 w-full border py-3" onClick={() => setShowEarlier(true)}>
              Earlier weeks this month
            </button>
          ))}
      </div>

      {uploading && <UploadPanel key={uploading.week_start} propertyId={property.id} week={uploading} onClose={() => setUploading(null)} />}
    </>
  )
}

/**
 * Upload a week's schedule: drop files or choose them, or take a photo on a
 * phone; an optional note for the recruiter.
 */
const UploadPanel = ({ propertyId, week, onClose }: { propertyId: number; week: Week; onClose: () => void }) => {
  const form = useForm<{ files: File[]; note: string }>({ files: [], note: week.note ?? '' })
  const { data, setData, errors, processing, progress } = form
  const [dragging, setDragging] = useState(false)
  const pick = useRef<HTMLInputElement>(null)
  const photo = useRef<HTMLInputElement>(null)

  const add = (list: FileList | null) => {
    if (list) setData('files', [...data.files, ...Array.from(list)])
  }

  const drop = (e: DragEvent) => {
    e.preventDefault()
    setDragging(false)
    add(e.dataTransfer.files)
  }

  const submit = (e: FormEvent) => {
    e.preventDefault()
    form.post(`/schedules/${propertyId}/${week.week_start}`, { forceFormData: true, preserveScroll: true, onSuccess: onClose })
  }

  const fileErrors = Object.entries(errors)
    .filter(([k]) => k === 'files' || k.startsWith('files.'))
    .map(([, v]) => v)
  const noteChanged = (data.note ?? '') !== (week.note ?? '')
  const label = data.files.length ? `Upload ${data.files.length} ${data.files.length === 1 ? 'file' : 'files'}` : noteChanged ? 'Save note' : 'Upload'

  return (
    <SidePanel
      title={week.long_label}
      subtitle={week.when ? `${week.when}'s schedule` : 'Schedule for the week'}
      onClose={onClose}
      footer={
        <>
          <span />
          <div className="flex gap-2">
            <button type="button" className="btn border-default-300 border" onClick={onClose}>
              Cancel
            </button>
            <button type="submit" form="schedule-upload" className="btn bg-primary hover:bg-primary-hover font-semibold text-white" disabled={processing || (data.files.length === 0 && !noteChanged)}>
              {label}
            </button>
          </div>
        </>
      }>
      <form id="schedule-upload" onSubmit={submit} className="space-y-5">
        <div
          onDragOver={(e) => {
            e.preventDefault()
            setDragging(true)
          }}
          onDragLeave={() => setDragging(false)}
          onDrop={drop}
          className={cn('hidden flex-col items-center gap-2 rounded-xl border-2 border-dashed px-4 py-8 text-center lg:flex', dragging ? 'border-primary bg-primary/5' : 'border-default-400 bg-light/40')}>
          <Icon icon="upload" className="text-primary size-7" />
          <span className="text-default-900 font-semibold">Drop your schedule here</span>
          <span className="text-default-500 text-sm">PDF, Excel, Word, or a photo · up to 20 MB each · several are fine</span>
          <button type="button" className="btn border-default-300 bg-card mt-1 border" onClick={() => pick.current?.click()}>
            Choose files
          </button>
        </div>

        {/* A phone: the camera, or a file from the phone. */}
        <div className="grid grid-cols-2 gap-3 lg:hidden">
          <button type="button" className="btn border-default-300 bg-card flex h-20 flex-col gap-1.5 border" onClick={() => photo.current?.click()}>
            <Icon icon="camera" className="text-primary size-6" /> Take a photo
          </button>
          <button type="button" className="btn border-default-300 bg-card flex h-20 flex-col gap-1.5 border" onClick={() => pick.current?.click()}>
            <Icon icon="file-upload" className="text-primary size-6" /> Choose a file
          </button>
        </div>
        <input
          ref={pick}
          type="file"
          multiple
          className="hidden"
          accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,image/*"
          onChange={(e) => {
            add(e.target.files)
            e.target.value = ''
          }}
        />
        <input
          ref={photo}
          type="file"
          accept="image/*"
          capture="environment"
          className="hidden"
          onChange={(e) => {
            add(e.target.files)
            e.target.value = ''
          }}
        />

        {data.files.length > 0 && (
          <div className="space-y-2">
            {data.files.map((f, i) => (
              <div key={`${f.name}-${i}`} className="bg-card border-default-300 flex min-h-11 items-center gap-2.5 rounded-lg border px-2.5 py-1.5">
                <Icon icon="file" className="text-default-500 size-5 shrink-0" />
                <span className="text-default-900 min-w-0 flex-1 truncate text-sm font-semibold">{f.name}</span>
                <span className="text-default-500 text-xs">{f.size >= 1048576 ? `${(f.size / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(f.size / 1024))} KB`}</span>
                <button
                  type="button"
                  className="text-default-500 hover:text-danger flex size-8 items-center justify-center rounded"
                  aria-label={`Remove ${f.name}`}
                  onClick={() =>
                    setData(
                      'files',
                      data.files.filter((_, j) => j !== i),
                    )
                  }>
                  <Icon icon="x" className="size-4" />
                </button>
              </div>
            ))}
          </div>
        )}
        {fileErrors.length > 0 && <p className="text-danger text-sm">{fileErrors[0]}</p>}
        {progress && <progress value={progress.percentage} max="100" className="w-full" />}

        {week.files.length > 0 && (
          <p className="text-default-500 text-sm">
            Already on this week: {week.files.map((f) => f.name).join(', ')}. New files are added next to them.
          </p>
        )}

        <div>
          <label htmlFor="schedule-note" className="form-label">
            Note for your recruiter <span className="text-default-500 font-normal">(optional)</span>
          </label>
          <textarea id="schedule-note" className="form-input" rows={3} maxLength={1000} value={data.note} onChange={(e) => setData('note', e.target.value)} />
          {errors.note && <p className="text-danger mt-1 text-sm">{errors.note}</p>}
        </div>
        <p className="text-default-500 text-sm">Your QCP recruiter is told right away, with a link to download it.</p>
      </form>
    </SidePanel>
  )
}

export default Page
