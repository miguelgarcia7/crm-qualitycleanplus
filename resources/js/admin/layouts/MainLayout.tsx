import { useLayoutContext } from '@/context/useLayoutContext'
import HorizontalLayout from '@/layouts/HorizontalLayout'
import VerticalLayout from '@/layouts/VerticalLayout'

const MainLayout = ({ children }: { children: React.ReactNode }) => {
  const { orientation } = useLayoutContext()

  return (
    <>
      {orientation === 'vertical' && <VerticalLayout>{ children }</VerticalLayout>}
      {orientation === 'horizontal' && <HorizontalLayout>{ children }</HorizontalLayout>}
    </>
  )
}

export default MainLayout