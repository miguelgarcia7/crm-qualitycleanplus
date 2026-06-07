import Icon from '@/components/wrappers/Icon'

const MonochromeMode = () => {
  const toggleMonochromeMode = () => {
    const htmlEl = document.getElementsByTagName('html')[0]
    htmlEl.classList.toggle('monochrome')
  }
  return (
    <div className="xl:inline-flex hidden">
      <div id="monochrome-toggler" className="topbar-item">
        <button className="topbar-link btn btn-sm size-8 rounded-full" id="monochrome-mode" type="button" aria-label="Monochrome Mode" onClick={toggleMonochromeMode}>
          <Icon icon="palette" className="topbar-link-icon" />
        </button>
      </div>
    </div>
  )
}

export default MonochromeMode
