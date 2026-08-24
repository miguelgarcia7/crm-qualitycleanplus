import AuthLogo from '@/components/AuthLogo'
import { currentYear, META_DATA } from '@/config/constants'
import authCard from '@/images/admin/auth-card-bg.svg'
import authImg from '@/images/admin/auth.jpg'

type AuthShellProps = {
  title: string
  subtitle?: string
  children: React.ReactNode
  footer?: React.ReactNode
}

/** Split-card shell shared by the Fortify auth screens (sign-in keeps its own). */
const AuthShell = ({ title, subtitle, children, footer }: AuthShellProps) => (
  <div className="flex min-h-screen items-center p-12.5">
    <div className="container">
      <div className="flex justify-center">
        <div className="xl:w-5/6">
          <div className="absolute end-0 top-0">
            <img src={authCard} alt="" />
          </div>

          <div className="absolute start-0 bottom-0 rotate-180">
            <img src={authCard} alt="" />
          </div>

          <div className="card rounded-2xl">
            <div className="grid grid-cols-1 lg:grid-cols-2">
              <div className="card-body relative p-12.5">
                <div className="mb-7.5 flex flex-col items-center justify-center text-center">
                  <AuthLogo />
                  <h4 className="text-default-900 mt-7.5 mb-2 text-base font-bold">{title}</h4>
                  {subtitle && <p className="text-default-400 mx-auto w-full lg:w-3/4">{subtitle}</p>}
                </div>

                {children}

                {footer}

                <p className="text-default-400 mt-7.5 text-center">
                  &copy; {currentYear} {META_DATA.name} - by <span>{META_DATA.author}</span>
                </p>
              </div>

              <div className="relative hidden h-full overflow-hidden rounded-e-2xl bg-cover bg-center object-cover lg:block" style={{ backgroundImage: `url(${authImg})` }}>
                <div className="absolute inset-0 flex items-end justify-center rounded-e-sm p-9 [background:linear-gradient(to_top,#313a46,rgba(49,58,70,.8),rgba(49,58,70,.5))]"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
)

export default AuthShell
