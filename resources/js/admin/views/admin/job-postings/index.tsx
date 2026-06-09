import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, Link, router, useForm } from '@inertiajs/react'
import { useState } from 'react'

type Posting = {
  id: number
  slug: string
  title: string
  status: string
  status_label: string
  pay_range: string | null
  content: string | null
  hour_start: string | null
  hour_end: string | null
  property_id: number | null
  location_label: string | null
  location: string | null
  applications_count: number
}

type Props = {
  postings: Posting[]
  properties: { id: number; name: string }[]
}

const statusBadge: Record<string, string> = {
  draft: 'badge badge-soft-warning',
  published: 'badge badge-soft-success',
  closed: 'badge badge-soft-secondary',
}

const emptyForm = {
  title: '',
  pay_range: '',
  content: '',
  hour_start: '',
  hour_end: '',
  property_id: '',
  location_label: '',
}

const Page = ({ postings, properties }: Props) => {
  const [editing, setEditing] = useState<Posting | null>(null)
  const { data, setData, post, put, processing, reset, errors, clearErrors } = useForm(emptyForm)

  const startEdit = (p: Posting) => {
    setEditing(p)
    clearErrors()
    setData({
      title: p.title,
      pay_range: p.pay_range ?? '',
      content: p.content ?? '',
      hour_start: p.hour_start ?? '',
      hour_end: p.hour_end ?? '',
      property_id: p.property_id ? String(p.property_id) : '',
      location_label: p.location_label ?? '',
    })
  }

  const cancelEdit = () => {
    setEditing(null)
    clearErrors()
    reset()
  }

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    if (editing) {
      put(`/admin/job-postings/${editing.slug}`, { preserveScroll: true, onSuccess: cancelEdit })
    } else {
      post('/admin/job-postings', { preserveScroll: true, onSuccess: () => reset() })
    }
  }

  const publish = (p: Posting) => router.post(`/admin/job-postings/${p.slug}/publish`, {}, { preserveScroll: true })
  const close = (p: Posting) => router.post(`/admin/job-postings/${p.slug}/close`, {}, { preserveScroll: true })
  const destroy = (p: Posting) => {
    if (confirm(`Delete "${p.title}"? This only works while it has no applications.`)) {
      router.delete(`/admin/job-postings/${p.slug}`, { preserveScroll: true })
    }
  }

  return (
    <>
      <Head title="Job Postings" />
      <PageBreadcrumb title="Job Postings" subtitle="Recruiting" />

      <div className="grid gap-4 lg:grid-cols-3">
        <div className="lg:col-span-2">
          <div className="card rounded-2xl">
            <div className="card-body p-0">
              {postings.length === 0 ? (
                <p className="text-muted p-6">No postings yet. Create one and publish it to the public job board.</p>
              ) : (
                <table className="w-full text-sm">
                  <thead className="border-default-200 text-muted border-b text-left">
                    <tr>
                      <th className="p-3">Title</th>
                      <th className="p-3">Location</th>
                      <th className="p-3">Pay</th>
                      <th className="p-3">Status</th>
                      <th className="p-3">Apps</th>
                      <th className="p-3 text-right">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {postings.map((p) => (
                      <tr key={p.id} className="border-default-100 border-b">
                        <td className="p-3 font-medium">{p.title}</td>
                        <td className="p-3">{p.location ?? '—'}</td>
                        <td className="p-3">{p.pay_range ?? '—'}</td>
                        <td className="p-3">
                          <span className={statusBadge[p.status] ?? 'badge'}>{p.status_label}</span>
                        </td>
                        <td className="p-3">{p.applications_count}</td>
                        <td className="space-x-2 p-3 text-right whitespace-nowrap">
                          {p.status !== 'published' ? (
                            <button className="btn btn-sm btn-light text-success" onClick={() => publish(p)}>
                              Publish
                            </button>
                          ) : (
                            <button className="btn btn-sm btn-light" onClick={() => close(p)}>
                              Close
                            </button>
                          )}
                          <button className="btn btn-sm btn-light" onClick={() => startEdit(p)}>
                            Edit
                          </button>
                          {p.applications_count === 0 && (
                            <button className="btn btn-sm btn-light text-danger" onClick={() => destroy(p)}>
                              Delete
                            </button>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </div>
          </div>
          <p className="text-default-400 mt-3 text-xs">
            Published postings appear on the public <Link href="/job-openings" className="text-primary">job board</Link>; applicants land in the
            Applicants queue.
          </p>
        </div>

        <div>
          <div className="card rounded-2xl">
            <div className="card-body p-5">
              <h4 className="card-title mb-3">{editing ? `Edit "${editing.title}"` : 'New posting'}</h4>
              <form onSubmit={submit} className="space-y-3">
                <div>
                  <label className="form-label">Title</label>
                  <input className="form-input w-full" value={data.title} onChange={(e) => setData('title', e.target.value)} required />
                  {errors.title && <p className="text-danger text-sm">{errors.title}</p>}
                </div>
                <div>
                  <label className="form-label">Property (optional)</label>
                  <select className="form-select w-full" value={data.property_id} onChange={(e) => setData('property_id', e.target.value)}>
                    <option value="">No linked property</option>
                    {properties.map((p) => (
                      <option key={p.id} value={p.id}>
                        {p.name}
                      </option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="form-label">Location label (when no property)</label>
                  <input
                    className="form-input w-full"
                    value={data.location_label}
                    onChange={(e) => setData('location_label', e.target.value)}
                    placeholder="Dallas, TX"
                  />
                </div>
                <div>
                  <label className="form-label">Pay range</label>
                  <input
                    className="form-input w-full"
                    value={data.pay_range}
                    onChange={(e) => setData('pay_range', e.target.value)}
                    placeholder="$16 - $18 / hr"
                  />
                </div>
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="form-label">Shift start</label>
                    <input type="time" className="form-input w-full" value={data.hour_start} onChange={(e) => setData('hour_start', e.target.value)} />
                  </div>
                  <div>
                    <label className="form-label">Shift end</label>
                    <input type="time" className="form-input w-full" value={data.hour_end} onChange={(e) => setData('hour_end', e.target.value)} />
                  </div>
                </div>
                <div>
                  <label className="form-label">Description</label>
                  <textarea className="form-input w-full" rows={4} value={data.content} onChange={(e) => setData('content', e.target.value)} />
                </div>
                <div className="flex gap-2">
                  <button className="btn bg-primary flex-1 py-2 font-semibold text-white" disabled={processing}>
                    {editing ? 'Save changes' : 'Create draft'}
                  </button>
                  {editing && (
                    <button type="button" className="btn btn-light" onClick={cancelEdit}>
                      Cancel
                    </button>
                  )}
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </>
  )
}

export default Page
