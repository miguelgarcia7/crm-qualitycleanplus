import { confirmAction } from '@/components/ConfirmHost'
import PageBreadcrumb from '@/components/PageBreadcrumb'
import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { Head, router, useForm } from '@inertiajs/react'

type Holiday = {
  id: number
  name: string
  type: string
  type_label: string
  this_year: string
  properties_count: number
}

type Props = {
  holidays: Holiday[]
  can: { edit: boolean }
}

const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']

const Page = ({ holidays, can }: Props) => {
  const { data, setData, post, processing, reset, errors } = useForm({ name: '', month: '', day: '' })

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    post('/admin/holidays', { preserveScroll: true, onSuccess: () => reset() })
  }

  const destroy = (h: Holiday) => {
    confirmAction({
      title: 'Delete holiday',
      message: (
        <>
          Delete <strong>{h.name}</strong>? It is removed from every property, and open weeks are recalculated — hours currently paid at the holiday rate revert to regular.
        </>
      ),
      onConfirm: () => router.delete(`/admin/holidays/${h.id}`, { preserveScroll: true }),
    })
  }

  return (
    <>
      <Head title="Holidays" />
      <PageBreadcrumb title="Holidays" subtitle="Property Bible" />

      <div className={cn('gap-base grid', can.edit && 'lg:grid-cols-3')}>
        <div className={cn(can.edit && 'lg:col-span-2')}>
          <div className="card">
            <div className="table-wrapper">
              <table className="table table-hover">
                <thead className="thead-sm">
                  <tr className="bg-light/25 text-xs uppercase">
                    <th>Holiday</th>
                    <th>Type</th>
                    <th>This Year</th>
                    <th>Properties observing</th>
                    {can.edit && <th></th>}
                  </tr>
                </thead>
                <tbody>
                  {holidays.map((h) => (
                    <tr key={h.id}>
                      <td className="font-medium">{h.name}</td>
                      <td>
                        <span
                          className={cn('badge badge-label', h.type === 'legal' ? 'bg-info/15 text-info' : 'bg-secondary/15 text-secondary')}
                        >
                          {h.type_label}
                        </span>
                      </td>
                      <td>{h.this_year}</td>
                      <td>
                        <span className={h.properties_count > 0 ? 'text-primary font-semibold' : ''}>{h.properties_count}</span>
                      </td>
                      {can.edit && (
                        <td className="text-end">
                          {h.type === 'custom' ? (
                            <button
                              className="btn btn-icon border-default-300 hover:border-default-400 border"
                              onClick={() => destroy(h)}
                              title="Delete holiday"
                            >
                              <Icon icon="trash" className="text-base" />
                            </button>
                          ) : (
                            <span className="badge badge-label bg-light text-default-500">Read-only</span>
                          )}
                        </td>
                      )}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
          <p className="text-default-400 mt-3 text-xs">
            Legal holidays follow their calendar rule every year (e.g. Thanksgiving = 4th Thursday of November) and can&apos;t be deleted.
            Which holidays a property observes is chosen on each property&apos;s Holidays tab. Work on an observed holiday pays and bills at
            the holiday rate and never counts as overtime.
          </p>
        </div>

        {can.edit && (
          <div>
            <div className="card">
              <div className="card-header">
                <h4 className="card-title">New custom holiday</h4>
              </div>
              <div className="card-body">
                <form onSubmit={submit} className="space-y-3">
                  <div>
                    <label className="form-label">Name</label>
                    <input className="form-input w-full" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                    {errors.name && <p className="text-danger text-sm">{errors.name}</p>}
                  </div>
                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <label className="form-label">Month</label>
                      <select className="form-select w-full" value={data.month} onChange={(e) => setData('month', e.target.value)} required>
                        <option value="">Select…</option>
                        {MONTHS.map((m, i) => (
                          <option key={m} value={i + 1}>
                            {m}
                          </option>
                        ))}
                      </select>
                      {errors.month && <p className="text-danger text-sm">{errors.month}</p>}
                    </div>
                    <div>
                      <label className="form-label">Day</label>
                      <input
                        type="number"
                        min={1}
                        max={31}
                        className="form-input w-full"
                        value={data.day}
                        onChange={(e) => setData('day', e.target.value)}
                        required
                      />
                      {errors.day && <p className="text-danger text-sm">{errors.day}</p>}
                    </div>
                  </div>
                  <p className="text-default-400 text-xs">Repeats on this date every year.</p>
                  <button className="btn bg-primary hover:bg-primary-hover w-full py-2 font-semibold text-white" disabled={processing}>
                    Create holiday
                  </button>
                </form>
              </div>
            </div>
          </div>
        )}
      </div>
    </>
  )
}

export default Page
