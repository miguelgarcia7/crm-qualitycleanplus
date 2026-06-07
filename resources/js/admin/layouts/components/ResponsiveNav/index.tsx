import Sidenav from '../Sidenav'
import HorizontalNav from '../HorizontalNav'
import { useEffect, useState } from 'react'




const ResponsiveNav = () => {
  const [isMobile, setIsMobile] = useState<null | boolean>(null)

  useEffect(() => {
    const checkScreen = () => setIsMobile(window.innerWidth < 992)
    checkScreen()
    window.addEventListener('resize', checkScreen)
    return () => window.removeEventListener('resize', checkScreen)
  }, [])

  if (isMobile === null) return null

  return isMobile ? <Sidenav /> : <HorizontalNav />
}

export default ResponsiveNav
