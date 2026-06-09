import AppProvidersWrapper from '@/components/wrappers/AppProvidersWrapper'
import { Head } from '@inertiajs/react'
import { FormEvent, useEffect, useRef, useState } from 'react'

type WorkOrderOption = { id: number; position: string | null }
type OpenEntry = { id: number; position: string | null; same_property: boolean }
type Lookup = { contractor: string; work_orders: WorkOrderOption[]; open_entry: OpenEntry | null }

const TOKEN_KEY = 'qcp_device_token'
const PROP_KEY = 'qcp_device_property'

const Card = ({ children }: { children: React.ReactNode }) => (
  <div className="mx-auto mt-10 w-full max-w-md px-4">
    <div className="card rounded-2xl">
      <div className="card-body p-6">{children}</div>
    </div>
  </div>
)

const Page = () => {
  const [token, setToken] = useState<string | null>(null)
  const [property, setProperty] = useState<string | null>(null)
  const [phase, setPhase] = useState<'activate' | 'phone' | 'action' | 'done'>('activate')

  const [code, setCode] = useState('')
  const [phone, setPhone] = useState('')
  const [lookup, setLookup] = useState<Lookup | null>(null)
  const [workOrderId, setWorkOrderId] = useState('')
  const [selfie, setSelfie] = useState<File | null>(null)
  const [cameraError, setCameraError] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [result, setResult] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  const videoRef = useRef<HTMLVideoElement>(null)
  const streamRef = useRef<MediaStream | null>(null)

  useEffect(() => {
    const t = localStorage.getItem(TOKEN_KEY)
    const p = localStorage.getItem(PROP_KEY)
    if (t) {
      setToken(t)
      setProperty(p)
      setPhase('phone')
    }
  }, [])

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const api = async (path: string, options: { method?: string; body?: BodyInit; auth?: boolean } = {}): Promise<any> => {
    const headers: Record<string, string> = { Accept: 'application/json' }
    if (options.auth && token) headers.Authorization = `Bearer ${token}`
    const res = await fetch(`/device/${path}`, { method: options.method ?? 'POST', headers, body: options.body })
    if (res.status === 401) {
      unpair()
      throw new Error('Device session expired. Re-pair the tablet.')
    }
    const data = await res.json().catch(() => ({}) as Record<string, unknown>)
    if (!res.ok) {
      const errors = data.errors as Record<string, string[]> | undefined
      const firstError = errors ? Object.values(errors)[0]?.[0] : undefined
      throw new Error((data.error as string) ?? firstError ?? 'Something went wrong.')
    }
    return data
  }

  const unpair = () => {
    localStorage.removeItem(TOKEN_KEY)
    localStorage.removeItem(PROP_KEY)
    setToken(null)
    setProperty(null)
    setPhase('activate')
  }

  const activate = async (e: FormEvent) => {
    e.preventDefault()
    setBusy(true)
    setError(null)
    try {
      const body = new FormData()
      body.append('code', code.trim())
      const data = await api('activate', { body })
      localStorage.setItem(TOKEN_KEY, data.token)
      localStorage.setItem(PROP_KEY, data.property?.name ?? '')
      setToken(data.token)
      setProperty(data.property?.name ?? '')
      setCode('')
      setPhase('phone')
    } catch (err) {
      setError((err as Error).message)
    } finally {
      setBusy(false)
    }
  }

  const doLookup = async (e: FormEvent) => {
    e.preventDefault()
    setBusy(true)
    setError(null)
    try {
      const body = new FormData()
      body.append('phone', phone)
      const data: Lookup = await api('lookup', { body, auth: true })
      setLookup(data)
      setWorkOrderId(data.work_orders.length === 1 ? String(data.work_orders[0].id) : '')
      setPhase('action')
    } catch (err) {
      setError((err as Error).message)
    } finally {
      setBusy(false)
    }
  }

  // Camera on the action step.
  useEffect(() => {
    if (phase !== 'action') return
    let cancelled = false
    navigator.mediaDevices
      ?.getUserMedia({ video: { facingMode: 'user' } })
      .then((stream) => {
        if (cancelled) return stream.getTracks().forEach((t) => t.stop())
        streamRef.current = stream
        if (videoRef.current) videoRef.current.srcObject = stream
      })
      .catch(() => setCameraError('Camera access is required.'))
    return () => {
      cancelled = true
      streamRef.current?.getTracks().forEach((t) => t.stop())
      streamRef.current = null
    }
  }, [phase])

  const capture = () => {
    const video = videoRef.current
    if (!video) return
    const canvas = document.createElement('canvas')
    canvas.width = video.videoWidth || 480
    canvas.height = video.videoHeight || 640
    canvas.getContext('2d')?.drawImage(video, 0, 0, canvas.width, canvas.height)
    canvas.toBlob((b) => b && setSelfie(new File([b], 'selfie.jpg', { type: 'image/jpeg' })), 'image/jpeg', 0.85)
  }

  const openHere = lookup?.open_entry && lookup.open_entry.same_property ? lookup.open_entry : null

  const submit = async () => {
    if (!selfie) return
    setBusy(true)
    setError(null)
    try {
      const body = new FormData()
      body.append('phone', phone)
      body.append('selfie', selfie)
      let data
      if (openHere) {
        body.append('time_entry_id', String(openHere.id))
        data = await api('clock-out', { body, auth: true })
        setResult(`Clocked out — ${data.duration_minutes ?? 0} min`)
      } else {
        body.append('work_order_id', workOrderId)
        data = await api('clock-in', { body, auth: true })
        setResult(`Clocked in at ${data.at}`)
      }
      setPhase('done')
    } catch (err) {
      setError((err as Error).message)
    } finally {
      setBusy(false)
    }
  }

  const nextContractor = () => {
    setPhone('')
    setLookup(null)
    setWorkOrderId('')
    setSelfie(null)
    setResult(null)
    setError(null)
    setPhase('phone')
  }

  return (
    <>
      <Head title="Clock In Station" />
      <div className="min-h-screen bg-gray-100">
        <div className="bg-primary py-5 text-center text-white">
          <h1 className="text-lg font-semibold">{property ?? 'Clock-In Station'}</h1>
          {token && <p className="text-xs opacity-75">Tablet paired</p>}
        </div>

        {error && (
          <div className="mx-auto mt-4 max-w-md px-4">
            <div className="bg-danger/10 text-danger rounded-lg p-3 text-sm">{error}</div>
          </div>
        )}

        {phase === 'activate' && (
          <Card>
            <h2 className="mb-1 text-lg font-semibold">Pair this tablet</h2>
            <p className="text-default-500 mb-4 text-sm">Enter the activation code from the back office.</p>
            <form onSubmit={activate} className="space-y-3">
              <input className="form-input w-full text-center text-2xl tracking-widest uppercase" maxLength={6} value={code} onChange={(e) => setCode(e.target.value)} required />
              <button className="btn bg-primary w-full py-2.5 font-semibold text-white" disabled={busy}>
                Pair tablet
              </button>
            </form>
          </Card>
        )}

        {phase === 'phone' && (
          <Card>
            <h2 className="mb-1 text-lg font-semibold">Clock in / out</h2>
            <p className="text-default-500 mb-4 text-sm">Enter your phone number.</p>
            <form onSubmit={doLookup} className="space-y-3">
              <input type="tel" inputMode="tel" className="form-input w-full text-lg" placeholder="(555) 123-4567" value={phone} onChange={(e) => setPhone(e.target.value)} required />
              <button className="btn bg-primary w-full py-2.5 font-semibold text-white" disabled={busy}>
                Continue
              </button>
            </form>
            <button className="text-default-400 mt-4 w-full text-center text-xs" onClick={unpair}>
              Unpair tablet
            </button>
          </Card>
        )}

        {phase === 'action' && lookup && (
          <Card>
            <p className="text-default-500 text-sm">Hi {lookup.contractor},</p>
            <h2 className="mb-3 text-lg font-semibold">{openHere ? 'Clock out' : 'Clock in'}</h2>

            {!openHere && (
              <label className="mb-3 block">
                <span className="form-label">Position</span>
                <select className="form-select w-full" value={workOrderId} onChange={(e) => setWorkOrderId(e.target.value)}>
                  <option value="">Select…</option>
                  {lookup.work_orders.map((wo) => (
                    <option key={wo.id} value={wo.id}>
                      {wo.position}
                    </option>
                  ))}
                </select>
              </label>
            )}

            <div className="mb-4">
              {cameraError ? (
                <p className="text-danger text-sm">{cameraError}</p>
              ) : selfie ? (
                <p className="text-success text-sm">✓ Selfie captured</p>
              ) : (
                <>
                  <video ref={videoRef} autoPlay playsInline muted className="mb-2 w-full rounded-lg bg-black" />
                  <button className="btn btn-light w-full" onClick={capture}>
                    Take selfie
                  </button>
                </>
              )}
            </div>

            <button className="btn bg-primary w-full py-2.5 font-semibold text-white disabled:opacity-50" disabled={busy || !selfie || (!openHere && !workOrderId)} onClick={submit}>
              {openHere ? 'Confirm clock out' : 'Confirm clock in'}
            </button>
            <button className="text-default-400 mt-3 w-full text-center text-sm" onClick={nextContractor}>
              Cancel
            </button>
          </Card>
        )}

        {phase === 'done' && (
          <Card>
            <div className="text-center">
              <div className="text-success mb-2 text-5xl">✓</div>
              <h2 className="text-xl font-semibold">{result}</h2>
              <button className="btn bg-primary mt-6 w-full py-2.5 font-semibold text-white" onClick={nextContractor}>
                Done
              </button>
            </div>
          </Card>
        )}
      </div>
    </>
  )
}

Page.layout = (page: React.ReactNode) => <AppProvidersWrapper>{page}</AppProvidersWrapper>

export default Page
