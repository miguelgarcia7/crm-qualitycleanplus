import PageBreadcrumb from '@/components/PageBreadcrumb'
import { Head, Link } from '@inertiajs/react'
import { QRCodeCanvas } from 'qrcode.react'

type Props = {
  property: { id: number; name: string; has_location: boolean }
  url: string
}

const Page = ({ property, url }: Props) => (
  <>
    <Head title={`Clock-In QR · ${property.name}`} />
    <PageBreadcrumb title="Clock-In QR" subtitle="Property Bible" />

    <div className="mx-auto max-w-md">
      {!property.has_location && (
        <div className="bg-warning/10 text-warning mb-4 rounded-lg p-3 text-sm">
          This property has no latitude/longitude set yet. Clock-in is geofenced — add coordinates on the
          property profile before printing this code.
        </div>
      )}

      <div className="card rounded-2xl print:shadow-none">
        <div className="card-body p-8 text-center">
          <h2 className="mb-1 text-xl font-bold">{property.name}</h2>
          <p className="text-default-500 mb-6 text-sm">Scan to clock in / out</p>

          <div className="flex justify-center">
            <QRCodeCanvas value={url} size={260} includeMargin level="M" />
          </div>

          <p className="text-default-400 mt-6 text-xs break-all">{url}</p>
        </div>
      </div>

      <div className="mt-4 flex gap-2 print:hidden">
        <button onClick={() => window.print()} className="btn bg-primary px-5 py-2 font-semibold text-white">
          Print
        </button>
        <Link href={`/admin/properties/${property.id}`} className="btn btn-light px-5 py-2">
          Back to property
        </Link>
      </div>
    </div>
  </>
)

export default Page
