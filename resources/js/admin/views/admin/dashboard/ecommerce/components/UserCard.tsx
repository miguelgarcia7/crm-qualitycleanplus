import email from '@/images/admin/svg/email-campaign.svg'
import Icon from '@/components/wrappers/Icon'
import { META_DATA } from '@/config/constants'

import { useEffect, useState } from 'react'

const UserCard = () => {
  const [currentTime, setCurrentTime] = useState('')

  useEffect(() => {
    const updateTime = () => {
      const now = new Date()
      setCurrentTime(
        now.toLocaleTimeString('en-US', {
          hour: '2-digit',
          minute: '2-digit',
          second: '2-digit',
          hour12: true,
        })
      )
    }

    updateTime()
    const timer = setInterval(updateTime, 1000)

    return () => clearInterval(timer)
  }, [])
  return (
    <div className="card h-full">
      <div className="card-body pb-0">
        <div className="flex justify-between items-center">
          <div className="overflow-hidden">
            <h3 className="font-normal text-xl mb-2">
              <span className="text-default-400 text-sm uppercase font-medium">Good Day,</span>
              <br />
              <b>{META_DATA.username}!</b>
            </h3>
          </div>
          <div className="text-end">
            <img className="xl:block hidden" src={email} width={110} alt="Generic placeholder image" />
          </div>
        </div>
      </div>
      <div className="card-body p-2.5 flex items-center bg-light/50 overflow-hidden">
        <p className="flex items-center justify-between w-full">
          <span className="flex items-center gap-1.25">
            <Icon icon="calendar" className="align-middle text-md" />
            <span className="ms-1">{new Date().toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' })}</span>
          </span>
          <span className="flex items-center gap-1.25">
            <Icon icon="clock" className="align-middle text-md" />
            <span className="font-semibold" id="clock-widget">
              {currentTime}
            </span>
          </span>
        </p>
      </div>
    </div>
  )
}

export default UserCard
