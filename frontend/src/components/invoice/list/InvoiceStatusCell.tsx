import Box from '@mui/material/Box'
import TableCell from '@mui/material/TableCell'
import { motion } from 'motion/react'
import type { InvoiceRow } from '@/api/schema/types'
import type { RowStamp } from '@/hooks/invoice/useInvoiceListActions'
import type { InvoiceStampKind } from '@/config/theme'
import { InvoiceStamp } from '@/components/invoice/status/InvoiceStamp'
import { InvoiceStatusChip } from '@/components/invoice/status/InvoiceStatusChip'

type InvoiceStatusCellProps = {
  invoice: InvoiceRow
  stamp: RowStamp | null
  onStampLanded: () => void
  onStampSettled: () => void
}

function buildStampLayoutId(invoiceId: string, kind: InvoiceStampKind): string {
  return `invoice-status-${invoiceId}-${kind}`
}

export function InvoiceStatusCell({ invoice, stamp, onStampLanded, onStampSettled }: InvoiceStatusCellProps) {
  if (stamp?.phase === 'landing') {
    return (
      <TableCell sx={{ position: 'relative' }}>
        {/* holds the chip's space, or the row shrinks while the stamp is in the air */}
        <Box aria-hidden sx={{ visibility: 'hidden', display: 'inline-flex' }}>
          <InvoiceStatusChip invoice={invoice} />
        </Box>
        <Box
          aria-hidden
          sx={{ position: 'absolute', insetBlockStart: '50%', translate: '0 -50%', zIndex: 1 }}
        >
          <InvoiceStamp
            kind={stamp.kind}
            layoutId={buildStampLayoutId(invoice.id, stamp.kind)}
            onLanded={onStampLanded}
          />
        </Box>
      </TableCell>
    )
  }

  return (
    <TableCell>
      <motion.span
        layoutId={stamp === null ? undefined : buildStampLayoutId(invoice.id, stamp.kind)}
        onLayoutAnimationComplete={onStampSettled}
        style={{ display: 'inline-flex' }}
      >
        <InvoiceStatusChip invoice={invoice} />
      </motion.span>
    </TableCell>
  )
}
