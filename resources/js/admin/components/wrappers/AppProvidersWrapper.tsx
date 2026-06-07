import { LayoutProvider } from '@/context/useLayoutContext'
import { preline } from '@/utils/preline'
import React, { useEffect } from 'react'

const AppProvidersWrapper = ({ children }: { children: React.ReactNode }) => {
  // Auth is enforced server-side (Fortify + the `auth` middleware redirects to
  // /login). No client-side auth guard here — the theme's dummy useAuth check
  // redirected to a pruned /auth/sign-in route and surfaced a 404 popup.
  useEffect(() => {
    preline.init()
  }, [])

  return <LayoutProvider>{children}</LayoutProvider>
}

export default AppProvidersWrapper
