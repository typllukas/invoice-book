import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toApiError } from '@/api/client/apiError'
import { apiClient } from '@/api/client/client'
import type { InvoiceInput } from '@/api/schema/types'
import { invoiceDetailSchema } from './invoiceResponseSchema'
import { INVOICES_QUERY_KEY, invoiceDetailQueryOptions } from './invoiceQueries'

// openapi-fetch would send application/json, which this API answers with a 415
const JSON_LD_HEADERS = { 'Content-Type': 'application/ld+json' } as const

export function useSaveInvoice(invoiceId: string | undefined) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (body: InvoiceInput) => {
      const { data, error, response } =
        invoiceId === undefined
          ? await apiClient.POST('/api/invoices', {
              body,
              headers: JSON_LD_HEADERS,
            })
          : await apiClient.PUT('/api/invoices/{id}', {
              params: { path: { id: invoiceId } },
              body,
              headers: JSON_LD_HEADERS,
            })

      if (data === undefined) {
        throw toApiError(response.status, error)
      }

      return invoiceDetailSchema.parse(data)
    },
    onSuccess: (savedInvoice) => {
      queryClient.setQueryData(invoiceDetailQueryOptions(savedInvoice.id).queryKey, savedInvoice)
    },
    onSettled: async () => {
      await queryClient.invalidateQueries({ queryKey: INVOICES_QUERY_KEY })
    },
  })
}
