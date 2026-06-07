import { LayoutProvider } from '@/context/useLayoutContext'
import { useAuth } from '@/hooks/useAuth'
import { preline } from '@/utils/preline'
import { router } from '@inertiajs/react'
import React, { useEffect } from 'react'

const AppProvidersWrapper = ({ children }: { children: React.ReactNode }) => {
  

  const { isAuthenticated } = useAuth()
  useEffect(() => {
    if (!isAuthenticated) {
      router.visit('/auth/sign-in')
    }
  }, [])
  preline.init()
  return <LayoutProvider>{children}</LayoutProvider>
}

export default AppProvidersWrapper
