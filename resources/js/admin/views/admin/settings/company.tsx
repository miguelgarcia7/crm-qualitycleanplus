import PageBreadcrumb from '@/components/PageBreadcrumb'
import Icon from '@/components/wrappers/Icon'
import { Head, useForm } from '@inertiajs/react'
import { FormEvent } from 'react'

type Invoicer = {
  name: string
  address: string
  city: string
  state: string
  zip: string
  phone: string
  email: string
}

type Props = { invoicer: Invoicer; missing: string[] }

const fields: { key: keyof Invoicer; label: string; type?: string; span?: string }[] = [
  { key: 'name', label: 'Company name', span: 'sm:col-span-2' },
  { key: 'address', label: 'Street address', span: 'sm:col-span-2' },
  { key: 'city', label: 'City' },
  { key: 'state', label: 'State' },
  { key: 'zip', label: 'ZIP' },
  { key: 'phone', label: 'Phone' },
  { key: 'email', label: 'Billing email', type: 'email', span: 'sm:col-span-2' },
]

const Page = ({ invoicer, missing }: Props) => {
  const { data, setData, patch, processing, errors, isDirty } = useForm<Invoicer>({ ...invoicer })

  const submit = (e: FormEvent) => {
    e.preventDefault()
    patch('/admin/settings/company', { preserveScroll: true })
  }

  return (
    <>
      <Head title="Company details" />
      <PageBreadcrumb title="Company" subtitle="Settings" />

      <div className="grid gap-4 xl:grid-cols-3">
        <div className="xl:col-span-2">
          <div className="card">
            <div className="card-header">
              <h4 className="card-title">Invoice details</h4>
            </div>

            <div className="card-body p-5">
              {missing.length > 0 && (
                <div className="bg-warning/15 text-warning mb-5 flex items-start gap-3 rounded-md px-4 py-3 text-sm">
                  <Icon icon="alert-triangle" className="mt-0.5 size-4 shrink-0" />
                  <span>
                    <strong>
                      {missing.length} {missing.length === 1 ? 'field is' : 'fields are'} blank
                    </strong>{' '}
                    ({missing.join(', ')}). Every invoice generated while they are blank carries them that way permanently.
                  </span>
                </div>
              )}

              <form onSubmit={submit}>
                <div className="grid gap-4 sm:grid-cols-2">
                  {fields.map((field) => (
                    <div key={field.key} className={field.span}>
                      <label htmlFor={field.key} className="form-label">
                        {field.label}
                        {field.key === 'name' && <span className="text-danger">*</span>}
                      </label>
                      <input
                        id={field.key}
                        type={field.type ?? 'text'}
                        className="form-input"
                        value={data[field.key]}
                        onChange={(e) => setData(field.key, e.target.value)}
                      />
                      {errors[field.key] && <p className="text-danger mt-1 text-sm">{errors[field.key]}</p>}
                    </div>
                  ))}
                </div>

                <div className="mt-5 flex justify-end">
                  <button type="submit" className="btn bg-primary hover:bg-primary-hover px-4 py-2 font-semibold text-white" disabled={processing || !isDirty}>
                    {processing ? 'Saving…' : 'Save company details'}
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <div>
          <div className="card h-full">
            <div className="card-header">
              <h4 className="card-title">How this is used</h4>
            </div>
            <div className="card-body p-5">
              <p className="text-default-400 text-sm">
                These details are the &ldquo;From&rdquo; block on every invoice. They are copied onto each invoice when it is generated and never
                change afterwards, so past invoices keep the details that were correct at the time.
              </p>
              <p className="text-default-400 mt-3 text-sm">
                That also means a correction here does <strong className="text-default-600">not</strong> reach invoices already issued. Those would
                need to be voided and reissued.
              </p>

              <div className="border-default-100 mt-4 border-t pt-4">
                <div className="text-default-400 mb-2 text-xs uppercase">Preview</div>
                <div className="text-sm font-medium">{data.name || '—'}</div>
                <div className="text-default-400 text-sm">
                  {data.address || <span className="text-warning">No street address</span>}
                </div>
                <div className="text-default-400 text-sm">
                  {[data.city, data.state, data.zip].filter(Boolean).join(', ') || <span className="text-warning">No city, state or ZIP</span>}
                </div>
                <div className="text-default-400 mt-1 text-sm">{data.phone || <span className="text-warning">No phone</span>}</div>
                <div className="text-default-400 text-sm">{data.email || <span className="text-warning">No billing email</span>}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </>
  )
}

export default Page
