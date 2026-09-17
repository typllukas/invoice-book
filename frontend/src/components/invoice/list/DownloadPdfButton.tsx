import DownloadRoundedIcon from '@mui/icons-material/DownloadRounded'
import IconButton from '@mui/material/IconButton'
import Tooltip from '@mui/material/Tooltip'
import { enqueueSnackbar } from 'notistack'
import { describeFailure } from '@/api/client/apiError'
import { useDownloadInvoicePdf } from '@/api/invoice/useDownloadInvoicePdf'
import type { InvoiceRow } from '@/api/schema/types'

type DownloadPdfButtonProps = {
  invoice: InvoiceRow
}

export function DownloadPdfButton({ invoice }: DownloadPdfButtonProps) {
  const downloadInvoicePdf = useDownloadInvoicePdf()

  return (
    <Tooltip title="Stáhnout PDF">
      <IconButton
        color="primary"
        size="small"
        loading={downloadInvoicePdf.isPending}
        aria-label={`Stáhnout PDF faktury ${invoice.number ?? ''}`}
        onClick={() => {
          downloadInvoicePdf.mutate(invoice.id, {
            onError: (error) => {
              enqueueSnackbar(describeFailure(error, 'PDF se nepodařilo stáhnout.'), { variant: 'error' })
            },
          })
        }}
      >
        <DownloadRoundedIcon fontSize="small" />
      </IconButton>
    </Tooltip>
  )
}
