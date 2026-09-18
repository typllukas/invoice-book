import DownloadRoundedIcon from '@mui/icons-material/DownloadRounded'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Divider from '@mui/material/Divider'
import Stack from '@mui/material/Stack'
import Table from '@mui/material/Table'
import TableBody from '@mui/material/TableBody'
import TableCell from '@mui/material/TableCell'
import TableHead from '@mui/material/TableHead'
import TableRow from '@mui/material/TableRow'
import Typography from '@mui/material/Typography'
import type { ReactNode } from 'react'
import { Link as RouterLink } from 'react-router'
import type { InvoiceDetail } from '@/api/schema/types'
import { formatDate, formatMoment } from '@/domain/invoice/formatDate'
import { formatMinorUnits } from '@/domain/pricing/amount'
import { formatQuantity } from '@/domain/pricing/quantity'
import { EXEMPT_VAT_RATE, VAT_RATE_LABELS } from '@/domain/pricing/vat'
import { InvoiceBreadcrumbs } from '@/components/invoice/InvoiceBreadcrumbs'
import { InvoiceStamp } from '@/components/invoice/status/InvoiceStamp'
import { VatSummaryTable } from '@/components/invoice/VatSummaryTable'

type InvoiceDocumentProps = {
  invoice: InvoiceDetail
  isMarkingPaid: boolean
  isDownloadingPdf: boolean
  animateIssuedStamp: boolean
  animatePaidStamp: boolean
  onMarkPaid: () => void
  onDownloadPdf: () => void
}

function Field({ label, value }: { label: string; value: string | null }) {
  return (
    <Stack>
      <Typography variant="caption" color="text.secondary">
        {label}
      </Typography>
      <Typography variant="body2">{value === null || value === '' ? '—' : value}</Typography>
    </Stack>
  )
}

function Party({ title, children }: { title: string; children: ReactNode }) {
  return (
    <Stack sx={{ flexGrow: 1 }}>
      <Typography variant="caption" color="text.secondary">
        {title}
      </Typography>
      {children}
    </Stack>
  )
}

function PartyLine({ label = null, value }: { label?: string | null; value: string | null }) {
  if (value === null || value === '') {
    return null
  }

  return <Typography variant="body2">{label === null ? value : `${label} ${value}`}</Typography>
}

/**
 * Everything comes from the response: the supplier, the amounts and the recapitulation were frozen at
 * issue and must never be recomputed.
 */
export function InvoiceDocument({
  invoice,
  isMarkingPaid,
  isDownloadingPdf,
  animateIssuedStamp,
  animatePaidStamp,
  onMarkPaid,
  onDownloadPdf,
}: InvoiceDocumentProps) {
  return (
    <Box sx={{ position: 'relative' }}>
      <InvoiceBreadcrumbs heading={`Faktura ${invoice.number ?? ''}`} />

      <Stack sx={{ position: 'absolute', insetBlockStart: 4, insetInlineEnd: 8, alignItems: 'flex-end', gap: 1.5 }}>
        <InvoiceStamp kind="issued" caption={invoice.number} animateOnMount={animateIssuedStamp} />
        {invoice.paidAt !== null && (
          <Box sx={{ marginInlineEnd: 5 }}>
            <InvoiceStamp kind="paid" caption={formatDate(invoice.paidAt)} animateOnMount={animatePaidStamp} />
          </Box>
        )}
      </Stack>

      <Stack direction="row" spacing={4} sx={{ marginBlockEnd: 3 }}>
        <Party title="Dodavatel">
          <PartyLine value={invoice.supplierName} />
          <PartyLine value={invoice.supplierAddress} />
          <PartyLine label="IČO" value={invoice.supplierCompanyId} />
          <PartyLine label="DIČ" value={invoice.supplierVatId} />
          <PartyLine label="Bankovní účet" value={invoice.supplierBankAccount} />
          <PartyLine value={invoice.supplierRegisterEntry} />
        </Party>
        <Party title="Odběratel">
          <PartyLine value={invoice.clientName} />
          <PartyLine value={invoice.clientAddress} />
          <PartyLine label="IČO" value={invoice.clientCompanyId} />
          <PartyLine label="DIČ" value={invoice.clientVatId} />
        </Party>
      </Stack>

      <Stack direction="row" spacing={4} sx={{ marginBlockEnd: 3, flexWrap: 'wrap' }}>
        <Field label="Datum vystavení" value={formatDate(invoice.issuedAt)} />
        <Field label="DUZP" value={formatDate(invoice.taxPointAt)} />
        <Field label="Datum splatnosti" value={formatDate(invoice.dueAt)} />
        <Field label="Variabilní symbol" value={invoice.variableSymbol} />
        <Field label="Uhrazeno" value={formatMoment(invoice.paidAt)} />
      </Stack>

      <Divider sx={{ marginBlockEnd: 2 }} />

      <Table size="small">
        <TableHead>
          <TableRow>
            <TableCell>Položka</TableCell>
            <TableCell align="right">Množství</TableCell>
            <TableCell align="right">Cena za MJ bez DPH</TableCell>
            <TableCell align="right">Sazba DPH</TableCell>
            <TableCell align="right">Celkem bez DPH</TableCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {invoice.items.map((item) => (
            <TableRow key={item.id}>
              <TableCell>{item.description}</TableCell>
              <TableCell align="right">
                {formatQuantity(item.quantity)} {item.unit}
              </TableCell>
              <TableCell align="right">{formatMinorUnits(item.unitPriceNet)}</TableCell>
              <TableCell align="right">{VAT_RATE_LABELS[item.vatRate]}</TableCell>
              <TableCell align="right">{formatMinorUnits(item.netAmount)}</TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>

      {invoice.note !== null && invoice.note !== '' && (
        <Stack sx={{ marginBlockStart: 3 }}>
          <Typography variant="caption" color="text.secondary">
            Poznámka
          </Typography>
          <Typography variant="body2">{invoice.note}</Typography>
        </Stack>
      )}

      <Stack sx={{ marginBlockStart: 3, alignItems: 'flex-end' }}>
        <VatSummaryTable
          summary={{
            lines: invoice.vatSummary,
            netAmount: invoice.totalNetAmount,
            vatAmount: invoice.totalVatAmount,
            grossAmount: invoice.totalGrossAmount,
          }}
        />
        {invoice.items.some((item) => item.vatRate === EXEMPT_VAT_RATE) && (
          <Typography variant="body2" sx={{ marginBlockStart: 1 }}>
            Plnění je osvobozeno od daně.
          </Typography>
        )}
      </Stack>

      <Stack direction="row" spacing={2} sx={{ marginBlockStart: 3, justifyContent: 'space-between' }}>
        <Button component={RouterLink} to="/" variant="outlined">
          Zpět
        </Button>
        <Stack direction="row" spacing={2} sx={{ alignItems: 'center' }}>
          <Button
            variant="outlined"
            startIcon={<DownloadRoundedIcon />}
            loading={isDownloadingPdf}
            onClick={onDownloadPdf}
          >
            Stáhnout PDF
          </Button>
          {invoice.paidAt === null && (
            <Button variant="contained" loading={isMarkingPaid} onClick={onMarkPaid}>
              Označit jako uhrazenou
            </Button>
          )}
        </Stack>
      </Stack>
    </Box>
  )
}
