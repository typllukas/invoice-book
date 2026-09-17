import type { SxProps, Theme } from '@mui/material/styles'
import TableCell from '@mui/material/TableCell'
import TableSortLabel from '@mui/material/TableSortLabel'
import type { InvoiceListFilter, InvoiceSortColumn } from '@/domain/invoice/list/invoiceListFilter'

type SortableColumnHeadProps = {
  column: InvoiceSortColumn
  label: string
  filter: InvoiceListFilter
  onSort: (column: InvoiceSortColumn) => void
  sx?: SxProps<Theme>
}

export function SortableColumnHead({ column, label, filter, onSort, sx }: SortableColumnHeadProps) {
  const isSorted = filter.sortBy === column

  return (
    <TableCell sortDirection={isSorted ? filter.sortDirection : false} sx={sx}>
      <TableSortLabel
        active={isSorted}
        direction={isSorted ? filter.sortDirection : 'asc'}
        onClick={() => {
          onSort(column)
        }}
      >
        {label}
      </TableSortLabel>
    </TableCell>
  )
}
