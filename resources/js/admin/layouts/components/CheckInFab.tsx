import { router, usePage } from '@inertiajs/react'
import { useEffect, useRef, useState } from 'react'

type Prop = { id: number; name: string; latitude: number | null; longitude: number | null; radius: number }
type OpenVisit = { id: number; property: string | null; since: string }
type Context = { open_visit: OpenVisit | null; properties: Prop[] }
type Geo = { lat: number; lng: number; accuracy: number | null; status: 'ok' | 'unavailable' | 'denied' }

const distanceMeters = (aLat: number, aLng: number, bLat: number, bLng: number): number => {
  const R = 6371000
  const dLat = ((bLat - aLat) * Math.PI) / 180
  const dLng = ((bLng - aLng) * Math.PI) / 180
  const s =
    Math.sin(dLat / 2) ** 2 +
    Math.cos((aLat * Math.PI) / 180) * Math.cos((bLat * Math.PI) / 180) * Math.sin(dLng / 2) ** 2
  return R * 2 * Math.asin(Math.min(1, Math.sqrt(s)))
}

const CheckInFab = () => {
  const permissions = (usePage().props as { auth?: { permissions?: string[] } }).auth?.permissions ?? []
  const enabled = permissions.includes('field_visits.create')

  const [open, setOpen] = useState(false)
  const [ctx, setCtx] = useState<Context | null>(null)
  const [mode, setMode] = useState<'checkout' | 'checkin'>('checkin')
  const [geo, setGeo] = useState<Geo | null>(null)
  const [propertyId, setPropertyId] = useState('')
  const [selfie, setSelfie] = useState<File | null>(null)
  const [cameraError, setCameraError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [lateClose, setLateClose] = useState(false)

  const videoRef = useRef<HTMLVideoElement>(null)
  const streamRef = useRef<MediaStream | null>(null)

  const reset = () => {
    setCtx(null)
    setGeo(null)
    setPropertyId('')
    setSelfie(null)
    setCameraError(null)
    setLateClose(false)
    setMode('checkin')
  }

  const loadContext = async () => {
    const res = await fetch('/admin/field-visits/context', { headers: { Accept: 'application/json' } })
    const data: Context = await res.json()
    setCtx(data)
    setMode(data.open_visit ? 'checkout' : 'checkin')
  }

  const openModal = () => {
    reset()
    setOpen(true)
    void loadContext()
  }

  const closeModal = () => {
    streamRef.current?.getTracks().forEach((t) => t.stop())
    streamRef.current = null
    setOpen(false)
  }

  // Start the camera once we're in a step that needs a selfie (check-in).
  const needSelfie = open && mode === 'checkin' && ctx !== null
  useEffect(() => {
    if (!needSelfie) return
    let cancelled = false
    navigator.mediaDevices
      ?.getUserMedia({ video: { facingMode: 'user' } })
      .then((stream) => {
        if (cancelled) return stream.getTracks().forEach((t) => t.stop())
        streamRef.current = stream
        if (videoRef.current) videoRef.current.srcObject = stream
      })
      .catch(() => setCameraError('Camera permission is required to check in.'))
    return () => {
      cancelled = true
    }
  }, [needSelfie])

  const captureGps = (): Promise<Geo> =>
    new Promise((resolve) => {
      if (!navigator.geolocation) return resolve({ lat: 0, lng: 0, accuracy: null, status: 'unavailable' })
      navigator.geolocation.getCurrentPosition(
        (p) => resolve({ lat: p.coords.latitude, lng: p.coords.longitude, accuracy: Math.round(p.coords.accuracy), status: 'ok' }),
        (err) => resolve({ lat: 0, lng: 0, accuracy: null, status: err.code === err.PERMISSION_DENIED ? 'denied' : 'unavailable' }),
        { enableHighAccuracy: true, timeout: 10000 },
      )
    })

  const grabLocation = async () => {
    const g = await captureGps()
    setGeo(g)
    if (g.status === 'ok' && ctx) {
      const near = ctx.properties.filter(
        (p) => p.latitude !== null && p.longitude !== null && distanceMeters(g.lat, g.lng, p.latitude, p.longitude) <= p.radius,
      )
      if (near.length === 1) setPropertyId(String(near[0].id))
    }
  }

  const captureSelfie = () => {
    const video = videoRef.current
    if (!video) return
    const canvas = document.createElement('canvas')
    canvas.width = video.videoWidth || 480
    canvas.height = video.videoHeight || 640
    canvas.getContext('2d')?.drawImage(video, 0, 0, canvas.width, canvas.height)
    canvas.toBlob((blob) => blob && setSelfie(new File([blob], 'selfie.jpg', { type: 'image/jpeg' })), 'image/jpeg', 0.85)
  }

  const gpsFields = (data: FormData, g: Geo) => {
    if (g.status === 'ok') {
      data.append('lat', String(g.lat))
      data.append('lng', String(g.lng))
      if (g.accuracy !== null) data.append('accuracy', String(g.accuracy))
    }
    data.append('gps_status', g.status)
  }

  const submitCheckIn = () => {
    if (!geo || !selfie || !propertyId) return
    setBusy(true)
    const data = new FormData()
    data.append('property_id', propertyId)
    data.append('selfie', selfie)
    gpsFields(data, geo)
    router.post('/admin/field-visits', data, {
      forceFormData: true,
      onSuccess: closeModal,
      onFinish: () => setBusy(false),
    })
  }

  const submitCheckOut = async (late: boolean) => {
    setBusy(true)
    const g = await captureGps()
    const data: Record<string, string> = { gps_status: g.status, late: late ? '1' : '0' }
    if (g.status === 'ok') {
      data.lat = String(g.lat)
      data.lng = String(g.lng)
      if (g.accuracy !== null) data.accuracy = String(g.accuracy)
    }
    router.post('/admin/field-visits/check-out', data, {
      preserveScroll: true,
      onSuccess: () => {
        if (late) {
          // forgot-to-check-out: prior visit closed, now check in at the new property
          setBusy(false)
          setLateClose(true)
          setMode('checkin')
          void loadContext().then(() => setMode('checkin'))
        } else {
          closeModal()
        }
      },
      onFinish: () => setBusy(false),
    })
  }

  if (!enabled) return null

  return (
    <>
      <button
        onClick={openModal}
        className="bg-primary hover:bg-primary-hover fixed bottom-6 right-6 z-40 flex size-14 items-center justify-center rounded-full text-2xl text-white shadow-lg"
        title="Check in / out"
        aria-label="Check in or out"
      >
        📍
      </button>

      {open && (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 sm:items-center" onClick={closeModal}>
          <div className="card w-full max-w-md rounded-2xl" onClick={(e) => e.stopPropagation()}>
            <div className="card-body p-6">
              {!ctx ? (
                <p className="text-default-500">Loading…</p>
              ) : mode === 'checkout' && ctx.open_visit ? (
                <>
                  <h3 className="mb-1 text-lg font-semibold">You're checked in</h3>
                  <p className="text-default-500 mb-4 text-sm">
                    {ctx.open_visit.property} · since {new Date(ctx.open_visit.since).toLocaleTimeString()}
                  </p>
                  <button className="btn bg-primary mb-2 w-full py-2.5 font-semibold text-white disabled:opacity-50" disabled={busy} onClick={() => submitCheckOut(false)}>
                    Check out
                  </button>
                  <button className="btn btn-light w-full" disabled={busy} onClick={() => submitCheckOut(true)}>
                    I'm at a different property
                  </button>
                </>
              ) : (
                <>
                  <h3 className="mb-1 text-lg font-semibold">Check in</h3>
                  {lateClose && <p className="text-warning mb-2 text-sm">Previous visit closed (late). Now check in here.</p>}

                  <div className="mb-3">
                    {geo ? (
                      <p className={geo.status === 'ok' ? 'text-success text-sm' : 'text-warning text-sm'}>
                        {geo.status === 'ok' ? `✓ Location captured (±${geo.accuracy ?? '?'}m)` : 'Location unavailable — pick the property manually.'}
                      </p>
                    ) : (
                      <button className="btn btn-light w-full" onClick={grabLocation}>
                        Share my location
                      </button>
                    )}
                  </div>

                  <label className="mb-3 block">
                    <span className="form-label">Property</span>
                    <select className="form-select w-full" value={propertyId} onChange={(e) => setPropertyId(e.target.value)}>
                      <option value="">Select…</option>
                      {ctx.properties.map((p) => (
                        <option key={p.id} value={p.id}>
                          {p.name}
                        </option>
                      ))}
                    </select>
                  </label>

                  <div className="mb-4">
                    {cameraError ? (
                      <p className="text-danger text-sm">{cameraError}</p>
                    ) : selfie ? (
                      <p className="text-success text-sm">✓ Selfie captured</p>
                    ) : (
                      <>
                        <video ref={videoRef} autoPlay playsInline muted className="mb-2 w-full rounded-lg bg-black" />
                        <button className="btn btn-light w-full" onClick={captureSelfie}>
                          Take selfie
                        </button>
                      </>
                    )}
                  </div>

                  <button
                    className="btn bg-primary w-full py-2.5 font-semibold text-white disabled:opacity-50"
                    disabled={busy || !geo || !selfie || !propertyId}
                    onClick={submitCheckIn}
                  >
                    Confirm check-in
                  </button>
                </>
              )}

              <button className="text-default-400 mt-3 w-full text-center text-sm" onClick={closeModal}>
                Cancel
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  )
}

export default CheckInFab
