import AddRoundedIcon from '@mui/icons-material/AddRounded'
import Alert from '@mui/material/Alert'
import Button from '@mui/material/Button'
import LinearProgress from '@mui/material/LinearProgress'
import Stack from '@mui/material/Stack'
import TablePagination from '@mui/material/TablePagination'
import Typography from '@mui/material/Typography'
import { useQuery } from '@tanstack/react-query'
import { Link as RouterLink, useSearchParams } from 'react-router'
import { describeFailure } from '@/api/client/apiError'
import { invoiceListQueryOptions } from '@/api/invoice/invoiceQueries'
import { InvoiceActionDialogs } from '@/components/invoice/list/InvoiceActionDialogs'
import { InvoiceFilterBar } from '@/components/invoice/list/InvoiceFilterBar'
import { InvoiceTable } from '@/components/invoice/list/InvoiceTable'
import { useInvoiceListActions } from '@/hooks/invoice/useInvoiceListActions'
import { useDebouncedValue } from '@/hooks/useDebouncedValue'
import {
  INVOICE_PAGE_SIZE,
  isFilterActive,
  readFilterFromSearchParams,
  writeFilterToSearchParams,
  type InvoiceListFilter,
  type InvoiceSortColumn,
} from '@/domain/invoice/list/invoiceListFilter'

const CLIENT_NAME_QUERY_DELAY_MILLISECONDS = 300

export function InvoiceListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const filter = readFilterFromSearchParams(searchParams)
  const queriedClientName = useDebouncedValue(filter.clientName, CLIENT_NAME_QUERY_DELAY_MILLISECONDS)
  const queriedFilter = { ...filter, clientName: queriedClientName }
  const invoiceListQuery = useQuery(invoiceListQueryOptions(queriedFilter))
  const actions = useInvoiceListActions(queriedFilter, invoiceListQuery.data?.member)

  const applyFilter = (nextFilter: InvoiceListFilter) => {
    setSearchParams(writeFilterToSearchParams(nextFilter))
  }

  const applyFilterBarChange = (nextFilter: InvoiceListFilter) => {
    setSearchParams(writeFilterToSearchParams(nextFilter), { replace: true, flushSync: true })
  }

  const sortByColumn = (column: InvoiceSortColumn) => {
    if (filter.sortBy === column && filter.sortDirection === 'desc') {
      applyFilter({ ...filter, sortBy: '', sortDirection: 'asc', page: 1 })

      return
    }

    applyFilter({
      ...filter,
      sortBy: column,
      sortDirection: filter.sortBy === column ? 'desc' : 'asc',
      page: 1,
    })
  }

  const invoices = actions.invoices
  const totalItems = invoiceListQuery.data?.totalItems ?? 0
  const lastPage = Math.max(1, Math.ceil(totalItems / INVOICE_PAGE_SIZE))
  const isPastLastPage = filter.page > lastPage

  return (
    <>
      <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'center', marginBlockEnd: 2 }}>
        <Typography variant="h1">Seznam faktur</Typography>
        <Button variant="contained" component={RouterLink} to="/invoices/new" startIcon={<AddRoundedIcon />}>
          Nová faktura
        </Button>
      </Stack>

      <InvoiceFilterBar filter={filter} onFilterChange={applyFilterBarChange} />

      {invoiceListQuery.error !== null && (
        <Alert severity="error" sx={{ marginBlockEnd: 2 }}>
          {describeFailure(invoiceListQuery.error, 'Seznam faktur se nepodařilo načíst.')}
        </Alert>
      )}

      {invoiceListQuery.isLoading && <LinearProgress />}
      {!invoiceListQuery.isLoading && invoiceListQuery.isFetching && <LinearProgress sx={{ height: 2 }} />}

      {invoices?.length === 0 && !isPastLastPage && (
        <Typography sx={{ paddingBlock: 4 }} color="text.secondary">
          {isFilterActive(filter) ? 'Žádná faktura neodpovídá zadanému filtru.' : 'Zatím tu není žádná faktura.'}
        </Typography>
      )}

      {invoices?.length === 0 && isPastLastPage && (
        <Stack direction="row" spacing={2} sx={{ paddingBlock: 4, alignItems: 'center' }}>
          <Typography color="text.secondary">Na této stránce už žádná faktura není.</Typography>
          <Button
            variant="outlined"
            onClick={() => {
              applyFilter({ ...filter, page: lastPage })
            }}
          >
            Přejít na poslední stránku
          </Button>
        </Stack>
      )}

      {invoices !== undefined && invoices.length > 0 && (
        <InvoiceTable invoices={invoices} filter={filter} actions={actions} onSort={sortByColumn} />
      )}

      {totalItems > 0 && !isPastLastPage && (
        <TablePagination
          component="div"
          count={totalItems}
          page={filter.page - 1}
          onPageChange={(_event, nextPage) => {
            applyFilter({ ...filter, page: nextPage + 1 })
          }}
          rowsPerPage={INVOICE_PAGE_SIZE}
          rowsPerPageOptions={[INVOICE_PAGE_SIZE]}
        />
      )}

      <InvoiceActionDialogs actions={actions} />
    </>
  )
}
