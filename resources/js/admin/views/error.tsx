import { currentYear, META_DATA } from '@/config/constants'
import authCard from '@/images/admin/auth-card-bg.svg'
import logoDark from '@/images/admin/logo-black.png'
import logo from '@/images/admin/logo.png'
import maintenanceSvg from '@/images/admin/maintenance.svg'
import BaseLayout from '@/layouts/BaseLayout'
import { Head, Link, router } from '@inertiajs/react'

/**
 * Error page for the back office and QC Minute (rendered by App\Support\AppErrorPage),
 * following the reference library's error cards (design-reference/error/*).
 */
type Props = {
  status: number
  /** A 403's own reason, when the app gave one. */
  message: string | null
  /** This surface's home: the back-office dashboard or QC Minute. */
  home: string
  signed_in: boolean
}

const COPY: Record<number, { title: string; message: string }> = {
  403: { title: 'Permission Required', message: 'You don’t have permission to view this page.' },
  404: { title: 'Nothing Here', message: 'We couldn’t find the page you were looking for. It might have been moved or deleted.' },
  429: { title: 'Too Many Requests', message: 'You’ve made a lot of requests in a short time. Please wait a minute and try again.' },
  500: { title: 'Server Error', message: 'Something went wrong on our end. Please try again in a moment.' },
  503: { title: 'Under Maintenance', message: 'We’re performing scheduled maintenance. Please check back soon.' },
}

const Page = ({ status, message, home, signed_in }: Props) => {
  const copy = COPY[status] ?? COPY[500]
  const maintenance = status === 503

  return (
    <>
      <Head title={copy.title} />
      <div className="flex min-h-screen items-center">
        <div className="container">
          <div className="flex justify-center p-12.5 lg:p-0">
            <div className={maintenance ? 'w-full lg:w-1/2' : 'w-full sm:w-2/3 md:w-1/2 2xl:w-4/10'}>
              <div className="absolute end-0 top-0">
                <img src={authCard} alt="" />
              </div>
              <div className="absolute start-0 bottom-0 rotate-180">
                <img src={authCard} alt="" />
              </div>

              <div className="card rounded-2xl">
                <div className="card-body p-7.5">
                  <div className="mb-base flex flex-col items-center justify-center text-center">
                    <Link href={home} className="auth-logo">
                      <img src={logoDark} alt="Minute" className="flex dark:hidden" />
                      <img src={logo} alt="Minute" className="hidden dark:flex" />
                    </Link>
                  </div>

                  <div className="p-6 text-center">
                    {maintenance ? (
                      <img src={maintenanceSvg} alt="" className="mx-auto md:size-64" />
                    ) : (
                      <div className="from-primary to-danger my-4 bg-linear-to-r bg-clip-text text-7xl font-bold text-transparent">{status}</div>
                    )}
                    <h3 className="mb-2 text-xl font-bold uppercase">{copy.title}</h3>
                    <p className="text-default-400 mx-auto">{message ?? copy.message}</p>

                    <div className="mt-8 flex flex-wrap items-center justify-center gap-1.5">
                      {status === 500 || maintenance ? (
                        <button type="button" className="btn bg-primary hover:bg-primary-hover text-white" onClick={() => window.location.reload()}>
                          Try Again
                        </button>
                      ) : (
                        <Link href={home} className="btn bg-primary hover:bg-primary-hover text-white">
                          Back to Home
                        </Link>
                      )}
                      {!maintenance && (
                        <button
                          type="button"
                          className="btn border-secondary text-secondary hover:bg-secondary border hover:text-white"
                          onClick={() => window.history.back()}
                        >
                          Go Back
                        </button>
                      )}
                    </div>

                    {status === 403 && signed_in && (
                      <button type="button" className="text-default-500 mt-5 text-sm underline" onClick={() => router.post('/logout')}>
                        Sign out and use a different account
                      </button>
                    )}
                  </div>
                </div>
              </div>

              <p className="text-default-400 mt-7.5 text-center">
                &copy; {currentYear} {META_DATA.name} - by <span>{META_DATA.author}</span>
              </p>
            </div>
          </div>
        </div>
      </div>
    </>
  )
}

Page.layout = (page: React.ReactNode) => <BaseLayout>{page}</BaseLayout>

export default Page
