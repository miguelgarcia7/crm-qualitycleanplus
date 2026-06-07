import AppProvidersWrapper from '@/components/wrappers/AppProvidersWrapper'

const BaseLayout = ({ children }: { children: React.ReactNode }) => {
  return (
    <AppProvidersWrapper>
      {children}
    </AppProvidersWrapper>
  )
}

export default BaseLayout