import logoDark from '@/images/admin/logo-black.png'
import logo from '@/images/admin/logo.png'
import { Link } from '@inertiajs/react'

const AuthLogo = () => {
  return (
    <>
      <Link href="/" className="auth-logo">
        <img src={logoDark} alt="logo" className="flex dark:hidden" />
        <img src={logo} alt="dark logo" className="hidden dark:flex" />
      </Link>
    </>
  )
}

export default AuthLogo
