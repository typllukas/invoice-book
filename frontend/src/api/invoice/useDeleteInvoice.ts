import { useMutation, useQueryClient } from '@tanstack/react-query'
import { enqueueSnackbar } from 'notistack'
import { toApiError } from '@/api/client/apiError'
import { apiClient } from '@/api/client/client'
import { INVOICES_QUERY_KEY } from './invoiceQueries'

export function useDeleteInvoice() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (invoiceId: string) => {
      const { error, response } = await apiClient.DELETE('/api/invoices/{id}', {
        params: { path: { id: invoiceId } },
      })

      if (!response.ok) {
        throw toApiError(response.status, error)
      }
    },
    onSuccess: () => {
      enqueueSnackbar('Koncept faktury byl smazán.', { variant: 'success' })
    },
    onSettled: async () => {
      await queryClient.invalidateQueries({ queryKey: INVOICES_QUERY_KEY })
    },
  })
}
