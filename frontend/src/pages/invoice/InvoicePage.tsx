import Alert from '@mui/material/Alert'
import Button from '@mui/material/Button'
import LinearProgress from '@mui/material/LinearProgress'
import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { Link as RouterLink, useLocation, useParams } from 'react-router'
import { enqueueSnackbar } from 'notistack'
import { ApiError, describeFailure } from '@/api/client/apiError'
import { InvoiceDocument } from '@/components/invoice/document/InvoiceDocument'
import { InvoiceForm } from '@/components/invoice/form/InvoiceForm'
import { EMPTY_HEADER_VALUES, toInvoiceHeaderValues, toInvoiceItemDrafts } from '@/domain/invoice/form/invoiceFormValues'
import { invoiceDetailQueryOptions } from '@/api/invoice/invoiceQueries'
import { wasInvoiceJustIssued } from '@/domain/invoice/invoiceNavigationState'
import { useDownloadInvoicePdf } from '@/api/invoice/useDownloadInvoicePdf'
import { useMarkInvoicePaid } from '@/api/invoice/useMarkInvoicePaid'

export function InvoicePage() {
  const { invoiceId } = useParams()
  const location = useLocation()
  const invoiceQuery = useQuery(invoiceDetailQueryOptions(invoiceId))
  const markInvoicePaid = useMarkInvoicePaid()
  const downloadInvoicePdf = useDownloadInvoicePdf()
  const [shownDraftId, setShownDraftId] = useState<string | null>(null)

  if (invoiceQuery.data?.status === 'draft' && invoiceQuery.data.id !== shownDraftId) {
    setShownDraftId(invoiceQuery.data.id)
  }

  if (invoiceQuery.isLoading) {
    return <LinearProgress />
  }

  // a failed background refetch keeps the data, and must not take a draft being edited off the screen
  if (invoiceQuery.isError && invoiceQuery.data === undefined) {
    return (
      <>
        <Alert severity="error" sx={{ marginBlockEnd: 2 }}>
          {invoiceQuery.error instanceof ApiError && invoiceQuery.error.status === 404
            ? 'Faktura neexistuje, nebo byla smazána.' : 'Fakturu se nepodařilo načíst.'}
        </Alert>
        <Button component={RouterLink} to="/">
          Zpět na seznam
        </Button>
      </>
    )
  }

  const invoice = invoiceQuery.data

  if (invoice !== undefined && invoice.status === 'issued') {
    return (
      <InvoiceDocument
        invoice={invoice}
        isMarkingPaid={markInvoicePaid.isPending}
        isDownloadingPdf={downloadInvoicePdf.isPending}
        animateIssuedStamp={wasInvoiceJustIssued(location.state) || invoice.id === shownDraftId}
        animatePaidStamp={!markInvoicePaid.isIdle}
        onMarkPaid={() => {
          markInvoicePaid.mutate(invoice.id, {
            onError: (error) => {
              enqueueSnackbar(describeFailure(error, 'Akci se nepodařilo dokončit.'), { variant: 'error' })
            },
          })
        }}
        onDownloadPdf={() => {
          downloadInvoicePdf.mutate(invoice.id, {
            onError: (error) => {
              enqueueSnackbar(describeFailure(error, 'PDF se nepodařilo stáhnout.'), { variant: 'error' })
            },
          })
        }}
      />
    )
  }

  return (
    <InvoiceForm
      key={invoiceId ?? 'new'}
      invoiceId={invoiceId}
      heading={invoice === undefined ? 'Nová faktura · koncept' : 'Faktura · koncept'}
      initialValues={invoice === undefined ? EMPTY_HEADER_VALUES : toInvoiceHeaderValues(invoice)}
      initialItems={toInvoiceItemDrafts(invoice?.items ?? [])}
    />
  )
}
