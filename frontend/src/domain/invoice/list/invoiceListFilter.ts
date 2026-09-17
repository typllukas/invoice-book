import type { InvoiceListQuery } from '@/api/schema/types'
import { INVOICE_STATUS_TONES } from '@/domain/invoice/invoiceStatusTone'

export const STATUS_FILTER_VALUES = ['', ...INVOICE_STATUS_TONES] as const
const SORT_COLUMNS = ['number', 'clientName', 'issuedAt', 'dueAt', 'paidAt', 'status'] as const

export type InvoiceStatusFilter = (typeof STATUS_FILTER_VALUES)[number]
export type InvoiceSortColumn = (typeof SORT_COLUMNS)[number]
export type InvoiceSortDirection = NonNullable<InvoiceListQuery[`order[${InvoiceSortColumn}]`]>

export type InvoiceListFilter = {
  clientName: string
  status: InvoiceStatusFilter
  issuedFrom: string
  issuedTo: string
  sortBy: InvoiceSortColumn | ''
  sortDirection: InvoiceSortDirection
  page: number
}

export const INVOICE_PAGE_SIZE = 30

export const EMPTY_INVOICE_LIST_FILTER: InvoiceListFilter = {
  clientName: '',
  status: '',
  issuedFrom: '',
  issuedTo: '',
  sortBy: '',
  sortDirection: 'asc',
  page: 1,
}

export function parseStatusFilter(value: string | null): InvoiceStatusFilter {
  return STATUS_FILTER_VALUES.find((status) => status === value) ?? ''
}

function parseDateFilter(value: string | null): string {
  if (value === null || !/^\d{4}-\d{2}-\d{2}$/u.test(value)) {
    return ''
  }

  // Date rolls 2026-02-30 over to 2 March, so a day the calendar lacks comes back as another one
  const parsedDate = new Date(`${value}T00:00:00Z`)

  return !Number.isNaN(parsedDate.getTime()) && parsedDate.toISOString().startsWith(value) ? value : ''
}

export function isFilterActive(filter: InvoiceListFilter): boolean {
  return (
    filter.clientName !== '' ||
    filter.status !== '' ||
    filter.issuedFrom !== '' ||
    filter.issuedTo !== ''
  )
}

export function readFilterFromSearchParams(searchParams: URLSearchParams): InvoiceListFilter {
  const page = Number(searchParams.get('page') ?? '1')

  return {
    clientName: searchParams.get('clientName') ?? '',
    status: parseStatusFilter(searchParams.get('status')),
    issuedFrom: parseDateFilter(searchParams.get('issuedFrom')),
    issuedTo: parseDateFilter(searchParams.get('issuedTo')),
    sortBy: SORT_COLUMNS.find((column) => column === searchParams.get('sort')) ?? '',
    sortDirection: searchParams.get('dir') === 'desc' ? 'desc' : 'asc',
    page: Number.isSafeInteger(page) && page >= 1 ? page : 1,
  }
}

export function writeFilterToSearchParams(filter: InvoiceListFilter): URLSearchParams {
  const searchParams = new URLSearchParams()

  if (filter.clientName !== '') {
    searchParams.set('clientName', filter.clientName)
  }
  if (filter.status !== '') {
    searchParams.set('status', filter.status)
  }
  if (filter.issuedFrom !== '') {
    searchParams.set('issuedFrom', filter.issuedFrom)
  }
  if (filter.issuedTo !== '') {
    searchParams.set('issuedTo', filter.issuedTo)
  }
  if (filter.sortBy !== '') {
    searchParams.set('sort', filter.sortBy)
    searchParams.set('dir', filter.sortDirection)
  }
  if (filter.page !== 1) {
    searchParams.set('page', String(filter.page))
  }

  return searchParams
}
