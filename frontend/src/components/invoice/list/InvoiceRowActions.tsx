import DeleteOutlinedIcon from '@mui/icons-material/DeleteOutlined'
import EditOutlinedIcon from '@mui/icons-material/EditOutlined'
import LabelImportantRoundedIcon from '@mui/icons-material/LabelImportantRounded'
import MonetizationOnRoundedIcon from '@mui/icons-material/MonetizationOnRounded'
import FindInPageRoundedIcon from '@mui/icons-material/FindInPageRounded'
import Button from '@mui/material/Button'
import IconButton from '@mui/material/IconButton'
import Stack from '@mui/material/Stack'
import TableCell from '@mui/material/TableCell'
import Tooltip from '@mui/material/Tooltip'
import { motion } from 'motion/react'
import { Link as RouterLink } from 'react-router'
import type { InvoiceRow } from '@/api/schema/types'
import { DownloadPdfButton } from './DownloadPdfButton'

type InvoiceRowActionsProps = {
  invoice: InvoiceRow
  isHiddenByStamp: boolean
  onIssue: () => void
  onDelete: () => void
  onMarkPaid: () => void
}

export function InvoiceRowActions({
  invoice,
  isHiddenByStamp,
  onIssue,
  onDelete,
  onMarkPaid,
}: InvoiceRowActionsProps) {
  return (
    <TableCell sx={{ paddingInlineStart: 1.5 }}>
      {/* the next action appears as the stamp leaves for the chip */}
      <motion.div
        initial={false}
        animate={{ opacity: isHiddenByStamp ? 0 : 1 }}
        transition={{ duration: isHiddenByStamp ? 0 : 0.2, delay: isHiddenByStamp ? 0 : 0.1 }}
        style={{ pointerEvents: isHiddenByStamp ? 'none' : 'auto' }}
      >
        {/* as tall as a small button, so a paid row with icons only keeps its height */}
        <Stack direction="row" sx={{ alignItems: 'center', gap: 1, minHeight: 30.75 }}>
          {invoice.status === 'draft' ? (
            <>
              <Tooltip title="Upravit">
                <IconButton
                  color="primary"
                  size="small"
                  component={RouterLink}
                  to={`/invoices/${invoice.id}`}
                  aria-label={`Upravit koncept pro ${invoice.clientName}`}
                >
                  <EditOutlinedIcon fontSize="small" />
                </IconButton>
              </Tooltip>
              <Button variant="outlined" startIcon={<LabelImportantRoundedIcon />} onClick={onIssue}>
                Vystavit
              </Button>
              <Tooltip title="Smazat">
                <IconButton color="error" size="small" aria-label={`Smazat koncept pro ${invoice.clientName}`} onClick={onDelete}>
                  <DeleteOutlinedIcon fontSize="small" />
                </IconButton>
              </Tooltip>
            </>
          ) : (
            <Tooltip title="Zobrazit">
              <IconButton
                color="primary"
                size="small"
                component={RouterLink}
                to={`/invoices/${invoice.id}`}
                aria-label={`Zobrazit fakturu ${invoice.number ?? ''}`}
              >
                <FindInPageRoundedIcon fontSize="small" />
              </IconButton>
            </Tooltip>
          )}
          {invoice.status === 'issued' && invoice.paidAt === null && (
            <Button variant="outlined" startIcon={<MonetizationOnRoundedIcon />} onClick={onMarkPaid}>
              Označit jako uhrazenou
            </Button>
          )}
          {invoice.status === 'issued' && (
            <DownloadPdfButton invoice={invoice} />
          )}
        </Stack>
      </motion.div>
    </TableCell>
  )
}
