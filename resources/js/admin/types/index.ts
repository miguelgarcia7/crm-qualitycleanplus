import React from 'react'

export type MenuItemType = {
  slug: string
  label: string
  isTitle?: boolean
  icon?: string
  url?: string
  badge?: {
    className: string
    text: string
  }
  parentKey?: string
  target?: string
  isDisabled?: boolean
  isSpecial?: boolean
  children?: MenuItemType[]
}

export type ChildrenType = Readonly<{ children: React.ReactNode }>

export type VariantType = 'primary' | 'danger' | 'warning' | 'success' | 'info' | 'dark' | 'secondary' | 'purple' | 'light'

export type FileType = File & {
  path?: string
  preview?: string
  formattedSize?: string
}
