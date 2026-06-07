import product1 from '@/images/admin/products/1.png'
import product10 from '@/images/admin/products/10.png'
import product2 from '@/images/admin/products/2.png'
import product3 from '@/images/admin/products/3.png'
import product4 from '@/images/admin/products/4.png'
import product5 from '@/images/admin/products/5.png'
import product6 from '@/images/admin/products/6.png'
import product7 from '@/images/admin/products/7.png'
import product8 from '@/images/admin/products/8.png'
import product9 from '@/images/admin/products/9.png'

export type StatType = {
  title: string
  value: number
  prefix?: string
  suffix?: string
  change: number
  icon: string
}

export const statData: StatType[] = [
  { title: 'Orders', value: 9754, change: -1.89, icon: 'shopping-cart' },
  { title: 'Revenue', value: 75.21, prefix: '$', suffix: 'k', change: -5.23, icon: 'pig-money' },
  { title: 'Growth', value: 25.08, prefix: '+', suffix: '%', change: 4.87, icon: 'trending-up' },
]

export type SaleReportType = {
  title: string
  value: number
  prefix?: string
  suffix?: string
  icon: string
}

export const saleReportData: SaleReportType[] = [
  { title: 'Revenue', value: 78224.68, prefix: '$', icon: 'wallet' },
  { title: 'Orders', value: 8541, icon: 'basket' },
  { title: 'Growth Rate', value: 25.3, suffix: '%', icon: 'trending-up' },
]

export type ProductType = {
  image: string
  name: string
  brand: string
  price: string
  quantity: number
  amount: string
  status: 'in-stock' | 'low-stock' | 'out-of-stock'
}

export const productData: ProductType[] = [
  {
    image: product1,
    name: 'Modern Fabric Sofa Set',
    brand: 'Homeluxe',
    price: '$499.00',
    quantity: 34,
    amount: '$16,966.00',
    status: 'low-stock',
  },
  {
    image: product2,
    name: 'L-Shaped Sectional Sofa',
    brand: 'ComfortHub',
    price: '$899.00',
    quantity: 21,
    amount: '$18,879.00',
    status: 'in-stock',
  },
  {
    image: product3,
    name: 'Velvet Recliner Chair',
    brand: 'SoftEase',
    price: '$379.00',
    quantity: 47,
    amount: '$17,813.00',
    status: 'in-stock',
  },
  {
    image: product4,
    name: 'Classic Wooden Coffee Table',
    brand: 'OakCraft',
    price: '$259.00',
    quantity: 58,
    amount: '$15,022.00',
    status: 'out-of-stock',
  },
  {
    image: product5,
    name: 'Minimalist TV Stand',
    brand: 'FurniPro',
    price: '$315.00',
    quantity: 64,
    amount: '$20,160.00',
    status: 'in-stock',
  },
  {
    image: product6,
    name: 'Leather Lounge Chair',
    brand: 'UrbanStyle',
    price: '$425.00',
    quantity: 39,
    amount: '$16,575.00',
    status: 'low-stock',
  },
  {
    image: product7,
    name: 'Glass Center Table',
    brand: 'CrystalCasa',
    price: '$289.00',
    quantity: 52,
    amount: '$15,028.00',
    status: 'in-stock',
  },
  {
    image: product8,
    name: 'Wooden Bookshelf Unit',
    brand: 'TimberWorks',
    price: '$349.00',
    quantity: 28,
    amount: '$9,772.00',
    status: 'low-stock',
  },
  {
    image: product9,
    name: 'Luxury King Bed Frame',
    brand: 'DreamRest',
    price: '$1,099.00',
    quantity: 15,
    amount: '$16,485.00',
    status: 'out-of-stock',
  },
  {
    image: product10,
    name: 'Round Dining Table Set',
    brand: 'CasaDine',
    price: '$725.00',
    quantity: 25,
    amount: '$18,125.00',
    status: 'in-stock',
  },
  {
    image: product2,
    name: 'Ergonomic Office Chair',
    brand: 'WorkEase',
    price: '$269.00',
    quantity: 44,
    amount: '$11,836.00',
    status: 'in-stock',
  },
  {
    image: product5,
    name: 'Nightstand with Drawers',
    brand: 'CozyHome',
    price: '$189.00',
    quantity: 53,
    amount: '$10,017.00',
    status: 'low-stock',
  },
]

export type OrderType = {
  id: string
  customer: {
    name: string
    email: string
  }
  date: string
  amount: string
  payment: string
  status: 'completed' | 'pending' | 'cancelled' | 'failed'
}

export const orderData: OrderType[] = [
  {
    id: '#ORD-1023',
    customer: { name: 'John Carter', email: 'john@example.com' },
    date: '12 Nov 2025',
    amount: '$249.00',
    payment: 'Credit Card',
    status: 'completed',
  },
  {
    id: '#ORD-1022',
    customer: { name: 'Emma Wilson', email: 'emma@example.com' },
    date: '12 Nov 2025',
    amount: '$179.00',
    payment: 'UPI',
    status: 'pending',
  },
  {
    id: '#ORD-1021',
    customer: { name: 'Michael Harris', email: 'michael@example.com' },
    date: '11 Nov 2025',
    amount: '$329.00',
    payment: 'PayPal',
    status: 'completed',
  },
  {
    id: '#ORD-1020',
    customer: { name: 'Sophia Turner', email: 'sophia@example.com' },
    date: '11 Nov 2025',
    amount: '$125.00',
    payment: 'Debit Card',
    status: 'cancelled',
  },
  {
    id: '#ORD-1019',
    customer: { name: 'Chris Evans', email: 'chris@example.com' },
    date: '10 Nov 2025',
    amount: '$560.00',
    payment: 'Credit Card',
    status: 'completed',
  },
  {
    id: '#ORD-1018',
    customer: { name: 'Ava Mitchell', email: 'ava@example.com' },
    date: '10 Nov 2025',
    amount: '$98.00',
    payment: 'Cash',
    status: 'pending',
  },
  {
    id: '#ORD-1017',
    customer: { name: 'Liam Parker', email: 'liam@example.com' },
    date: '09 Nov 2025',
    amount: '$412.00',
    payment: 'Net Banking',
    status: 'completed',
  },
  {
    id: '#ORD-1016',
    customer: { name: 'Isabella Rose', email: 'isabella@example.com' },
    date: '09 Nov 2025',
    amount: '$255.00',
    payment: 'Credit Card',
    status: 'failed',
  },
  {
    id: '#ORD-1015',
    customer: { name: 'Oliver Brown', email: 'oliver@example.com' },
    date: '08 Nov 2025',
    amount: '$720.00',
    payment: 'UPI',
    status: 'completed',
  },
  {
    id: '#ORD-1014',
    customer: { name: 'Charlotte Green', email: 'charlotte@example.com' },
    date: '08 Nov 2025',
    amount: '$138.00',
    payment: 'PayPal',
    status: 'pending',
  },
]

export type ActivityType = {
  title: string
  description: string
  author: string
  icon: string
  className: string
}

export const activityData: ActivityType[] = [
  {
    title: 'New Orders Synced from Storefront',
    description: '1,250 new customer orders were successfully imported from the online store.',
    author: 'Olivia Green',
    icon: 'shopping-cart',
    className: 'bg-primary',
  },
  {
    title: 'Payment Gateway Integration Updated',
    description: 'Stripe API upgraded to support faster settlements and improved security tokens.',
    author: 'James Parker',
    icon: 'credit-card',
    className: 'bg-success',
  },
  {
    title: 'Inventory Levels Auto-Synced',
    description: 'All product quantities were updated based on the latest warehouse data.',
    author: 'Sophia Lee',
    icon: 'package',
    className: 'bg-warning',
  },
  {
    title: 'New Vendor Accounts Approved',
    description: 'Five new seller accounts were verified and added to the marketplace.',
    author: 'Liam Johnson',
    icon: 'user',
    className: 'bg-info',
  },
  {
    title: 'Refund Requests Reviewed',
    description: '27 refund claims were processed successfully with zero pending disputes.',
    author: 'Ethan Miller',
    icon: 'alert-circle',
    className: 'bg-danger',
  },
  {
    title: 'Summer Campaign Launched',
    description: 'The “Summer Deals 2025” campaign is now live across all marketing channels.',
    author: 'Ava Mitchell',
    icon: 'speakerphone',
    className: 'bg-secondary',
  },
]
