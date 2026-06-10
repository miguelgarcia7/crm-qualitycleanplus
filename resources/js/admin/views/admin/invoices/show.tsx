import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, Link, router, useForm } from '@inertiajs/react'
import { FormEvent, useState } from 'react'

type Item = { contractor_name: string; position_name: string; regular_minutes: number; overtime_minutes: number; total_bill: number }
type Invoice = {
  id: number
  invoice_number: string
  issue_date: string
  due_date: string
  status: string
  status_label: string
  property_snapshot: Record<string, string | null>
  invoicer_snapshot: Record<string, string | null>
  work_subtotal: number
  subtotal: number
  tax_amount: number
  total: number
  notification_recipient: string | null
  items: Item[]
}

type Props = { invoice: Invoice; can: { send: boolean } }

const money = (cents: number) => `$${(cents / 100).toFixed(2)}`
const hrs = (m: number) => (m / 60).toFixed(2)

const Page = ({ invoice, can }: Props) => {
  const [sending, setSending] = useState(false)

  return (
    <>
      <Head title={invoice.invoice_number} />
      <PageBreadcrumb title={invoice.invoice_number} subtitle="Invoice" />

      <div className="card">
        <div className="card-header">
          <div className="flex items-center gap-3">
            <h4 className="card-title">{invoice.invoice_number}</h4>
            <span className="badge badge-label bg-secondary/15 text-secondary">{invoice.status_label}</span>
          </div>
          <div className="flex items-center gap-2">
            <a href={`/admin/invoices/${invoice.id}/pdf`} className="btn btn-light px-4 py-1.5">Download PDF</a>
            {can.send && (
              <button className="btn bg-primary hover:bg-primary-hover px-4 py-1.5 font-semibold text-white" onClick={() => setSending(true)}>Send to Property</button>
            )}
            <Link href="/admin/invoices" className="text-default-500 ms-2 text-sm hover:underline">All</Link>
          </div>
        </div>

        <div className="card-body p-6">
          <div className="mb-6 grid grid-cols-2 gap-6">
            <div>
              <div className="text-default-400 text-xs uppercase">From</div>
              <div className="font-medium">{invoice.invoicer_snapshot.name}</div>
            </div>
            <div>
              <div className="text-default-400 text-xs uppercase">Bill To</div>
              <div className="font-medium">{invoice.property_snapshot.name}</div>
              <div className="text-default-400 text-sm">
                {invoice.property_snapshot.city} {invoice.property_snapshot.state} {invoice.property_snapshot.zip}
              </div>
            </div>
            <div><span className="text-default-400">Issued:</span> {invoice.issue_date}</div>
            <div><span className="text-default-400">Due:</span> {invoice.due_date}</div>
          </div>

          <div className="table-wrapper">
            <table className="table table-hover">
              <thead className="thead-sm">
                <tr className="bg-light/25 text-2xs uppercase">
                  <th>Contractor</th>
                  <th>Position</th>
                  <th className="text-end">Reg Hrs</th>
                  <th className="text-end">OT Hrs</th>
                  <th className="text-end">Amount</th>
                </tr>
              </thead>
              <tbody>
                {invoice.items.map((it, idx) => (
                  <tr key={idx}>
                    <td className="font-medium">{it.contractor_name}</td>
                    <td>{it.position_name}</td>
                    <td className="text-end">{hrs(it.regular_minutes)}</td>
                    <td className="text-end">{hrs(it.overtime_minutes)}</td>
                    <td className="text-end">{money(it.total_bill)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div className="mt-4 flex justify-end">
            <table className="w-64 text-sm">
              <tbody>
                <tr><td className="py-1">Subtotal</td><td className="py-1 text-end">{money(invoice.subtotal)}</td></tr>
                <tr><td className="py-1">Tax</td><td className="py-1 text-end">{money(invoice.tax_amount)}</td></tr>
                <tr className="border-default-300 border-t font-bold"><td className="py-1">Total</td><td className="py-1 text-end">{money(invoice.total)}</td></tr>
              </tbody>
            </table>
          </div>

          {invoice.notification_recipient && (
            <p className="text-success mt-4 text-sm">Sent to {invoice.notification_recipient}.</p>
          )}
        </div>
      </div>

      {sending && <SendModal invoiceId={invoice.id} defaultEmail={invoice.property_snapshot.billing_email ?? ''} onClose={() => setSending(false)} />}
    </>
  )
}

const SendModal = ({ invoiceId, defaultEmail, onClose }: { invoiceId: number; defaultEmail: string; onClose: () => void }) => {
  const { data, setData, post, processing, errors } = useForm({ recipient: defaultEmail })

  const submit = (e: FormEvent) => {
    e.preventDefault()
    post(`/admin/invoices/${invoiceId}/send`, { onSuccess: onClose })
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
      <div className="card w-full max-w-md" onClick={(e) => e.stopPropagation()}>
        <div className="card-header"><h4 className="card-title">Send Invoice to Property</h4></div>
        <div className="card-body p-5">
          <form onSubmit={submit} className="space-y-4">
            <div>
              <label className="form-label">Recipient email</label>
              <input type="email" className="form-input" value={data.recipient} onChange={(e) => setData('recipient', e.target.value)} required />
              {errors.recipient && <p className="text-danger mt-1 text-sm">{errors.recipient}</p>}
            </div>
            <div className="flex justify-end gap-2">
              <button type="button" className="btn btn-light px-4 py-2" onClick={onClose}>Cancel</button>
              <button type="submit" className="btn bg-primary hover:bg-primary-hover px-4 py-2 font-semibold text-white" disabled={processing}>Send</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  )
}

export default Page
