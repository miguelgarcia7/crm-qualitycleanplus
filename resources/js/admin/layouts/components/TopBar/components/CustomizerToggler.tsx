import Icon from '@/components/wrappers/Icon'
const CustomizerToggler = () => {
  return (
    <div className="sm:inline-flex hidden">
      <div className="topbar-item btn-theme-setting">
        <button className="topbar-link btn btn-icon size-8 rounded-full" type="button" aria-haspopup="dialog" aria-expanded="false" aria-controls="theme-customization" data-hs-overlay="#theme-customization">
          <span className="topbar-link-icon">
            <Icon icon="settings" />
          </span>
        </button>
      </div>
    </div>
  )
}

export default CustomizerToggler
