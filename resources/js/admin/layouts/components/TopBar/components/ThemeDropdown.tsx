import Icon from '@/components/wrappers/Icon'
import { useLayoutContext } from '@/context/useLayoutContext'

type ThemeOption = {
  value: string
  label: string
  icon: string
}

export const themeDropdownOptions: ThemeOption[] = [
  { value: 'light', label: 'Light', icon: 'sun' },
  { value: 'dark', label: 'Dark', icon: 'moon' },
  { value: 'system', label: 'System', icon: 'sun-moon' },
]

const ThemeMode = () => {
  const { theme, updateSettings } = useLayoutContext()

  return (
    <div id="theme-dropdown" className="sm:inline-flex hidden">
      <div className="topbar-item hs-dropdown relative inline-flex [--auto-close:inside] [--placement:bottom-right]">
        <button className="topbar-link hs-dropdown-toggle rounded-full" type="button" aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
          {theme === 'light' ? <Icon icon="sun" className="text-xl" /> : <Icon icon="moon" className="text-xl" />}
        </button>
        <div className="hs-dropdown-menu" role="menu" aria-orientation="vertical" aria-labelledby="dropdown-menu">
          {themeDropdownOptions.map((option, idx) => (
            <div className="dropdown-item relative p-0" key={idx}>
              <input value={option.value} className="peer invisible absolute size-0" type="radio" name="data-theme" id={`topbar-dropdown-${option.value}`} checked={theme === option.value} onChange={() => updateSettings({ theme: option.value })} />
              <label className="peer-checked:bg-default-100 w-full cursor-pointer rounded px-3.75 py-1.5" htmlFor={`topbar-dropdown-${option.value}`}>
                <span className="flex items-center gap-1">
                  <Icon icon={option.icon} className="me-1 align-middle text-base" />
                  {option.label}
                </span>
              </label>
            </div>
          ))}
        </div>
      </div>
    </div>
  )
}

export default ThemeMode
