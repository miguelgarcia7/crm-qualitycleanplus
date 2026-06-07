import { useEffect } from 'react'

const HTMLClassHandler = ({ classes }: { classes: string[] }) => {
  useEffect(() => {
    const htmlEl = document.getElementsByTagName('html')[0]
    htmlEl.classList.add(...classes)
    return () => {
      htmlEl.classList.remove(...classes)
    }
  }, [classes])

  return null
}

export default HTMLClassHandler
