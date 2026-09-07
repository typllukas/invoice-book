import TableCell from '@mui/material/TableCell'
import TableRow from '@mui/material/TableRow'
import type { InvoiceRow } from '@/api/schema/types'
import { InvoiceStatusChip } from '@/components/invoice/status/InvoiceStatusChip'
import { formatMinorUnits } from '@/domain/pricing/amount'
import { formatDate } from '@/domain/invoice/formatDate'

export function InvoiceTableRow({ invoice }: { invoice: InvoiceRow }) {
  return (
    <TableRow hover>
      <TableCell>{invoice.number ?? '—'}</TableCell>
      <TableCell sx={{ whiteSpace: 'normal' }}>{invoice.clientName}</TableCell>
      <TableCell>{formatDate(invoice.issuedAt)}</TableCell>
      <TableCell>{formatDate(invoice.dueAt)}</TableCell>
      <TableCell>{formatDate(invoice.paidAt)}</TableCell>
      <TableCell align="right">{formatMinorUnits(invoice.totalGrossAmount)}</TableCell>
      <TableCell>
        <InvoiceStatusChip invoice={invoice} />
      </TableCell>
    </TableRow>
  )
}
