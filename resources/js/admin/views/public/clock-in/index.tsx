import AppProvidersWrapper from '@/components/wrappers/AppProvidersWrapper'
import { Head, router, usePage } from '@inertiajs/react'
import { FormEvent, useEffect, useRef, useState } from 'react'

type Property = { id: number; name: string; city: string | null; state: string | null }
type WorkOrderOption = { id: number; position: string | null }
type OpenEntry = { id: number; property: string | null; position: string | null; since: string | null; same_property: boolean }
type Lookup = { phone: string; error?: string; contractor?: string; work_orders?: WorkOrderOption[]; open_entry?: OpenEntry | null }
type Result = { action: 'in' | 'out'; position?: string | null; duration?: string; at: string | null }

type Props = { property: Property; lookup?: Lookup; result?: Result }

type Geo = { lat: number; lng: number; accuracy: number | null }

const Card = ({ children }: { children: React.ReactNode }) => (
  <div className="mx-auto mt-8 w-full max-w-md px-4">
    <div className="card rounded-2xl">
      <div className="card-body p-6">{children}</div>
    </div>
  </div>
)

const Page = ({ property, lookup, result }: Props) => {
  const errors = (usePage().props as { errors?: Record<string, string> }).errors ?? {}

  const [phone, setPhone] = useState(lookup?.phone ?? '')
  const [workOrderId, setWorkOrderId] = useState<string>('')
  const [geo, setGeo] = useState<Geo | null>(null)
  const [geoError, setGeoError] = useState<string | null>(null)
  const [selfie, setSelfie] = useState<File | null>(null)
  const [cameraError, setCameraError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  const videoRef = useRef<HTMLVideoElement>(null)
  const streamRef = useRef<MediaStream | null>(null)

  const matched = lookup && !lookup.error
  const openHere = matched && lookup?.open_entry && lookup.open_entry.same_property ? lookup.open_entry : null
  const openElsewhere = matched && lookup?.open_entry && !lookup.open_entry.same_property ? lookup.open_entry : null
  const canPickWo = matched && !openHere && (lookup?.work_orders?.length ?? 0) > 0
  const inCapture = !result && (openHere || canPickWo)

  useEffect(() => {
    if (!workOrderId && lookup?.work_orders?.length === 1) {
      setWorkOrderId(String(lookup.work_orders[0].id))
    }
  }, [lookup, workOrderId])

  // Start the camera once we reach the capture step; stop it on unmount.
  useEffect(() => {
    if (!inCapture) return
    let cancelled = false
    navigator.mediaDevices
      ?.getUserMedia({ video: { facingMode: 'user' } })
      .then((stream) => {
        if (cancelled) {
          stream.getTracks().forEach((t) => t.stop())
          return
        }
        streamRef.current = stream
        if (videoRef.current) videoRef.current.srcObject = stream
      })
      .catch(() => setCameraError('Camera access is required for clock-in verification. Please grant permission and reload.'))
    return () => {
      cancelled = true
      streamRef.current?.getTracks().forEach((t) => t.stop())
      streamRef.current = null
    }
  }, [inCapture])

  const requestGps = () => {
    setGeoError(null)
    if (!navigator.geolocation) {
      setGeoError('Location is not available on this device.')
      return
    }
    navigator.geolocation.getCurrentPosition(
      (pos) => setGeo({ lat: pos.coords.latitude, lng: pos.coords.longitude, accuracy: Math.round(pos.coords.accuracy) }),
      () => setGeoError('Could not get your location. Please enable location access and try again.'),
      { enableHighAccuracy: true, timeout: 10000 },
    )
  }

  const captureSelfie = () => {
    const video = videoRef.current
    if (!video) return
    const canvas = document.createElement('canvas')
    canvas.width = video.videoWidth || 480
    canvas.height = video.videoHeight || 640
    canvas.getContext('2d')?.drawImage(video, 0, 0, canvas.width, canvas.height)
    canvas.toBlob((blob) => {
      if (blob) setSelfie(new File([blob], 'selfie.jpg', { type: 'image/jpeg' }))
    }, 'image/jpeg', 0.85)
  }

  const lookupPhone = (e: FormEvent) => {
    e.preventDefault()
    router.post(`/clock-in/${property.id}/lookup`, { phone }, { preserveScroll: true })
  }

  const submit = () => {
    if (!geo || !selfie) return
    setSubmitting(true)
    const data = new FormData()
    data.append('phone', phone)
    data.append('lat', String(geo.lat))
    data.append('lng', String(geo.lng))
    if (geo.accuracy !== null) data.append('accuracy', String(geo.accuracy))
    data.append('selfie', selfie)

    if (openHere) {
      data.append('time_entry_id', String(openHere.id))
      router.post(`/clock-in/${property.id}/out`, data, { forceFormData: true, onFinish: () => setSubmitting(false) })
    } else {
      data.append('work_order_id', workOrderId)
      router.post(`/clock-in/${property.id}/in`, data, { forceFormData: true, onFinish: () => setSubmitting(false) })
    }
  }

  return (
    <>
      <Head title={`Clock In · ${property.name}`} />
      <div className="min-h-screen bg-gray-50">
        <div className="bg-primary py-5 text-center text-white">
          <h1 className="text-lg font-semibold">{property.name}</h1>
          <p className="text-sm opacity-80">
            {[property.city, property.state].filter(Boolean).join(', ')}
          </p>
        </div>

        {/* Confirmation */}
        {result && (
          <Card>
            <div className="text-center">
              <div className="text-success mb-2 text-5xl">✓</div>
              <h2 className="text-xl font-semibold">
                {result.action === 'in' ? 'Clocked in' : 'Clocked out'}
              </h2>
              <p className="text-default-500 mt-2">
                {result.action === 'in'
                  ? `${result.position ?? 'Shift'} · started at ${result.at}`
                  : `${result.at} · worked ${result.duration}`}
              </p>
              <a href={`/clock-in/${property.id}`} className="btn btn-light mt-6 w-full">
                Done
              </a>
            </div>
          </Card>
        )}

        {/* Step 1 — phone */}
        {!result && !matched && (
          <Card>
            <h2 className="mb-1 text-lg font-semibold">Clock in / out</h2>
            <p className="text-default-500 mb-4 text-sm">Enter your phone number to begin.</p>
            <form onSubmit={lookupPhone} className="space-y-3">
              <input
                type="tel"
                inputMode="tel"
                className="form-input w-full text-lg"
                placeholder="(555) 123-4567"
                value={phone}
                onChange={(e) => setPhone(e.target.value)}
                required
              />
              {(lookup?.error || errors.phone) && <p className="text-danger text-sm">{lookup?.error ?? errors.phone}</p>}
              <button type="submit" className="btn bg-primary w-full py-2.5 font-semibold text-white">
                Continue
              </button>
            </form>
          </Card>
        )}

        {/* Step 2 — capture (clock in or out) */}
        {inCapture && (
          <Card>
            <p className="text-default-500 text-sm">Hi {lookup?.contractor},</p>
            <h2 className="mb-3 text-lg font-semibold">{openHere ? 'Clock out' : 'Clock in'}</h2>

            {openElsewhere && (
              <p className="text-warning mb-3 text-sm">
                Note: you have an open clock-in at {openElsewhere.property}. Clock out there first.
              </p>
            )}

            {openHere ? (
              <p className="mb-3 text-sm">
                {openHere.position} — clocked in since{' '}
                {openHere.since ? new Date(openHere.since).toLocaleTimeString() : 'earlier'}.
              </p>
            ) : (
              <label className="mb-3 block">
                <span className="form-label">Position</span>
                <select className="form-select w-full" value={workOrderId} onChange={(e) => setWorkOrderId(e.target.value)}>
                  <option value="">Select…</option>
                  {lookup?.work_orders?.map((wo) => (
                    <option key={wo.id} value={wo.id}>
                      {wo.position}
                    </option>
                  ))}
                </select>
              </label>
            )}

            {/* GPS */}
            <div className="mb-3">
              {geo ? (
                <p className="text-success text-sm">✓ Location captured (±{geo.accuracy ?? '?'}m)</p>
              ) : (
                <button type="button" className="btn btn-light w-full" onClick={requestGps}>
                  Share my location
                </button>
              )}
              {(geoError || errors.gps) && <p className="text-danger mt-1 text-sm">{geoError ?? errors.gps}</p>}
            </div>

            {/* Selfie */}
            <div className="mb-4">
              {cameraError ? (
                <p className="text-danger text-sm">{cameraError}</p>
              ) : selfie ? (
                <p className="text-success text-sm">✓ Selfie captured</p>
              ) : (
                <>
                  <video ref={videoRef} autoPlay playsInline muted className="mb-2 w-full rounded-lg bg-black" />
                  <button type="button" className="btn btn-light w-full" onClick={captureSelfie}>
                    Take selfie
                  </button>
                </>
              )}
            </div>

            {errors.work_order_id && <p className="text-danger mb-2 text-sm">{errors.work_order_id}</p>}
            {errors.time_entry_id && <p className="text-danger mb-2 text-sm">{errors.time_entry_id}</p>}

            <button
              type="button"
              className="btn bg-primary w-full py-2.5 font-semibold text-white disabled:opacity-50"
              disabled={submitting || !geo || !selfie || (!openHere && !workOrderId)}
              onClick={submit}
            >
              {openHere ? 'Confirm clock out' : 'Confirm clock in'}
            </button>
          </Card>
        )}
      </div>
    </>
  )
}

Page.layout = (page: React.ReactNode) => <AppProvidersWrapper>{page}</AppProvidersWrapper>

export default Page
