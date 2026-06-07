import Icon from '@/components/wrappers/Icon'
import { cn } from '@/utils/helpers'
import { useEffect, useState } from 'react'

const Fullscreen = () => {
  const [isFullscreen, setIsFullscreen] = useState(false)

  const toggleFullscreen = () => {
    if (!document.fullscreenElement) {
      document.documentElement.requestFullscreen()
    } else {
      document.exitFullscreen()
    }
  }

  useEffect(() => {
    const handleChange = () => {
      setIsFullscreen(!!document.fullscreenElement)
    }

    document.addEventListener('fullscreenchange', handleChange)

    return () => {
      document.removeEventListener('fullscreenchange', handleChange)
    }
  }, [])
  return (
    <div id="fullscreen-toggler" className="md:inline-flex hidden">
      <div className="topbar-item">
        <button className={cn('topbar-link btn group size-8 rounded-full', { 'fullscreen-active': isFullscreen })} aria-label="Full Screen" onClick={toggleFullscreen}>
          {!isFullscreen ? <Icon icon="maximize" className="text-xl group-[.fullscreen-active]:hidden" /> : <Icon icon="minimize" className="hidden text-xl group-[.fullscreen-active]:inline-block" />}
        </button>
      </div>
    </div>
  )
}

export default Fullscreen
