import clsx, { ClassValue } from 'clsx'
import { twMerge } from 'tailwind-merge'

export const generateInitials = (name = ''): string => {
  return name
    .split(' ')
    .map((word) => word[0])
    .join('')
    .toUpperCase()
}

export const toPascalCase = (value: string) =>
  value
    .replace(/[-_ ]+/g, ' ')
    .split(' ')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
    .join(' ')

export const toTitleCase = (value?: string) => {
  if (!value) return ''
  return value
    .replace(/[-_]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
    .split(' ')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
    .join(' ')
}

export const formatBytes = (bytes: number, decimals: number = 2) => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const dm = decimals < 0 ? 0 : decimals
  const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB']

  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i]
}

export function getColor(v: string, a: number = 1): string {
  if (typeof window === 'undefined') return 'rgba(0,0,0,0.2)'

  const val = getComputedStyle(document.documentElement).getPropertyValue(`--color-${v}`).trim()

  if (!val) return 'rgba(0,0,0,0.2)'

  if (a === 1) return val

  if (val.startsWith('#')) {
    const c = val.replace('#', '')
    const bigint = parseInt(c, 16)
    const r = (bigint >> 16) & 255
    const g = (bigint >> 8) & 255
    const b = bigint & 255
    return `rgba(${r}, ${g}, ${b}, ${a})`
  }

  if (val.startsWith('rgb')) {
    const rgb = val.match(/\d+,\s*\d+,\s*\d+/)?.[0]
    return rgb ? `rgba(${rgb}, ${a})` : val
  }

  return val
}

export const getFont = () => {
  if (typeof window === 'undefined') {
    return
  }
  return getComputedStyle(document.body).fontFamily.trim()
}

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

export const splitArray = <T>(arr: T[], size: number): T[][] => {
  return Array.from({ length: Math.ceil(arr.length / size) }, (_, i) => arr.slice(i * size, i * size + size))
}

/**
 * Render a 24-hour "HH:MM" clock time as 12-hour with am/pm — "09:00" → "9:00 am".
 *
 * The payload stays 24-hour because `<input type="time">` requires that format;
 * this is for display only.
 */
export const formatClockTime = (time?: string | null): string => {
  if (!time) return ''

  const [rawHours, rawMinutes] = time.split(':')
  const hours = Number(rawHours)

  if (Number.isNaN(hours)) return time

  const period = hours < 12 ? 'am' : 'pm'
  const hour12 = hours % 12 === 0 ? 12 : hours % 12

  return `${hour12}:${(rawMinutes ?? '00').padStart(2, '0')} ${period}`
}
