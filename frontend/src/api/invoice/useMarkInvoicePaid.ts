import { useMutation, useQueryClient } from '@tanstack/react-query'
import { enqueueSnackbar } from 'notistack'
import { toApiError } from '@/api/client/apiError'
import { apiClient } from '@/api/client/client'
import type { InvoiceRow } from '@/api/schema/types'
import type { InvoiceListFilter } from '@/domain/invoice/list/invoiceListFilter'
import { INVOICES_QUERY_KEY, invoiceListQueryOptions } from './invoiceQueries'

const MARK_PAID_MUTATION_KEY = [...INVOICES_QUERY_KEY, 'markPaid'] as const

export function useMarkInvoicePaid(shownListFilter?: InvoiceListFilter) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationKey: MARK_PAID_MUTATION_KEY,
    mutationFn: async (invoiceId: string) => {
      const { error, response } = await apiClient.POST('/api/invoices/{id}/mark_paid', {
        params: { path: { id: invoiceId } },
      })

      if (!response.ok) {
        throw toApiError(response.status, error)
      }
    },

    onMutate: async (invoiceId) => {
      if (shownListFilter === undefined) {
        return undefined
      }

      const listQueryKey = invoiceListQueryOptions(shownListFilter).queryKey

      // cancel first, or an older refetch lands after this write and undoes it
      await queryClient.cancelQueries({ queryKey: listQueryKey })

      const unpaidRow = queryClient.getQueryData(listQueryKey)?.member.find((invoice) => invoice.id === invoiceId)
      const paidAt = new Date().toISOString()

      // in place even under the unpaid filter, the list holds the row until its stamp has landed
      queryClient.setQueryData(listQueryKey, (page) =>
        page === undefined
          ? page
          : {
              ...page,
              member: page.member.map((invoice) =>
                invoice.id === invoiceId ? ({ ...invoice, paidAt } satisfies InvoiceRow) : invoice,
              ),
            },
      )

      return { listQueryKey, unpaidRow }
    },

    onSuccess: () => {
      enqueueSnackbar('Faktura byla označena jako uhrazená.', { variant: 'success' })
    },

    // only the failed row, another row paid meanwhile keeps its optimistic write
    onError: (_error, _invoiceId, onMutateResult) => {
      if (onMutateResult?.unpaidRow === undefined) {
        return
      }
      const { listQueryKey, unpaidRow } = onMutateResult

      queryClient.setQueryData(listQueryKey, (page) =>
        page === undefined
          ? page
          : { ...page, member: page.member.map((invoice) => (invoice.id === unpaidRow.id ? unpaidRow : invoice)) },
      )
    },

    onSettled: async () => {
      if (queryClient.isMutating({ mutationKey: MARK_PAID_MUTATION_KEY }) === 1) {
        await queryClient.invalidateQueries({ queryKey: INVOICES_QUERY_KEY })
      }
    },
  })
}
