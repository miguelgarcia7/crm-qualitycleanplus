import { useLayoutContext } from '@/context/useLayoutContext'
import HorizontalLayout from '@/layouts/HorizontalLayout'
import VerticalLayout from '@/layouts/VerticalLayout'
import ConfirmHost from '@/components/ConfirmHost'
import CheckInFab from '@/layouts/components/CheckInFab'

const MainLayout = ({ children }: { children: React.ReactNode }) => {
  const { orientation } = useLayoutContext()

  return (
    <>
      {orientation === 'vertical' && <VerticalLayout>{ children }</VerticalLayout>}
      {orientation === 'horizontal' && <HorizontalLayout>{ children }</HorizontalLayout>}
      <CheckInFab />
      <ConfirmHost />
    </>
  )
}

export default MainLayout