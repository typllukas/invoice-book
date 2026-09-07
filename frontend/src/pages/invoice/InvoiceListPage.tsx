import Alert from '@mui/material/Alert'
import LinearProgress from '@mui/material/LinearProgress'
import Typography from '@mui/material/Typography'
import { useQuery } from '@tanstack/react-query'
import { describeFailure } from '@/api/client/apiError'
import { invoiceListQueryOptions } from '@/api/invoice/invoiceQueries'
import { InvoiceTable } from '@/components/invoice/list/InvoiceTable'

export function InvoiceListPage() {
  const invoiceListQuery = useQuery(invoiceListQueryOptions())
  const invoices = invoiceListQuery.data?.member

  return (
    <>
      <Typography variant="h1" sx={{ marginBlockEnd: 2 }}>
        Seznam faktur
      </Typography>

      {invoiceListQuery.error !== null && (
        <Alert severity="error" sx={{ marginBlockEnd: 2 }}>
          {describeFailure(invoiceListQuery.error, 'Seznam faktur se nepodařilo načíst.')}
        </Alert>
      )}

      {invoiceListQuery.isLoading && <LinearProgress />}

      {invoices?.length === 0 && (
        <Typography sx={{ paddingBlock: 4 }} color="text.secondary">
          Zatím tu není žádná faktura.
        </Typography>
      )}

      {invoices !== undefined && invoices.length > 0 && <InvoiceTable invoices={invoices} />}
    </>
  )
}
