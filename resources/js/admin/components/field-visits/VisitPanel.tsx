import SidePanel from '@/components/SidePanel'
import Icon from '@/components/wrappers/Icon'
import { useForm } from '@inertiajs/react'
import { FormEvent, ReactNode, useState } from 'react'

type Place = { gps_status: string | null; accuracy: number | null; distance: number | null; maps_url: string | null }

export type VisitPanelData = {
  id: number
  recruiter: string | null
  recruiter_id: number
  property: string | null
  property_id: number
  timezone: string
  open: boolean
  open_hours: number | null
  in: string
  out: string | null
  minutes: number | null
  off_site: boolean
  late: boolean
  covering: boolean
  closed_by: string | null
  notes: string | null
  check_in: Place
  check_out: Place | null
  radius: number | null
  selfie_url: string | null
  in_local: string
  out_local: string | null
  now_local: string
  history: { id: number; at: string | null; text: string; by: string | null }[]
  can: { close: boolean; fix: boolean }
}

const fmtMinutes = (m: number) => (m >= 60 ? `${Math.floor(m / 60)} h ${m % 60} min` : `${m} min`)

/** Where the recruiter stood, against the hotel's check-in area. */
const whereText = (p: Place, radius: number | null) => {
  if (p.gps_status === 'denied') return 'No location: the phone refused to share it'
  // GPS was taken but is gone: the yearly purge removed it.
  if (p.gps_status === 'ok' && p.maps_url === null) return 'Location deleted after a year'
  if (p.distance === null) return 'No location: GPS wasn’t available'
  if (radius !== null && p.distance <= radius) return `Inside the hotel’s check-in area (${p.distance} m from it)`
  const km = p.distance >= 1000 ? `${(p.distance / 1000).toFixed(1)} km` : `${p.distance} m`
  return radius !== null ? `${km} from the hotel, outside its ${radius} m check-in area` : `${km} from the hotel`
}

const Fact = ({ label, children }: { label: string; children: ReactNode }) => (
  <div className="grid grid-cols-[6.5rem_minmax(0,1fr)] gap-3 py-1 text-sm">
    <span className="text-default-500">{label}</span>
    <span className="text-default-900">{children}</span>
  </div>
)

const Section = ({ title, children, tinted }: { title: string; children: ReactNode; tinted?: boolean }) => (
  <section className={tinted ? 'bg-light/60 -mx-5 px-5 py-4' : 'py-1'}>
    <h5 className="text-default-900 mb-2 font-semibold">{title}</h5>
    {children}
  </section>
)

/**
 * One field visit, from the Field Visits list or a hotel's Visits tab: the
 * selfie and location the office checks on a dispute, closing a visit the
 * recruiter forgot (admin, office manager, HR), fixing its times (super admin,
 * with a reason), and the visit's History.
 */
const VisitPanel = ({ visit, onClose }: { visit: VisitPanelData; onClose: () => void }) => {
  const close = useForm({ left_at: visit.now_local, note: '' })
  const fix = useForm({ check_in_at: visit.in_local, check_out_at: visit.out_local ?? '', reason: '' })
  const [fixing, setFixing] = useState(false)

  const submitClose = (e: FormEvent) => {
    e.preventDefault()
    close.post(`/admin/field-visits/${visit.id}/close`, { preserveScroll: true, preserveState: true })
  }

  const submitFix = (e: FormEvent) => {
    e.preventDefault()
    fix.put(`/admin/field-visits/${visit.id}/times`, { preserveScroll: true, preserveState: true, onSuccess: () => setFixing(false) })
  }

  return (
    <SidePanel
      title={`${visit.recruiter ?? 'Recruiter'} at ${visit.property ?? 'a hotel'}`}
      subtitle={
        <div className="mt-1.5 flex flex-wrap gap-1">
          {visit.open && <span className="badge badge-label bg-danger/15 text-danger">Open {visit.open_hours ?? 0} h</span>}
          {visit.off_site && <span className="badge badge-label bg-warning/15 text-warning">Off-site</span>}
          {visit.late && <span className="badge badge-label bg-secondary/15 text-secondary">Closed late</span>}
          {visit.covering && <span className="badge badge-label bg-info/15 text-info">Covering</span>}
          {!visit.open && !visit.off_site && !visit.late && !visit.covering && <span className="badge badge-label bg-success/15 text-success">No flags</span>}
        </div>
      }
      onClose={onClose}
    >
      <Section title="Check-in">
        <div className="flex gap-4">
          {visit.selfie_url ? (
            <a href={visit.selfie_url} target="_blank" rel="noreferrer" className="shrink-0" title="Open the selfie full size">
              <img src={visit.selfie_url} alt={`Check-in selfie of ${visit.recruiter ?? 'the recruiter'}`} className="h-36 w-28 rounded-lg object-cover" />
            </a>
          ) : (
            <div className="bg-light text-default-500 flex h-36 w-28 shrink-0 items-center justify-center rounded-lg p-2 text-center text-xs">No selfie kept</div>
          )}
          <div className="min-w-0 flex-1">
            <Fact label="When">{visit.in}</Fact>
            <Fact label="Where">{whereText(visit.check_in, visit.radius)}</Fact>
            {visit.check_in.accuracy !== null && <Fact label="GPS">Accurate to about {visit.check_in.accuracy} m</Fact>}
            {visit.check_in.maps_url && (
              <Fact label="Map">
                <a href={visit.check_in.maps_url} target="_blank" rel="noreferrer" className="text-primary underline underline-offset-3">
                  Open in Google Maps
                </a>
              </Fact>
            )}
          </div>
        </div>
      </Section>

      <Section title="Check-out">
        {visit.open ? (
          <p className="text-default-500 text-sm">Not checked out yet. It closes, marked closed late, the next time {visit.recruiter ?? 'they'} check in.</p>
        ) : (
          <>
            <Fact label="When">{visit.out}</Fact>
            {visit.minutes !== null && <Fact label="Time on site">{fmtMinutes(visit.minutes)}</Fact>}
            {visit.closed_by ? (
              <Fact label="Closed by">{visit.closed_by}, for the recruiter</Fact>
            ) : visit.check_out ? (
              <Fact label="Where">{whereText(visit.check_out, visit.radius)}</Fact>
            ) : null}
          </>
        )}
        {visit.notes && <Fact label="Their note">{visit.notes}</Fact>}
      </Section>

      {visit.can.close && (
        <Section title="Close this visit" tinted>
          <p className="text-default-500 mb-3 text-sm">For when they forgot to check out. It shows as closed late, closed by you.</p>
          <form onSubmit={submitClose} className="space-y-3">
            <div>
              <label htmlFor="visit-left-at" className="form-label">
                They left at <span className="text-default-500 font-normal">(hotel time)</span>
              </label>
              <input
                id="visit-left-at"
                type="datetime-local"
                className="form-input"
                min={visit.in_local}
                max={visit.now_local}
                value={close.data.left_at}
                onChange={(e) => close.setData('left_at', e.target.value)}
                required
              />
              {close.errors.left_at && <p className="text-danger mt-1 text-sm">{close.errors.left_at}</p>}
            </div>
            <div>
              <label htmlFor="visit-close-note" className="form-label">
                Note <span className="text-default-500 font-normal">(optional, kept on History)</span>
              </label>
              <textarea id="visit-close-note" className="form-input" rows={2} maxLength={500} value={close.data.note} onChange={(e) => close.setData('note', e.target.value)} />
            </div>
            <div className="flex justify-end">
              <button type="submit" className="btn bg-primary hover:bg-primary-hover font-semibold text-white" disabled={close.processing}>
                Close visit
              </button>
            </div>
          </form>
        </Section>
      )}

      {visit.can.fix && (
        <Section title="Fix the times">
          {!fixing ? (
            <div className="flex flex-wrap items-center justify-between gap-3">
              <p className="text-default-500 text-sm">Super admin only. Needs a reason, kept on History.</p>
              <button type="button" className="btn border-default-300 border" onClick={() => setFixing(true)}>
                Fix times
              </button>
            </div>
          ) : (
            <form onSubmit={submitFix} className="space-y-3">
              <div className="grid gap-3 sm:grid-cols-2">
                <div>
                  <label htmlFor="visit-fix-in" className="form-label">
                    Checked in
                  </label>
                  <input id="visit-fix-in" type="datetime-local" className="form-input" max={visit.now_local} value={fix.data.check_in_at} onChange={(e) => fix.setData('check_in_at', e.target.value)} required />
                </div>
                <div>
                  <label htmlFor="visit-fix-out" className="form-label">
                    Checked out
                  </label>
                  <input id="visit-fix-out" type="datetime-local" className="form-input" max={visit.now_local} value={fix.data.check_out_at} onChange={(e) => fix.setData('check_out_at', e.target.value)} />
                </div>
              </div>
              {(fix.errors.check_in_at || fix.errors.check_out_at) && <p className="text-danger text-sm">{fix.errors.check_in_at ?? fix.errors.check_out_at}</p>}
              <div>
                <label htmlFor="visit-fix-reason" className="form-label">
                  Reason
                </label>
                <textarea id="visit-fix-reason" className="form-input" rows={2} maxLength={500} value={fix.data.reason} onChange={(e) => fix.setData('reason', e.target.value)} required />
                {fix.errors.reason && <p className="text-danger mt-1 text-sm">{fix.errors.reason}</p>}
              </div>
              <div className="flex justify-end gap-2">
                <button type="button" className="btn border-default-300 border" onClick={() => setFixing(false)}>
                  Cancel
                </button>
                <button type="submit" className="btn bg-primary hover:bg-primary-hover font-semibold text-white" disabled={fix.processing}>
                  Save times
                </button>
              </div>
            </form>
          )}
        </Section>
      )}

      <Section title="History">
        {visit.history.length === 0 ? (
          <p className="text-default-500 text-sm">Nothing recorded for this visit.</p>
        ) : (
          <ol className="space-y-2 text-sm">
            {visit.history.map((h) => (
              <li key={h.id} className="grid grid-cols-[6.5rem_minmax(0,1fr)] gap-3">
                <span className="text-default-500">{h.at}</span>
                <span className="text-default-900">
                  {h.text}
                  {h.by && <span className="text-default-500"> · {h.by}</span>}
                </span>
              </li>
            ))}
          </ol>
        )}
        <p className="text-default-500 mt-4 text-xs">The selfie and location are deleted after 1 year. The visit itself stays.</p>
      </Section>
    </SidePanel>
  )
}

export default VisitPanel
