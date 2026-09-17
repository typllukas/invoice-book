import { useMutation, useQueryClient } from '@tanstack/react-query'
import { enqueueSnackbar } from 'notistack'
import { toApiError } from '@/api/client/apiError'
import { apiClient } from '@/api/client/client'
import type { InvoiceCollectionResponse } from '@/api/schema/types'
import { INVOICES_QUERY_KEY } from './invoiceQueries'
import { invoiceDetailSchema } from './invoiceResponseSchema'

/**
 * No optimistic write: only the server knows the number it assigns.
 */
export function useIssueInvoice() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (invoiceId: string) => {
      const { data, error, response } = await apiClient.POST('/api/invoices/{id}/issue', {
        params: { path: { id: invoiceId } },
      })

      if (data === undefined) {
        throw toApiError(response.status, error)
      }

      return invoiceDetailSchema.parse(data)
    },
    // the row a stamp lands on shows the issued invoice even where the refetch drops it from the list
    onSuccess: (issuedInvoice) => {
      enqueueSnackbar('Faktura byla vystavena.', { variant: 'success' })
      queryClient.setQueriesData<InvoiceCollectionResponse>({ queryKey: [...INVOICES_QUERY_KEY, 'list'] }, (page) =>
        page === undefined
          ? page
          : {
              ...page,
              member: page.member.map((invoice) =>
                invoice.id === issuedInvoice.id
                  ? { ...invoice, number: issuedInvoice.number, status: issuedInvoice.status, issuedAt: issuedInvoice.issuedAt }
                  : invoice,
              ),
            },
      )
    },
    // not awaited, or the refetch has taken the row off the list before the caller can stamp it
    onSettled: () => {
      void queryClient.invalidateQueries({ queryKey: INVOICES_QUERY_KEY })
    },
  })
}
