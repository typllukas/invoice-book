import { queryOptions } from '@tanstack/react-query'
import { toApiError } from '@/api/client/apiError'
import { apiClient } from '@/api/client/client'
import { invoiceListResponseSchema } from './invoiceResponseSchema'

export const INVOICES_QUERY_KEY = ['invoices'] as const

export function invoiceListQueryOptions() {
  return queryOptions({
    queryKey: [...INVOICES_QUERY_KEY, 'list'],
    queryFn: async ({ signal }) => {
      const { data, error, response } = await apiClient.GET('/api/invoices', { signal })

      if (data === undefined) {
        throw toApiError(response.status, error)
      }

      return invoiceListResponseSchema.parse(data)
    },
  })
}
