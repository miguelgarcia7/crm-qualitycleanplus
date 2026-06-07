import Icon from '@/components/wrappers/Icon'
import { menuItems } from '@/layouts/components/data'
import type { MenuItemType } from '@/types'
import { cn } from '@/utils/helpers'
import { scrollToElement } from '@/utils/layout'
import { Link } from '@inertiajs/react'
import { usePage } from '@inertiajs/react'
import { Fragment, useCallback, useEffect, useState } from 'react'

const MenuItemWithChildren = ({ item, openMenuKey, setOpenMenuKey, level = 0 }: { item: MenuItemType; openMenuKey: string | null; setOpenMenuKey: (key: string | null) => void; level?: number }) => {
  const pathname = usePage().url
  const isTopLevel = level === 0

  const [localOpen, setLocalOpen] = useState(false)
  const [didAutoOpen, setDidAutoOpen] = useState(false)

  const isChildActive = useCallback(
    function checkChildren(children: MenuItemType[] = []): boolean {
      return children.some((child) => (child.url && pathname.startsWith(child.url)) || (child.children && checkChildren(child.children)))
    },
    [pathname]
  )

  const isActive = (item.url && pathname.startsWith(item.url)) || isChildActive(item.children)

  const isOpen = isTopLevel ? openMenuKey === item.slug : localOpen

  useEffect(() => {
    if (isActive && !didAutoOpen) {
      if (isTopLevel) {
        setOpenMenuKey(item.slug)
      } else {
        setLocalOpen(true)
      }
      setDidAutoOpen(true)
    }
  }, [isActive, didAutoOpen, isTopLevel, item.slug, setOpenMenuKey])

  const toggleOpen = (e: React.MouseEvent) => {
    if (item.children?.length) e.preventDefault()
    if (isTopLevel) {
      setOpenMenuKey(isOpen ? null : item.slug)
    } else {
      setLocalOpen((prev) => !prev)
    }
  }

  return (
    <li
      className={cn('menu-item hs-accordion', {
        active: isActive,
      })}
    >
      <Link href={item.url ?? '#'} onClick={toggleOpen} className={cn('hs-accordion-toggle menu-link', isActive && 'active')} aria-expanded={isOpen}>
        {item.icon && isTopLevel && (
          <span className="menu-icon">
            <Icon icon={item.icon} />
          </span>
        )}
        <span className="menu-text">{item.label}</span>
        {item.badge ? <span className={cn('badge text-white', item.badge.className)}>{item.badge.text}</span> : <span className="menu-arrow" />}
      </Link>

      <ul className={cn('sub-menu hs-accordion-content hs-accordion-group', { 'hidden': !isOpen })}>
        {(item.children || []).map((child) => (child.children ? <MenuItemWithChildren key={child.slug} item={child} openMenuKey={openMenuKey} setOpenMenuKey={setOpenMenuKey} level={level + 1} /> : <MenuItem key={child.slug} item={child} level={level + 1} />))}
      </ul>
    </li>
  )
}

const MenuItem = ({ item, level = 0 }: { item: MenuItemType; level?: number }) => {
  const pathname = usePage().url
  const isTopLevel = level === 0

  const isActive = item.url && pathname.startsWith(item.url)
  if (!item.url) return null
  return (
    <li className={cn('menu-item', isActive && 'active')}>
      <Link href={item.url ?? '/'} className={cn('menu-link', isActive && 'active', item.isDisabled && 'disabled', item.isSpecial && 'special-menu')}>
        {item.icon && isTopLevel && (
          <span className="menu-icon">
            <Icon icon={item.icon} />
          </span>
        )}
        <span className="menu-text">{item.label}</span>
        {item.badge && <span className={cn('badge text-white', item.badge.className)}>{item.badge.text}</span>}
      </Link>
    </li>
  )
}

const AppMenu = () => {
  const [openMenuKey, setOpenMenuKey] = useState<string | null>(null)
  const scrollToActiveLink = () => {
    const activeItem: HTMLAnchorElement | null = document.querySelector('.menu-link.active')
    if (activeItem) {
      const simpleBarContent = document.querySelector('#sidenav-menu .simplebar-content-wrapper')
      if (simpleBarContent) {
        const offset = activeItem.offsetTop - window.innerHeight * 0.4
        scrollToElement(simpleBarContent, offset, 500)
      }
    }
  }
  useEffect(() => {
    setTimeout(scrollToActiveLink, 150)
  }, [])

  return (
    <ul className="side-nav hs-accordion-group px-2.5 pb-16.5">
      {menuItems.map((item, idx) => (
        <Fragment key={idx}>
          {item.isTitle && (
            <li className="menu-title mt-0!">
              {' '}
              <span>{item.label}</span>
            </li>
          )}
          {(item.children || [item]).map((item, idx) => (
            <Fragment key={idx}>{item.children ? <MenuItemWithChildren key={item.slug} item={item} openMenuKey={openMenuKey} setOpenMenuKey={setOpenMenuKey} /> : <MenuItem key={item.slug} item={item} />}</Fragment>
          ))}
        </Fragment>
      ))}
    </ul>
  )
}

export default AppMenu
