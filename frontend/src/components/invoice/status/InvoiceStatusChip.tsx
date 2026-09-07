import Box from '@mui/material/Box'
import type { InvoiceRow } from '@/api/schema/types'
import { readTodayInCalendarZone } from '@/domain/invoice/formatDate'
import { isOverdue } from '@/domain/invoice/isOverdue'
import type { InvoiceStatusTone } from '@/domain/invoice/invoiceStatusTone'
import { INVOICE_STATUS_TONE_LABELS } from './invoiceStatusLabels'

function resolveTone(invoice: InvoiceRow): InvoiceStatusTone {
  if (invoice.status === 'draft') {
    return 'draft'
  }

  if (invoice.paidAt !== null) {
    return 'paid'
  }

  return isOverdue(invoice, readTodayInCalendarZone()) ? 'overdue' : 'unpaid'
}

export function InvoiceStatusChip({ invoice }: { invoice: InvoiceRow }) {
  const tone = resolveTone(invoice)

  return (
    <Box
      component="span"
      sx={{
        alignItems: 'center',
        backgroundColor: `invoiceStatus.${tone}.tint`,
        borderRadius: 999,
        color: `invoiceStatus.${tone}.ink`,
        display: 'inline-flex',
        fontSize: '0.75rem',
        fontWeight: 600,
        paddingBlock: '3px',
        paddingInline: '10px',
      }}
    >
      {INVOICE_STATUS_TONE_LABELS[tone]}
    </Box>
  )
}
