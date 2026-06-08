import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, Link, router } from '@inertiajs/react'

type Batch = {
  id: number
  property: string | null
  period: string | null
  file_name: string
  status: string
  status_label: string
  uploaded_by: string | null
  created_at: string | null
  invoice: { id: number; number: string } | null
  stats: Record<string, number> | null
}

type Props = {
  batches: Batch[]
  can: { upload: boolean; rollback: boolean }
}

const badgeTone = (status: string) =>
  status === 'applied'
    ? 'bg-success/10 text-success'
    : status === 'rolled_back'
      ? 'bg-gray-200 text-gray-600'
      : 'bg-warning/10 text-warning'

const Page = ({ batches, can }: Props) => {
  const rollback = (id: number, reimport: boolean) => {
    const msg = reimport
      ? 'Void this import and start a corrected re-import for the same week?'
      : 'Void and roll back this import? The invoice will be voided and time entries removed.'
    if (confirm(msg)) {
      router.post(`/admin/imports/${id}/rollback`, { reimport }, { preserveScroll: true })
    }
  }

  return (
    <>
      <Head title="Hour Imports" />
      <PageBreadcrumb title="Hour Imports" subtitle="Billing" />

      <div className="mb-4 flex justify-end">
        <Link href="/admin/imports/create" className="btn bg-primary px-5 py-2 font-semibold text-white">
          New Import
        </Link>
      </div>

      <div className="card rounded-2xl">
        <div className="card-body p-0">
          {batches.length === 0 ? (
            <p className="text-muted p-6">No imports yet.</p>
          ) : (
            <table className="w-full text-sm">
              <thead className="border-default-200 text-muted border-b text-left">
                <tr>
                  <th className="p-3">#</th>
                  <th className="p-3">Property / Week</th>
                  <th className="p-3">File</th>
                  <th className="p-3">Status</th>
                  <th className="p-3">Uploaded</th>
                  <th className="p-3">Invoice</th>
                  <th className="p-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                {batches.map((b) => (
                  <tr key={b.id} className="border-default-100 border-b">
                    <td className="p-3">{b.id}</td>
                    <td className="p-3">
                      <div className="font-medium">{b.property}</div>
                      <div className="text-muted text-xs">{b.period}</div>
                    </td>
                    <td className="p-3">{b.file_name}</td>
                    <td className="p-3">
                      <span className={`rounded px-2 py-0.5 text-xs font-medium ${badgeTone(b.status)}`}>{b.status_label}</span>
                    </td>
                    <td className="p-3">
                      <div>{b.uploaded_by}</div>
                      <div className="text-muted text-xs">{b.created_at}</div>
                    </td>
                    <td className="p-3">
                      {b.invoice ? (
                        <Link href={`/admin/invoices/${b.invoice.id}`} className="text-primary">
                          {b.invoice.number}
                        </Link>
                      ) : (
                        <span className="text-muted">—</span>
                      )}
                    </td>
                    <td className="space-x-2 p-3 text-right">
                      <Link href={`/admin/imports/${b.id}`} className="btn btn-sm btn-light">
                        {b.status === 'preview' ? 'Continue' : 'View'}
                      </Link>
                      {b.status === 'applied' && can.rollback && (
                        <>
                          <button className="btn btn-sm btn-light" onClick={() => rollback(b.id, true)}>
                            Re-import
                          </button>
                          <button className="btn btn-sm btn-light text-danger" onClick={() => rollback(b.id, false)}>
                            Void
                          </button>
                        </>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>
    </>
  )
}

export default Page
