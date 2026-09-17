import { enqueueSnackbar } from 'notistack'
import { useState } from 'react'
import { useNavigate } from 'react-router'
import { describeFailure } from '@/api/client/apiError'
import type { InvoiceRow } from '@/api/schema/types'
import { useDeleteInvoice } from '@/api/invoice/useDeleteInvoice'
import { useIssueInvoice } from '@/api/invoice/useIssueInvoice'
import { useMarkInvoicePaid } from '@/api/invoice/useMarkInvoicePaid'
import type { InvoiceStampKind } from '@/config/theme'
import { writeFilterToSearchParams, type InvoiceListFilter } from '@/domain/invoice/list/invoiceListFilter'
import { findIssueBlocker } from '@/domain/invoice/issueBlocker'

/** The stamp lands over the row, then turns into the status chip through the shared layoutId. */
export type RowStamp = {
  invoiceId: string
  kind: InvoiceStampKind
  phase: 'landing' | 'settling'
}

type HeldInvoiceRows = {
  filterKey: string
  invoices: InvoiceRow[]
}

type PendingInvoiceAction = {
  kind: 'issue' | 'delete'
  invoice: InvoiceRow
}

function reportActionFailure(error: unknown) {
  enqueueSnackbar(describeFailure(error, 'Akci se nepodařilo dokončit.'), { variant: 'error' })
}

export function useInvoiceListActions(filter: InvoiceListFilter, shownInvoices: InvoiceRow[] | undefined) {
  const navigate = useNavigate()
  const issueInvoice = useIssueInvoice()
  const deleteInvoice = useDeleteInvoice()
  const markInvoicePaid = useMarkInvoicePaid(filter)
  const [pendingAction, setPendingAction] = useState<PendingInvoiceAction | null>(null)
  const [rowStamp, setRowStamp] = useState<RowStamp | null>(null)
  const [heldInvoiceRows, setHeldInvoiceRows] = useState<HeldInvoiceRows | null>(null)
  const filterKey = writeFilterToSearchParams(filter).toString()

  // a row the filter, the sort or the page took off screen must not replay its stamp later
  if (rowStamp !== null && heldInvoiceRows !== null && heldInvoiceRows.filterKey !== filterKey) {
    setRowStamp(null)
    setHeldInvoiceRows(null)
  } else if (rowStamp?.phase === 'landing' && shownInvoices !== undefined) {
    // the rows keep their places until the stamp has landed and take only fresh contents
    const heldInvoices = (heldInvoiceRows?.invoices ?? shownInvoices).map(
      (heldInvoice) => shownInvoices.find((invoice) => invoice.id === heldInvoice.id) ?? heldInvoice,
    )
    if (heldInvoiceRows === null || heldInvoices.some((invoice, index) => invoice !== heldInvoiceRows.invoices[index])) {
      setHeldInvoiceRows({ filterKey, invoices: heldInvoices })
    }
  } else if (heldInvoiceRows !== null) {
    setHeldInvoiceRows(null)
  } else if (rowStamp !== null && shownInvoices?.every((invoice) => invoice.id !== rowStamp.invoiceId)) {
    setRowStamp(null)
  }

  const closePendingAction = () => {
    setPendingAction(null)
  }

  return {
    invoices: heldInvoiceRows?.invoices ?? shownInvoices,
    rowStamp,
    pendingAction,
    issueBlocker: pendingAction?.kind === 'issue' ? findIssueBlocker(pendingAction.invoice) : null,
    isIssuing: issueInvoice.isPending,
    isDeleting: deleteInvoice.isPending,

    requestIssue: (invoice: InvoiceRow) => {
      setPendingAction({ kind: 'issue', invoice })
    },
    requestDelete: (invoice: InvoiceRow) => {
      setPendingAction({ kind: 'delete', invoice })
    },
    closePendingAction,

    confirmIssue: () => {
      if (pendingAction === null) {
        return
      }
      const invoiceId = pendingAction.invoice.id
      issueInvoice.mutate(invoiceId, {
        onSuccess: () => {
          setRowStamp({ invoiceId, kind: 'issued', phase: 'landing' })
        },
        onError: reportActionFailure,
        onSettled: closePendingAction,
      })
    },
    confirmDelete: () => {
      if (pendingAction === null) {
        return
      }
      deleteInvoice.mutate(pendingAction.invoice.id, {
        onError: reportActionFailure,
        onSettled: closePendingAction,
      })
    },
    editBlockedDraft: () => {
      if (pendingAction !== null) {
        void navigate(`/invoices/${pendingAction.invoice.id}`)
      }
    },

    markPaid: (invoiceId: string) => {
      setRowStamp({ invoiceId, kind: 'paid', phase: 'landing' })
      // mutate() callbacks fire only for the last call, and another row may be paid meanwhile
      markInvoicePaid.mutateAsync(invoiceId).catch((error: unknown) => {
        setRowStamp((stamp) => (stamp?.invoiceId === invoiceId ? null : stamp))
        reportActionFailure(error)
      })
    },

    // a stamp or chip still animating reports completion after another row's stamp has taken over
    settleStamp: (invoiceId: string) => {
      setRowStamp((stamp) => (stamp?.invoiceId === invoiceId ? { ...stamp, phase: 'settling' } : stamp))
    },
    clearStamp: (invoiceId: string) => {
      setRowStamp((stamp) => (stamp?.invoiceId === invoiceId ? null : stamp))
    },
  }
}

export type InvoiceListActions = ReturnType<typeof useInvoiceListActions>
