import deflag from '@/images/admin/flags/de.svg'
import esflag from '@/images/admin/flags/es.svg'
import inflag from '@/images/admin/flags/in.svg'
import itflag from '@/images/admin/flags/it.svg'
import ruflag from '@/images/admin/flags/ru.svg'
import FlagSa from '@/images/admin/flags/sa.svg'
import usflag from '@/images/admin/flags/us.svg'
import { useLayoutContext } from '@/context/useLayoutContext'

import { Link } from '@inertiajs/react'
import { useState } from 'react'

type Language = {
  code: string
  name: string
  flag: string
}

const languages: Language[] = [
  { code: 'EN', name: 'English', flag: usflag },
  { code: 'DE', name: 'Deutsch', flag: deflag },
  { code: 'IT', name: 'Italiano', flag: itflag },
  { code: 'ES', name: 'Español', flag: esflag },
  { code: 'RU', name: 'Русский', flag: ruflag },
  { code: 'HI', name: 'हिन्दी', flag: inflag },
  { code: 'SA', name: 'عربي', flag: FlagSa },
]

const LanguageSelectorRounded = () => {
  const [selected, setSelected] = useState<Language>(languages[0])
  const { updateSettings, dir } = useLayoutContext()
  const handleLanguageChange = (lang: Language) => {
    setSelected(lang)
    if (lang.code === 'SA' && dir === 'ltr') {
      updateSettings({ dir: 'rtl' })
    } else if (lang.code !== 'SA' && dir === 'rtl') {
      updateSettings({ dir: 'ltr' })
    }
  }
  return (
    <>
      <div className="topbar-item hs-dropdown relative inline-flex [--placement:bottom-right]" id="language-selector-rounded">
        <button className="topbar-link hs-dropdown-toggle font-bold relative flex items-center" type="button" aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
          <img src={selected.flag} alt="" className="me-3 size-4.5 rounded-full" />
          <span id="selected-language-code">{selected.code}</span>
        </button>
        <div className="hs-dropdown-menu" role="menu" aria-orientation="vertical" aria-labelledby="dropdown-menu">
          {languages.map((language, idx) => (
            <Link href="" onClick={() => handleLanguageChange(language)} className="dropdown-item" data-translator-lang={language.code} title="English" key={idx}>
              <img src={language.flag} alt="English" className="me-1 size-4 rounded-full" height={18} data-translator-image />
              <span className="align-middle">{language.name}</span>
            </Link>
          ))}
        </div>
      </div>
    </>
  )
}

export default LanguageSelectorRounded
