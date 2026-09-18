import Table from '@mui/material/Table'
import TableBody from '@mui/material/TableBody'
import TableCell from '@mui/material/TableCell'
import TableHead from '@mui/material/TableHead'
import TableRow from '@mui/material/TableRow'
import { formatMinorUnits } from '@/domain/pricing/amount'
import { VAT_RATE_LABELS, type VatSummary } from '@/domain/pricing/vat'

export function VatSummaryTable({ summary }: { summary: VatSummary }) {
  return (
    <Table
      size="small"
      sx={{ maxWidth: 480, '& caption': { captionSide: 'top', padding: 0, typography: 'caption' } }}
    >
      <caption>Rekapitulace DPH</caption>
      <TableHead>
        <TableRow>
          <TableCell>Sazba</TableCell>
          <TableCell align="right">Základ daně</TableCell>
          <TableCell align="right">DPH</TableCell>
          <TableCell align="right">Celkem</TableCell>
        </TableRow>
      </TableHead>
      <TableBody>
        {summary.lines.map((summaryLine) => (
          <TableRow key={summaryLine.vatRate}>
            <TableCell>{VAT_RATE_LABELS[summaryLine.vatRate]}</TableCell>
            <TableCell align="right">{formatMinorUnits(summaryLine.netAmount)}</TableCell>
            <TableCell align="right">{formatMinorUnits(summaryLine.vatAmount)}</TableCell>
            <TableCell align="right">{formatMinorUnits(summaryLine.grossAmount)}</TableCell>
          </TableRow>
        ))}
        <TableRow>
          <TableCell>
            <strong>Celkem</strong>
          </TableCell>
          <TableCell align="right">{formatMinorUnits(summary.netAmount)}</TableCell>
          <TableCell align="right">{formatMinorUnits(summary.vatAmount)}</TableCell>
          <TableCell align="right">
            <strong>{formatMinorUnits(summary.grossAmount)}</strong>
          </TableCell>
        </TableRow>
      </TableBody>
    </Table>
  )
}
