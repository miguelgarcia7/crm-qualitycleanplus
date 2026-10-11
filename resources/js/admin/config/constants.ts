export const META_DATA = {
  name: 'Minute',
  title: 'Quality Cleaning Plus',
  description: 'Minute is a modern, responsive admin dashboard for tracking time.',
  author: 'Quality Cleaning Plus, Inc.',
  username: 'Miguel Dev',
  keywords: 'Minute, admin dashboard, StudioMex, time tracking',
  version: '1.0.0',
}

export const currentYear = new Date().getFullYear()

type CurrencyType = '₹' | '$' | '€'

export const currency: CurrencyType = '$'

/** Timezones a property can be in — the create form and the property's Settings share this list. */
export const PROPERTY_TIMEZONES = [
  'America/Phoenix',
  'America/Los_Angeles',
  'America/Denver',
  'America/Chicago',
  'America/New_York',
  'America/Anchorage',
  'Pacific/Honolulu',
]
