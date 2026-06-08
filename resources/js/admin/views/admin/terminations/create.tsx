import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, Link, useForm } from '@inertiajs/react'
import { FormEvent } from 'react'

type Person = { id: number; name: string; status: string }
type Option = { value: string; label: string }
type Props = { people: Person[]; types: Option[]; reasons: Option[] }

const Page = ({ people, types, reasons }: Props) => {
  const { data, setData, post, processing, errors } = useForm<{
    person_id: number | string; effective_date: string; termination_type: string
    reason_category: string; notes: string; rehireable: boolean
  }>({
    person_id: '', effective_date: new Date().toISOString().slice(0, 10),
    termination_type: types[0]?.value ?? '', reason_category: reasons[0]?.value ?? '',
    notes: '', rehireable: true,
  })

  const submit = (e: FormEvent) => {
    e.preventDefault()
    post('/admin/terminations', { preserveScroll: true })
  }

  return (
    <>
      <Head title="New Termination" />
      <PageBreadcrumb title="New Termination" subtitle="People" />

      <div className="card mx-auto max-w-2xl rounded-2xl">
        <div className="card-header p-6"><h4 className="card-title">Initiate Termination</h4></div>
        <div className="card-body p-6">
          <form onSubmit={submit} className="space-y-4">
            <div>
              <label className="form-label">Person</label>
              <select className="form-select" value={data.person_id} onChange={(e) => setData('person_id', e.target.value)} required>
                <option value="">Select…</option>
                {people.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
              </select>
              {errors.person_id && <p className="text-danger mt-1 text-sm">{errors.person_id}</p>}
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="form-label">Effective date</label>
                <input type="date" className="form-input" value={data.effective_date} onChange={(e) => setData('effective_date', e.target.value)} required />
                {errors.effective_date && <p className="text-danger mt-1 text-sm">{errors.effective_date}</p>}
              </div>
              <div>
                <label className="form-label">Type</label>
                <select className="form-select" value={data.termination_type} onChange={(e) => setData('termination_type', e.target.value)}>
                  {types.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
                </select>
              </div>
            </div>

            <div>
              <label className="form-label">Reason</label>
              <select className="form-select" value={data.reason_category} onChange={(e) => setData('reason_category', e.target.value)}>
                {reasons.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
              </select>
            </div>

            <div>
              <label className="form-label">Notes</label>
              <textarea className="form-input" rows={3} value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
            </div>

            <label className="flex items-center gap-2 text-sm">
              <input type="checkbox" checked={data.rehireable} onChange={(e) => setData('rehireable', e.target.checked)} />
              Eligible for rehire
            </label>

            <div className="flex justify-end gap-2 pt-2">
              <Link href="/admin/terminations" className="btn btn-light px-4 py-2">Cancel</Link>
              <button type="submit" className="btn bg-primary px-4 py-2 font-semibold text-white" disabled={processing}>Initiate Termination</button>
            </div>
          </form>
        </div>
      </div>
    </>
  )
}

export default Page
