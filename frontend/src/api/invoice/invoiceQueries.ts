import { keepPreviousData, queryOptions, skipToken } from '@tanstack/react-query'
import { toApiError } from '@/api/client/apiError'
import { apiClient } from '@/api/client/client'
import type { InvoiceListQuery } from '@/api/schema/types'
import { readTodayInCalendarZone } from '@/domain/invoice/formatDate'
import type { InvoiceListFilter, InvoiceStatusFilter } from '@/domain/invoice/list/invoiceListFilter'
import { invoiceDetailSchema, invoiceListResponseSchema } from './invoiceResponseSchema'

export const INVOICES_QUERY_KEY = ['invoices'] as const

export function invoiceListQueryOptions(shownFilter: InvoiceListFilter) {
  const filter = { ...shownFilter, clientName: shownFilter.clientName.trim() }

  return queryOptions({
    queryKey: [...INVOICES_QUERY_KEY, 'list', filter],
    queryFn: async ({ signal }) => {
      const today = readTodayInCalendarZone()
      const statusQueries = {
        '': {},
        draft: { status: 'draft' },
        unpaid: { status: 'issued', 'exists[paidAt]': false, 'dueAt[after]': today },
        overdue: { status: 'issued', 'exists[paidAt]': false, 'dueAt[strictly_before]': today },
        paid: { 'exists[paidAt]': true },
      } satisfies Record<InvoiceStatusFilter, InvoiceListQuery>

      const { data, error, response } = await apiClient.GET('/api/invoices', {
        params: {
          query: {
            ...(filter.clientName === '' ? {} : { clientName: filter.clientName }),
            ...statusQueries[filter.status],
            ...(filter.issuedFrom === '' ? {} : { 'issuedAt[after]': filter.issuedFrom }),
            ...(filter.issuedTo === '' ? {} : { 'issuedAt[before]': filter.issuedTo }),
            ...(filter.sortBy === '' ? {} : { [`order[${filter.sortBy}]` as const satisfies keyof InvoiceListQuery]: filter.sortDirection }),
            page: filter.page,
          },
        },
        signal,
      })

      if (data === undefined) {
        throw toApiError(response.status, error)
      }

      return invoiceListResponseSchema.parse(data)
    },
    placeholderData: keepPreviousData,
  })
}

export function invoiceDetailQueryOptions(invoiceId: string | undefined) {
  return queryOptions({
    queryKey: [...INVOICES_QUERY_KEY, 'detail', invoiceId],
    queryFn: invoiceId === undefined ? skipToken : async ({ signal }) => {
      const { data, error, response } = await apiClient.GET('/api/invoices/{id}', {
        params: { path: { id: invoiceId } },
        signal,
      })

      if (data === undefined) {
        throw toApiError(response.status, error)
      }

      return invoiceDetailSchema.parse(data)
    },
  })
}
