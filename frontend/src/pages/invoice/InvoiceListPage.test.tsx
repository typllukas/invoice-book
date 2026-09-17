import { screen, waitFor, within } from '@testing-library/react'
import { userEvent } from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { createMemoryRouter } from 'react-router'
import { expect, test } from 'vitest'
import type { InvoiceCollectionResponse } from '@/api/schema/types'
import { draftInvoiceRow, issuedUnpaidInvoiceRow, serveInvoiceRows } from '@/test/invoiceApiFakes'
import { mswServer } from '@/test/mswServer'
import { renderRouterWithProviders, renderWithProviders } from '@/test/renderWithProviders'
import { InvoiceListPage } from './InvoiceListPage'

test('each invoice the API returns is a row with its number, client and total', async () => {
  mswServer.use(serveInvoiceRows([issuedUnpaidInvoiceRow]))

  renderWithProviders(<InvoiceListPage />)

  expect(await screen.findByText('Novák a syn s.r.o.')).toBeInTheDocument()
  expect(screen.getByText('2026-000142')).toBeInTheDocument()
  expect(screen.getByText('48 400,00 Kč')).toBeInTheDocument()
  expect(screen.getByText('Po splatnosti')).toBeInTheDocument()
})

test('a draft has no number and opens for editing from its first action', async () => {
  mswServer.use(serveInvoiceRows([draftInvoiceRow]))

  renderWithProviders(<InvoiceListPage />)

  const editLink = await screen.findByRole('link', { name: 'Upravit koncept pro Novák a syn s.r.o.' })
  const actionsCell = within(screen.getByRole('row', { name: /Novák a syn s\.r\.o\./u })).getAllByRole('cell').at(-1)
  expect(actionsCell?.querySelector('a, button')).toBe(editLink)
  const [numberCell] = within(screen.getByRole('row', { name: /Novák a syn s\.r\.o\./u })).getAllByRole('cell')
  expect(numberCell).toHaveTextContent(/^—$/u)
  const linksToDraft = screen
    .getAllByRole('link')
    .filter((link) => link.getAttribute('href') === '/invoices/01K4A000000000000000000143')
  expect(linksToDraft).toEqual([editLink])
})

test('an issued invoice opens from its number and from the view action first in the row', async () => {
  mswServer.use(serveInvoiceRows([issuedUnpaidInvoiceRow]))

  renderWithProviders(<InvoiceListPage />)

  const viewLink = await screen.findByRole('link', { name: `Zobrazit fakturu ${issuedUnpaidInvoiceRow.number}` })
  const actionsCell = within(screen.getByRole('row', { name: /Novák a syn s\.r\.o\./u })).getAllByRole('cell').at(-1)
  expect(actionsCell?.querySelector('a, button')).toBe(viewLink)
  expect(viewLink).toHaveAttribute('href', screen.getByRole('link', { name: issuedUnpaidInvoiceRow.number }).getAttribute('href'))
})

test('typing a client name asks the server once, for the whole name', async () => {
  const user = userEvent.setup()
  const requestedUrls: string[] = []
  mswServer.use(serveInvoiceRows([issuedUnpaidInvoiceRow], requestedUrls))

  renderWithProviders(<InvoiceListPage />)
  await screen.findByText('Novák a syn s.r.o.')

  await user.type(screen.getByLabelText('Odběratel'), 'novak')

  await waitFor(() => {
    expect(requestedUrls.some((url) => url.includes('clientName=novak'))).toBe(true)
  })
  expect(requestedUrls.filter((url) => url.includes('clientName='))).toHaveLength(1)
})

test('filtering overdue invoices asks for issued unpaid ones due before today', async () => {
  const user = userEvent.setup()
  const requestedUrls: string[] = []
  mswServer.use(serveInvoiceRows([issuedUnpaidInvoiceRow], requestedUrls))

  renderWithProviders(<InvoiceListPage />)
  await screen.findByText('Novák a syn s.r.o.')

  await user.click(screen.getByRole('combobox', { name: 'Stav' }))
  await user.click(screen.getByRole('option', { name: 'Po splatnosti' }))

  await waitFor(() => {
    expect(requestedUrls).toContainEqual(
      expect.stringMatching(/status=issued&exists\[paidAt\]=false&dueAt\[strictly_before\]=\d{4}-\d{2}-\d{2}/u),
    )
  })
})

test('filtering unpaid invoices leaves out the overdue ones, as the row chips do', async () => {
  const user = userEvent.setup()
  const requestedUrls: string[] = []
  mswServer.use(serveInvoiceRows([issuedUnpaidInvoiceRow], requestedUrls))

  renderWithProviders(<InvoiceListPage />)
  await screen.findByText('Novák a syn s.r.o.')

  await user.click(screen.getByRole('combobox', { name: 'Stav' }))
  await user.click(screen.getByRole('option', { name: 'Neuhrazeno' }))

  await waitFor(() => {
    expect(requestedUrls).toContainEqual(
      expect.stringMatching(/status=issued&exists\[paidAt\]=false&dueAt\[after\]=\d{4}-\d{2}-\d{2}/u),
    )
  })
})

test('typing a filter replaces the history entry instead of adding one per keystroke', async () => {
  const user = userEvent.setup()
  mswServer.use(serveInvoiceRows([issuedUnpaidInvoiceRow]))
  const router = createMemoryRouter([{ path: '/', element: <InvoiceListPage /> }], {
    initialEntries: ['/'],
  })

  renderRouterWithProviders(router)
  await screen.findByText('Novák a syn s.r.o.')
  await user.type(screen.getByLabelText('Odběratel'), 'novak')

  expect(router.state.location.search).toBe('?clientName=novak')
  expect(router.state.historyAction).toBe('REPLACE')
})

test('a third click on a column header drops the order', async () => {
  const user = userEvent.setup()
  const requestedUrls: string[] = []
  mswServer.use(serveInvoiceRows([issuedUnpaidInvoiceRow], requestedUrls))

  renderWithProviders(<InvoiceListPage />, '/?sort=clientName&dir=desc')
  await screen.findByText('Novák a syn s.r.o.')

  await user.click(screen.getByRole('button', { name: 'Odběratel' }))
  await waitFor(() => {
    expect(requestedUrls.at(-1)).not.toContain('order[')
  })
})

test('clicking a column header orders on the server and turns around on a second click', async () => {
  const user = userEvent.setup()
  const requestedUrls: string[] = []
  mswServer.use(serveInvoiceRows([issuedUnpaidInvoiceRow], requestedUrls))

  renderWithProviders(<InvoiceListPage />)
  await screen.findByText('Novák a syn s.r.o.')

  await user.click(screen.getByRole('button', { name: 'Odběratel' }))
  await waitFor(() => {
    expect(requestedUrls.at(-1)).toContain('order[clientName]=asc')
  })

  await user.click(screen.getByRole('button', { name: 'Odběratel' }))
  await waitFor(() => {
    expect(requestedUrls.at(-1)).toContain('order[clientName]=desc')
  })
})

test('Zrušit filtry empties the filter and keeps the order the list is sorted in', async () => {
  const user = userEvent.setup()
  const requestedUrls: string[] = []
  mswServer.use(serveInvoiceRows([issuedUnpaidInvoiceRow], requestedUrls))

  renderWithProviders(<InvoiceListPage />, '/?clientName=novak&sort=clientName&dir=desc')
  await user.click(await screen.findByRole('button', { name: 'Zrušit filtry' }))

  expect(screen.getByLabelText('Odběratel')).toHaveValue('')
  await waitFor(() => {
    expect(requestedUrls.at(-1)).not.toContain('clientName=')
  })
  expect(requestedUrls.at(-1)).toContain('order[clientName]=desc')
})

test('a page past the last one offers the way to the last page instead of a pagination that miscounts', async () => {
  const user = userEvent.setup()
  const requestedPages: (string | null)[] = []
  mswServer.use(
    http.get('/api/invoices', ({ request }) => {
      const requestedPage = new URL(request.url).searchParams.get('page')
      requestedPages.push(requestedPage)

      return HttpResponse.json({
        member: requestedPage === '2' ? [issuedUnpaidInvoiceRow] : [],
        totalItems: 31,
      } satisfies InvoiceCollectionResponse)
    }),
  )

  renderWithProviders(<InvoiceListPage />, '/?page=5')
  expect(await screen.findByText('Na této stránce už žádná faktura není.')).toBeInTheDocument()
  expect(screen.queryByText(/z 31/u)).not.toBeInTheDocument()

  await user.click(screen.getByRole('button', { name: 'Přejít na poslední stránku' }))

  expect(await screen.findByText('2026-000142')).toBeInTheDocument()
  expect(screen.getByText('31–31 z 31')).toBeInTheDocument()
  expect(requestedPages).toEqual(['5', '2'])
})

test('a response that does not match the contract fails loudly instead of rendering', async () => {
  const rowMissingItsTotal = { ...issuedUnpaidInvoiceRow, totalGrossAmount: undefined }
  mswServer.use(http.get('/api/invoices', () => HttpResponse.json({ member: [rowMissingItsTotal], totalItems: 1 })))

  renderWithProviders(<InvoiceListPage />)

  expect(await screen.findByText('Seznam faktur se nepodařilo načíst.')).toBeInTheDocument()
  expect(screen.queryByText('Novák a syn s.r.o.')).not.toBeInTheDocument()
})
