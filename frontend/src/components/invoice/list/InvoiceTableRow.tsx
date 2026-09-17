import Link from '@mui/material/Link'
import TableCell from '@mui/material/TableCell'
import TableRow from '@mui/material/TableRow'
import { Link as RouterLink } from 'react-router'
import type { InvoiceRow } from '@/api/schema/types'
import { formatMinorUnits } from '@/domain/pricing/amount'
import { formatDate } from '@/domain/invoice/formatDate'
import type { InvoiceListActions } from '@/hooks/invoice/useInvoiceListActions'
import { InvoiceRowActions } from './InvoiceRowActions'
import { InvoiceStatusCell } from './InvoiceStatusCell'

type InvoiceTableRowProps = {
  invoice: InvoiceRow
  actions: InvoiceListActions
}

export function InvoiceTableRow({ invoice, actions }: InvoiceTableRowProps) {
  const stamp = actions.rowStamp?.invoiceId === invoice.id ? actions.rowStamp : null

  return (
    <TableRow hover>
      <TableCell>
        {invoice.number === null ? (
          '—'
        ) : (
          <Link component={RouterLink} to={`/invoices/${invoice.id}`}>
            {invoice.number}
          </Link>
        )}
      </TableCell>
      <TableCell sx={{ '&&': { whiteSpace: 'normal' } }}>{invoice.clientName}</TableCell>
      <TableCell>{formatDate(invoice.issuedAt)}</TableCell>
      <TableCell>{formatDate(invoice.dueAt)}</TableCell>
      <TableCell>{formatDate(invoice.paidAt)}</TableCell>
      <TableCell align="right">{formatMinorUnits(invoice.totalGrossAmount)}</TableCell>
      <InvoiceStatusCell
        invoice={invoice}
        stamp={stamp}
        onStampLanded={() => {
          actions.settleStamp(invoice.id)
        }}
        onStampSettled={() => {
          actions.clearStamp(invoice.id)
        }}
      />
      <InvoiceRowActions
        invoice={invoice}
        isHiddenByStamp={stamp?.phase === 'landing'}
        onIssue={() => {
          actions.requestIssue(invoice)
        }}
        onDelete={() => {
          actions.requestDelete(invoice)
        }}
        onMarkPaid={() => {
          actions.markPaid(invoice.id)
        }}
      />
    </TableRow>
  )
}
