import { useMutation } from '@tanstack/react-query'
import { toApiError } from '@/api/client/apiError'
import { apiClient } from '@/api/client/client'
import { invoicePdfSchema } from './invoiceResponseSchema'

/**
 * A GET run as a mutation, so it starts on a click; a query would fetch the PDF on mount and keep it in the cache.
 */
export function useDownloadInvoicePdf() {
  return useMutation({
    mutationFn: async (invoiceId: string) => {
      const { data, error, response } = await apiClient.GET('/api/invoices/{id}/pdf', {
        params: { path: { id: invoiceId } },
      })

      if (data === undefined) {
        throw toApiError(response.status, error)
      }

      return invoicePdfSchema.parse(data)
    },
    onSuccess: (invoicePdf) => {
      const downloadLink = document.createElement('a')
      downloadLink.href = `data:application/pdf;base64,${invoicePdf.base64Content}`
      downloadLink.download = invoicePdf.name
      downloadLink.click()
    },
  })
}
