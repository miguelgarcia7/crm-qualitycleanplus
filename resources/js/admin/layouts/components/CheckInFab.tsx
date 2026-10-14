import Icon from '@/components/wrappers/Icon'
import { router, usePage } from '@inertiajs/react'
import { useEffect, useMemo, useRef, useState } from 'react'

type Prop = { id: number; name: string; latitude: number | null; longitude: number | null; radius: number; mine: boolean }
type OpenVisit = { id: number; property: string | null; since: string }
type Context = { open_visit: OpenVisit | null; properties: Prop[] }
type Geo = { lat: number; lng: number; accuracy: number | null; status: 'ok' | 'unavailable' | 'denied' }
type Step = 'loading' | 'checkin' | 'checkout' | 'done'
type Near = Prop & { distance: number | null; inside: boolean }

const distanceMeters = (aLat: number, aLng: number, bLat: number, bLng: number): number => {
  const R = 6371000
  const dLat = ((bLat - aLat) * Math.PI) / 180
  const dLng = ((bLng - aLng) * Math.PI) / 180
  const s =
    Math.sin(dLat / 2) ** 2 +
    Math.cos((aLat * Math.PI) / 180) * Math.cos((bLat * Math.PI) / 180) * Math.sin(dLng / 2) ** 2
  return R * 2 * Math.asin(Math.min(1, Math.sqrt(s)))
}

const fmtTime = (d: Date) => d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })
const fmtMinutes = (m: number) => (m >= 60 ? `${Math.floor(m / 60)} h ${m % 60} min` : `${m} min`)
const fmtDistance = (m: number) => (m >= 1000 ? `${(m / 1000).toFixed(1)} km away` : `${Math.round(m)} m away`)
const minutesSince = (iso: string) => Math.max(0, Math.floor((Date.now() - new Date(iso).getTime()) / 60000))

const CHECK_IN_EVENT = 'qcp:check-in'

/** Open the check-in panel from anywhere on the page. */
export const openCheckIn = () => window.dispatchEvent(new Event(CHECK_IN_EVENT))

/**
 * Recruiter check-in / check-out, built for a phone: location is taken as soon
 * as the panel opens, the camera fills whatever space is left, and the main
 * button stays pinned at the bottom, so nothing scrolls. Three taps to check in
 * (pin, selfie, check in), one to check out.
 */
const CheckInFab = () => {
  const permissions = (usePage().props as { auth?: { permissions?: string[] } }).auth?.permissions ?? []
  const enabled = permissions.includes('field_visits.create')

  const [open, setOpen] = useState(false)
  const [step, setStep] = useState<Step>('loading')
  const [ctx, setCtx] = useState<Context | null>(null)
  const [geo, setGeo] = useState<Geo | null>(null)
  const [propertyId, setPropertyId] = useState('')
  const [picking, setPicking] = useState(false)
  const [selfie, setSelfie] = useState<File | null>(null)
  const [cameraError, setCameraError] = useState(false)
  const [cameraTry, setCameraTry] = useState(0)
  const [busy, setBusy] = useState(false)
  const [lateClose, setLateClose] = useState(false)
  const [notes, setNotes] = useState('')
  const [done, setDone] = useState<{ title: string; detail: string } | null>(null)
  const [, setTick] = useState(0)

  const videoRef = useRef<HTMLVideoElement>(null)

  const selfieUrl = useMemo(() => (selfie ? URL.createObjectURL(selfie) : null), [selfie])
  useEffect(() => () => void (selfieUrl && URL.revokeObjectURL(selfieUrl)), [selfieUrl])

  const captureGps = (): Promise<Geo> =>
    new Promise((resolve) => {
      if (!navigator.geolocation) return resolve({ lat: 0, lng: 0, accuracy: null, status: 'unavailable' })
      navigator.geolocation.getCurrentPosition(
        (p) => resolve({ lat: p.coords.latitude, lng: p.coords.longitude, accuracy: Math.round(p.coords.accuracy), status: 'ok' }),
        (err) => resolve({ lat: 0, lng: 0, accuracy: null, status: err.code === err.PERMISSION_DENIED ? 'denied' : 'unavailable' }),
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 },
      )
    })

  const startCheckIn = () => {
    setStep('checkin')
    setGeo(null)
    setPropertyId('')
    setPicking(false)
    setSelfie(null)
    void captureGps().then(setGeo)
  }

  const loadContext = async () => {
    const res = await fetch('/admin/field-visits/context', { headers: { Accept: 'application/json' } })
    const data: Context = await res.json()
    setCtx(data)
    if (data.open_visit) setStep('checkout')
    else startCheckIn()
  }

  const openModal = () => {
    setStep('loading')
    setCtx(null)
    setCameraError(false)
    setLateClose(false)
    setNotes('')
    setDone(null)
    setOpen(true)
    void loadContext()
  }

  const closeModal = () => setOpen(false)

  // Other screens (the recruiter dashboard) open this panel with openCheckIn().
  useEffect(() => {
    const onOpen = () => openModal()
    window.addEventListener(CHECK_IN_EVENT, onOpen)
    return () => window.removeEventListener(CHECK_IN_EVENT, onOpen)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  // The page behind doesn't scroll while the panel is up.
  useEffect(() => {
    if (!open) return
    const before = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    return () => {
      document.body.style.overflow = before
    }
  }, [open])

  // Front camera runs while a selfie is still needed; stopped as soon as it isn't.
  const needCamera = open && step === 'checkin' && selfie === null
  useEffect(() => {
    if (!needCamera) return
    let stream: MediaStream | null = null
    let cancelled = false
    setCameraError(false)
    navigator.mediaDevices
      ?.getUserMedia({ video: { facingMode: 'user' }, audio: false })
      .then((s) => {
        if (cancelled) return s.getTracks().forEach((t) => t.stop())
        stream = s
        if (videoRef.current) videoRef.current.srcObject = s
      })
      .catch(() => !cancelled && setCameraError(true))
    if (!navigator.mediaDevices) setCameraError(true)
    return () => {
      cancelled = true
      stream?.getTracks().forEach((t) => t.stop())
    }
  }, [needCamera, cameraTry])

  // Keep "47 min" current while checked in.
  useEffect(() => {
    if (!open || step !== 'checkout') return
    const id = window.setInterval(() => setTick((t) => t + 1), 30000)
    return () => window.clearInterval(id)
  }, [open, step])

  // Hotels by distance; the one hotel whose area you're inside is picked for you.
  const near: Near[] = useMemo(() => {
    const list = (ctx?.properties ?? []).map((p): Near => {
      const distance =
        geo?.status === 'ok' && p.latitude !== null && p.longitude !== null ? distanceMeters(geo.lat, geo.lng, p.latitude, p.longitude) : null
      return { ...p, distance, inside: distance !== null && distance <= p.radius }
    })
    return geo?.status === 'ok'
      ? list.sort((a, b) => (a.distance ?? Infinity) - (b.distance ?? Infinity))
      : list.sort((a, b) => Number(b.mine) - Number(a.mine) || a.name.localeCompare(b.name))
  }, [ctx, geo])
  const inside = near.filter((p) => p.inside)

  useEffect(() => {
    if (inside.length === 1 && propertyId === '') setPropertyId(String(inside[0].id))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [inside.length])

  const chosen = near.find((p) => String(p.id) === propertyId) ?? null

  const captureSelfie = () => {
    const video = videoRef.current
    if (!video || !video.videoWidth) return
    const canvas = document.createElement('canvas')
    canvas.width = video.videoWidth
    canvas.height = video.videoHeight
    canvas.getContext('2d')?.drawImage(video, 0, 0, canvas.width, canvas.height)
    canvas.toBlob((blob) => blob && setSelfie(new File([blob], 'selfie.jpg', { type: 'image/jpeg' })), 'image/jpeg', 0.85)
  }

  const gpsFields = (g: Geo): Record<string, string> => ({
    gps_status: g.status,
    ...(g.status === 'ok' ? { lat: String(g.lat), lng: String(g.lng), ...(g.accuracy !== null ? { accuracy: String(g.accuracy) } : {}) } : {}),
  })

  const submitCheckIn = () => {
    if (!geo || !selfie || !chosen) return
    setBusy(true)
    const data = new FormData()
    data.append('property_id', String(chosen.id))
    data.append('selfie', selfie)
    Object.entries(gpsFields(geo)).forEach(([k, v]) => data.append(k, v))
    router.post('/admin/field-visits', data, {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => {
        setDone({ title: `Checked in at ${chosen.name}`, detail: `At ${fmtTime(new Date())}. Tap the pin again to check out.` })
        setStep('done')
      },
      onFinish: () => setBusy(false),
    })
  }

  const submitCheckOut = async (late: boolean) => {
    const current = ctx
    const visit = current?.open_visit
    if (!current || !visit) return
    setBusy(true)
    const g = await captureGps()
    router.post(
      '/admin/field-visits/check-out',
      { ...gpsFields(g), late: late ? '1' : '0', ...(late || notes.trim() === '' ? {} : { notes: notes.trim() }) },
      {
        preserveScroll: true,
        onSuccess: () => {
          if (late) {
            // Forgot to check out: the old visit is closed (late); now check in here.
            setLateClose(true)
            setCtx({ ...current, open_visit: null })
            startCheckIn()
          } else {
            const since = new Date(visit.since)
            setDone({
              title: `Checked out of ${visit.property ?? 'the hotel'}`,
              detail: `Visit lasted ${fmtMinutes(minutesSince(visit.since))} · ${fmtTime(since)} to ${fmtTime(new Date())}`,
            })
            setStep('done')
          }
        },
        onFinish: () => setBusy(false),
      },
    )
  }

  if (!enabled) return null

  const closeButton = (
    <button type="button" onClick={closeModal} aria-label="Close" className="bg-default-100 text-default-800 flex size-11 shrink-0 items-center justify-center rounded-full">
      <Icon icon="x" className="text-lg" />
    </button>
  )

  const primary = 'btn bg-primary hover:bg-primary-hover min-h-14 w-full rounded-xl text-base font-semibold text-white disabled:opacity-50'

  return (
    <>
      <button
        onClick={openModal}
        className="bg-primary hover:bg-primary-hover fixed right-8 bottom-16 z-40 flex size-14 items-center justify-center rounded-full text-2xl text-white shadow-lg"
        title="Check in / out"
        aria-label="Check in or out"
      >
        <Icon icon="map-pin" />
      </button>

      {open && (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 sm:items-center sm:p-4" onClick={closeModal}>
          <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="check-in-title"
            className="card m-0 flex h-dvh w-full flex-col gap-3.5 rounded-none px-5 pt-3 pb-[max(1.5rem,env(safe-area-inset-bottom))] sm:h-[min(760px,calc(100dvh-2rem))] sm:max-w-md sm:rounded-2xl"
            onClick={(e) => e.stopPropagation()}
          >
            {step === 'loading' && (
              <>
                <div className="flex min-h-12 items-center justify-between">
                  <h3 id="check-in-title" className="text-xl font-semibold">Check in</h3>
                  {closeButton}
                </div>
                <p className="text-default-500">Loading…</p>
              </>
            )}

            {step === 'checkout' && ctx?.open_visit && (
              <>
                <div className="flex min-h-12 items-center justify-between">
                  <h3 id="check-in-title" className="text-xl font-semibold">You're checked in</h3>
                  {closeButton}
                </div>
                <div className="bg-default-100 flex flex-col items-center gap-1 rounded-2xl px-5 py-7 text-center">
                  <Icon icon="map-pin" className="text-primary text-3xl" />
                  <div className="text-xl font-semibold">{ctx.open_visit.property}</div>
                  <div className="text-default-500 text-sm">Since {fmtTime(new Date(ctx.open_visit.since))}</div>
                  <div className="mt-2 text-4xl font-semibold tabular-nums">{fmtMinutes(minutesSince(ctx.open_visit.since))}</div>
                </div>
                <label className="flex flex-col gap-1.5">
                  <span className="font-semibold">
                    Note for the office <span className="text-default-500 font-normal">(optional)</span>
                  </span>
                  <input
                    className="form-input min-h-12 rounded-xl text-base"
                    maxLength={500}
                    placeholder="e.g. Met with the housekeeping manager"
                    value={notes}
                    onChange={(e) => setNotes(e.target.value)}
                  />
                </label>
                <div className="flex-1" />
                <div className="flex flex-col gap-2.5">
                  <button type="button" className={primary} disabled={busy} onClick={() => submitCheckOut(false)}>
                    {busy ? 'Checking out…' : 'Check out'}
                  </button>
                  <button type="button" className="btn border-default-300 min-h-12 w-full rounded-xl border text-base" disabled={busy} onClick={() => submitCheckOut(true)}>
                    I'm at a different hotel now
                  </button>
                  <p className="text-default-500 text-center text-sm">No selfie needed to check out.</p>
                </div>
              </>
            )}

            {step === 'checkin' && ctx && (
              <>
                <div className="flex min-h-12 items-center justify-between">
                  <h3 id="check-in-title" className="text-xl font-semibold">Check in</h3>
                  {closeButton}
                </div>

                {lateClose && <p className="bg-warning/15 text-warning rounded-xl px-3.5 py-2.5 text-sm">Your last visit was closed. Now check in here.</p>}

                {geo === null ? (
                  <div className="bg-default-100 text-default-600 flex min-h-16 items-center gap-3 rounded-2xl px-3.5">
                    <Icon icon="map-pin" className="text-2xl" />
                    Finding your location…
                  </div>
                ) : chosen && inside.length === 1 && chosen.inside && !picking ? (
                  <div className="bg-success/15 flex items-center gap-3 rounded-2xl px-3.5 py-3">
                    <Icon icon="map-pin" className="text-success shrink-0 text-2xl" />
                    <div className="min-w-0 flex-1">
                      <div className="truncate text-base font-semibold">{chosen.name}</div>
                      <div className="text-success text-sm">You're here{geo.accuracy !== null ? ` · GPS within ${geo.accuracy} m` : ''}</div>
                    </div>
                    <button type="button" className="text-success min-h-11 px-3 font-semibold" onClick={() => setPicking(true)}>
                      Change
                    </button>
                  </div>
                ) : (
                  <HotelPicker geo={geo} near={near} inside={inside} value={propertyId} onChange={setPropertyId} />
                )}

                {chosen && !chosen.mine && <p className="text-default-500 -mt-1 text-sm">Not one of your hotels, so it's marked Covering.</p>}

                <div className="relative flex min-h-0 flex-1 items-center justify-center overflow-hidden rounded-2xl bg-[#252b28]">
                  {selfieUrl ? (
                    <>
                      <img src={selfieUrl} alt="Your selfie" className="size-full object-cover" />
                      <button
                        type="button"
                        onClick={() => setSelfie(null)}
                        className="absolute bottom-4 left-1/2 flex min-h-11 -translate-x-1/2 items-center gap-2 rounded-xl bg-black/70 px-4 font-semibold text-white"
                      >
                        <Icon icon="refresh" /> Retake
                      </button>
                    </>
                  ) : cameraError ? (
                    <div className="flex flex-col items-center gap-3 px-6 text-center text-white">
                      <Icon icon="camera-off" className="text-4xl" />
                      <p>The camera is needed for your check-in selfie. Allow it for this site in your browser settings.</p>
                      <button type="button" className="min-h-11 rounded-xl bg-white px-4 font-semibold text-[#252b28]" onClick={() => setCameraTry((n) => n + 1)}>
                        Try again
                      </button>
                    </div>
                  ) : (
                    <>
                      <video ref={videoRef} autoPlay playsInline muted className="size-full -scale-x-100 object-cover" />
                      <div aria-hidden="true" className="pointer-events-none absolute inset-x-0 top-3 text-center text-sm text-white/85">
                        Face in the frame, then tap the button
                      </div>
                      <button
                        type="button"
                        onClick={captureSelfie}
                        aria-label="Take selfie"
                        className="absolute bottom-4 left-1/2 size-18 -translate-x-1/2 rounded-full border-4 border-white bg-white/25"
                      />
                    </>
                  )}
                </div>

                <div className="flex flex-col gap-2">
                  <button type="button" className={primary} disabled={busy || !geo || !selfie || !chosen} onClick={submitCheckIn}>
                    {busy ? 'Checking in…' : chosen ? `Check in at ${chosen.name}` : 'Check in'}
                  </button>
                  <p className="text-default-500 text-center text-sm">
                    {!geo ? 'Getting your location…' : !chosen ? 'Pick the hotel' : !selfie ? 'Take your selfie to check in' : `Checks in at ${fmtTime(new Date())}`}
                  </p>
                </div>
              </>
            )}

            {step === 'done' && done && (
              <>
                <div className="flex flex-1 flex-col items-center justify-center gap-2.5 text-center">
                  <div className="bg-success/15 flex size-20 items-center justify-center rounded-full">
                    <Icon icon="check" className="text-success text-4xl" />
                  </div>
                  <h3 id="check-in-title" className="mt-2 text-2xl font-semibold">{done.title}</h3>
                  <p className="text-default-500">{done.detail}</p>
                </div>
                <button type="button" className={primary} onClick={closeModal}>
                  Done
                </button>
              </>
            )}
          </div>
        </div>
      )}
    </>
  )
}

/** Which hotel, when GPS didn't settle it: the ones you're inside as big buttons, the rest in a list. */
const HotelPicker = ({
  geo,
  near,
  inside,
  value,
  onChange,
}: {
  geo: Geo
  near: Near[]
  inside: Near[]
  value: string
  onChange: (id: string) => void
}) => {
  const others = near.filter((p) => !p.inside)
  const label = (p: Near) => (p.distance !== null ? `${p.name} · ${fmtDistance(p.distance)}` : p.name)
  const prompt =
    geo.status !== 'ok'
      ? "Couldn't get your location. Which hotel?"
      : inside.length > 1
        ? `You're near ${inside.length} hotels. Which one?`
        : inside.length === 1
          ? 'Which hotel?'
          : "You're not inside a hotel's area. Which hotel?"

  return (
    <fieldset className="flex flex-col gap-2">
      <legend className="mb-2 text-base font-semibold">{prompt}</legend>
      {inside.map((p) => (
        <button
          key={p.id}
          type="button"
          aria-pressed={value === String(p.id)}
          onClick={() => onChange(String(p.id))}
          className={`flex min-h-13 w-full items-center justify-between rounded-xl px-3.5 text-base font-semibold ${
            value === String(p.id) ? 'border-primary bg-primary/10 border-2' : 'border-default-300 border'
          }`}
        >
          {p.name}
          {p.distance !== null && <span className="text-default-500 text-sm font-normal">{fmtDistance(p.distance)}</span>}
        </button>
      ))}
      <label className="sr-only" htmlFor="check-in-hotel">
        Hotel
      </label>
      <select
        id="check-in-hotel"
        className="form-select min-h-12 rounded-xl text-base"
        value={inside.some((p) => String(p.id) === value) ? '' : value}
        onChange={(e) => onChange(e.target.value)}
      >
        <option value="">{inside.length > 0 ? 'Somewhere else?' : 'Pick the hotel'}</option>
        {others.some((p) => p.mine) && (
          <optgroup label="Your hotels">
            {others.filter((p) => p.mine).map((p) => (
              <option key={p.id} value={p.id}>
                {label(p)}
              </option>
            ))}
          </optgroup>
        )}
        {others.some((p) => !p.mine) && (
          <optgroup label="Other hotels (covering)">
            {others.filter((p) => !p.mine).map((p) => (
              <option key={p.id} value={p.id}>
                {label(p)}
              </option>
            ))}
          </optgroup>
        )}
      </select>
    </fieldset>
  )
}

export default CheckInFab
