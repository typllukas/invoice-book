import FilterAltOffRoundedIcon from '@mui/icons-material/FilterAltOffRounded'
import Button from '@mui/material/Button'
import MenuItem from '@mui/material/MenuItem'
import Stack from '@mui/material/Stack'
import TextField from '@mui/material/TextField'
import { CalendarDateField } from '@/components/common/CalendarDateField'
import {
  EMPTY_INVOICE_LIST_FILTER,
  isFilterActive,
  parseStatusFilter,
  STATUS_FILTER_VALUES,
  type InvoiceListFilter,
  type InvoiceStatusFilter,
} from '@/domain/invoice/list/invoiceListFilter'
import { INVOICE_STATUS_TONE_LABELS } from '@/components/invoice/status/invoiceStatusLabels'

const STATUS_FILTER_LABELS: Readonly<Record<InvoiceStatusFilter, string>> = { '': 'Vše', ...INVOICE_STATUS_TONE_LABELS }

type InvoiceFilterBarProps = {
  filter: InvoiceListFilter
  onFilterChange: (filter: InvoiceListFilter) => void
}

export function InvoiceFilterBar({ filter, onFilterChange }: InvoiceFilterBarProps) {
  const changeFilter = (patch: Partial<InvoiceListFilter>) => {
    onFilterChange({ ...filter, ...patch, page: 1 })
  }

  return (
    <Stack
      direction="row"
      spacing={2}
      useFlexGap
      sx={{ flexWrap: 'wrap', alignItems: 'flex-start', marginBlockEnd: 2 }}
    >
      <TextField
        label="Odběratel"
        value={filter.clientName}
        onChange={(event) => {
          changeFilter({ clientName: event.target.value })
        }}
      />
      <TextField
        select
        label="Stav"
        value={filter.status}
        onChange={(event) => {
          changeFilter({ status: parseStatusFilter(event.target.value) })
        }}
        sx={{ minWidth: 140 }}
      >
        {STATUS_FILTER_VALUES.map((status) => (
          <MenuItem key={status} value={status}>
            {STATUS_FILTER_LABELS[status]}
          </MenuItem>
        ))}
      </TextField>
      <CalendarDateField
        label="Vystaveno od"
        value={filter.issuedFrom}
        onChange={(issuedFrom) => {
          changeFilter({ issuedFrom })
        }}
      />
      <CalendarDateField
        label="Vystaveno do"
        value={filter.issuedTo}
        onChange={(issuedTo) => {
          changeFilter({ issuedTo })
        }}
      />
      {isFilterActive(filter) && (
        <Button
          variant="outlined"
          startIcon={<FilterAltOffRoundedIcon />}
          sx={{ alignSelf: 'center' }}
          onClick={() => {
            onFilterChange({
              ...EMPTY_INVOICE_LIST_FILTER,
              sortBy: filter.sortBy,
              sortDirection: filter.sortDirection,
            })
          }}
        >
          Zrušit filtry
        </Button>
      )}
    </Stack>
  )
}
