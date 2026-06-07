import PageBreadcrumb from '@/components/PageBreadcrumb'
import { useEffect, useState } from 'react'

const Preloader = () => {
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const timer = setTimeout(() => {
      setLoading(false)
    }, 1000)

    return () => clearTimeout(timer)
  }, [])

  return (
    <>
      {loading ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-white">
          <div className="size-8 animate-spin rounded-full border-3 border-primary border-t-transparent"></div>
        </div>
      ) : (
        <>
          <PageBreadcrumb title="Preloader" subtitle="Layouts" />
          <div className="card">
            <div className="card-body text-center">
              <h4 className="text-lg">Your custom content here</h4>
            </div>
          </div>
        </>
      )}
    </>
  )
}

export default Preloader
