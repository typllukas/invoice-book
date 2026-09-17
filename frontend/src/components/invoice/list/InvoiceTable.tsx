import Paper from '@mui/material/Paper'
import Table from '@mui/material/Table'
import TableBody from '@mui/material/TableBody'
import TableCell from '@mui/material/TableCell'
import TableContainer from '@mui/material/TableContainer'
import TableHead from '@mui/material/TableHead'
import TableRow from '@mui/material/TableRow'
import type { InvoiceRow } from '@/api/schema/types'
import type { InvoiceListActions } from '@/hooks/invoice/useInvoiceListActions'
import type { InvoiceListFilter, InvoiceSortColumn } from '@/domain/invoice/list/invoiceListFilter'
import { InvoiceTableRow } from './InvoiceTableRow'
import { SortableColumnHead } from './SortableColumnHead'

const WRAPPED_HEAD = { '&&': { whiteSpace: 'normal', lineHeight: 1.3 } } as const

// widths of the widest content, so a row changing its contents does not shift every column
const COLUMN_WIDTHS = {
  number: 105,
  issuedAt: 95,
  dueAt: 105,
  paidAt: 95,
  totalGrossAmount: 115,
  status: 120,
  actions: 285,
} as const

const FIXED_COLUMNS_WIDTH = Object.values(COLUMN_WIDTHS).reduce((sum, width) => sum + width, 0)

const MINIMUM_CLIENT_NAME_WIDTH = 180

type InvoiceTableProps = {
  invoices: readonly InvoiceRow[]
  filter: InvoiceListFilter
  actions: InvoiceListActions
  onSort: (column: InvoiceSortColumn) => void
}

export function InvoiceTable({ invoices, filter, actions, onSort }: InvoiceTableProps) {
  return (
    <TableContainer component={Paper}>
      <Table
        sx={{
          tableLayout: 'fixed',
          minWidth: FIXED_COLUMNS_WIDTH + MINIMUM_CLIENT_NAME_WIDTH,
          '& th, & td': { whiteSpace: 'nowrap' },
        }}
      >
        <TableHead>
          <TableRow>
            <SortableColumnHead column="number" label="Číslo" filter={filter} onSort={onSort} sx={{ width: COLUMN_WIDTHS.number }} />
            <SortableColumnHead column="clientName" label="Odběratel" filter={filter} onSort={onSort} />
            <SortableColumnHead
              column="issuedAt"
              label="Datum vystavení"
              filter={filter}
              onSort={onSort}
              sx={{ ...WRAPPED_HEAD, width: COLUMN_WIDTHS.issuedAt }}
            />
            <SortableColumnHead
              column="dueAt"
              label="Datum splatnosti"
              filter={filter}
              onSort={onSort}
              sx={{ ...WRAPPED_HEAD, width: COLUMN_WIDTHS.dueAt }}
            />
            <SortableColumnHead
              column="paidAt"
              label="Datum úhrady"
              filter={filter}
              onSort={onSort}
              sx={{ ...WRAPPED_HEAD, width: COLUMN_WIDTHS.paidAt }}
            />
            <TableCell align="right" sx={{ ...WRAPPED_HEAD, width: COLUMN_WIDTHS.totalGrossAmount }}>
              Celkem s DPH
            </TableCell>
            <SortableColumnHead column="status" label="Stav" filter={filter} onSort={onSort} sx={{ width: COLUMN_WIDTHS.status }} />
            <TableCell sx={{ width: COLUMN_WIDTHS.actions }} aria-label="Akce" />
          </TableRow>
        </TableHead>
        <TableBody>
          {invoices.map((invoice) => (
            <InvoiceTableRow key={invoice.id} invoice={invoice} actions={actions} />
          ))}
        </TableBody>
      </Table>
    </TableContainer>
  )
}
