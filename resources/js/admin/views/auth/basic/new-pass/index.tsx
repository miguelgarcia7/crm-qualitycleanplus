import BaseLayout from "@/layouts/BaseLayout";
import authCard from '@/images/admin/auth-card-bg.svg'
import AuthLogo from '@/components/AuthLogo'
import { currentYear, META_DATA } from '@/config/constants'
import { Link } from '@inertiajs/react'
import NewPassForm from './components/NewPassForm'


const Page = () => {
  return (
    <div className="flex min-h-screen items-center p-12.5">
      <div className="container">
        <div className="flex justify-center px-2.5">
          <div className="2xl:w-4/10 md:w-1/2 sm:w-2/3 w-full">
            <div className="absolute end-0 top-0">
              <img src={authCard} alt="auth-card-bg" />
            </div>

            <div className="absolute start-0 bottom-0 rotate-180">
              <img src={authCard} alt="auth-card-bg" />
            </div>
            <div className="card p-7.5 rounded-2xl">
              <div className="mb-3 flex flex-col items-center justify-center text-center">
                <AuthLogo />
                <p className="text-default-400 mx-auto mt-6 mb-4 w-full lg:w-3/4">We&apos;ve emailed you a 6-digit verification code. Please enter it below to confirm your email address</p>
              </div>

              <NewPassForm />

              <p className="text-default-400 my-9 text-center">
                Don’t have a code?&nbsp;
                <Link href="#" className="text-primary font-semibold underline underline-offset-3">
                  Resend&nbsp;
                </Link>
                or&nbsp;
                <Link href="#" className="text-primary font-semibold underline underline-offset-3">
                  Call Us
                </Link>
              </p>

              <p className="text-default-400 text-center">
                Return to&nbsp;
                <Link href="/auth/sign-in" className="underline text-primary font-semibold">
                  Sign in
                </Link>
              </p>
            </div>

            <p className="text-default-400 mt-7.5 text-center">
              &copy; {currentYear} {META_DATA.name} - by <span>{META_DATA.author}</span>
            </p>
          </div>
        </div>
      </div>
    </div>
  )
}


Page.layout = (page: React.ReactNode) => (
  <BaseLayout>{page}</BaseLayout>
)

export default Page
