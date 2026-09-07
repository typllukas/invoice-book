import Paper from '@mui/material/Paper'
import Table from '@mui/material/Table'
import TableBody from '@mui/material/TableBody'
import TableCell from '@mui/material/TableCell'
import TableContainer from '@mui/material/TableContainer'
import TableHead from '@mui/material/TableHead'
import TableRow from '@mui/material/TableRow'
import type { InvoiceRow } from '@/api/schema/types'
import { InvoiceTableRow } from './InvoiceTableRow'

export function InvoiceTable({ invoices }: { invoices: readonly InvoiceRow[] }) {
  return (
    <TableContainer component={Paper}>
      <Table sx={{ '& th, & td': { whiteSpace: 'nowrap' } }}>
        <TableHead>
          <TableRow>
            <TableCell>Číslo</TableCell>
            {/* client names vary in length, the other columns do not */}
            <TableCell sx={{ width: '25%' }}>Odběratel</TableCell>
            <TableCell>Datum vystavení</TableCell>
            <TableCell>Datum splatnosti</TableCell>
            <TableCell>Datum úhrady</TableCell>
            <TableCell align="right">Celkem s DPH</TableCell>
            <TableCell>Stav</TableCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {invoices.map((invoice) => (
            <InvoiceTableRow key={invoice.id} invoice={invoice} />
          ))}
        </TableBody>
      </Table>
    </TableContainer>
  )
}
