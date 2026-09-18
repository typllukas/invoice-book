import { screen, waitFor } from '@testing-library/react'
import { userEvent } from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { createMemoryRouter } from 'react-router'
import { expect, test, vi } from 'vitest'
import type { InvoiceCollectionResponse, InvoiceDetail, InvoicePdf } from '@/api/schema/types'
import { routes } from '@/router/routes'
import { draftInvoiceDetail, issuedInvoiceDetail, type ErrorResponse } from '@/test/invoiceApiFakes'
import { mswServer } from '@/test/mswServer'
import { renderRouterWithProviders } from '@/test/renderWithProviders'

function renderInvoicePage(invoiceId: string) {
  const router = createMemoryRouter(routes, { initialEntries: [`/invoices/${invoiceId}`] })
  renderRouterWithProviders(router)

  return router
}

test('a new invoice opens an empty form and loads nothing', async () => {
  const router = createMemoryRouter(routes, { initialEntries: ['/invoices/new'] })
  renderRouterWithProviders(router)

  expect(await screen.findByRole('heading', { level: 1, name: 'Nová faktura · koncept' })).toBeInTheDocument()
  expect(screen.getByLabelText(/^Odběratel/u)).toHaveValue('')
})

test.each([
  { failure: 'an invoice that does not exist', status: 404, message: 'Faktura neexistuje, nebo byla smazána.' },
  { failure: 'an invoice that fails to load', status: 500, message: 'Fakturu se nepodařilo načíst.' },
])('$failure shows the alert and no form to overwrite it with', async ({ status, message }) => {
  mswServer.use(
    http.get('/api/invoices/:invoiceId', () =>
      HttpResponse.json({ status, title: 'An error occurred', detail: 'Failure' } satisfies ErrorResponse, { status }),
    ),
  )

  renderInvoicePage(draftInvoiceDetail.id)

  expect(await screen.findByRole('alert')).toHaveTextContent(message)
  expect(screen.queryByRole('button', { name: 'Uložit' })).not.toBeInTheDocument()
  expect(screen.queryAllByRole('textbox')).toHaveLength(0)
})

test('saving an edited draft puts it back under its own id and stays on it', async () => {
  const user = userEvent.setup()
  const updatedPaths: string[] = []
  mswServer.use(
    http.get('/api/invoices/:invoiceId', () => HttpResponse.json(draftInvoiceDetail)),
    http.put('/api/invoices/:invoiceId', ({ request }) => {
      updatedPaths.push(new URL(request.url).pathname)

      return HttpResponse.json(draftInvoiceDetail)
    }),
    http.get('/api/invoices', () =>
      HttpResponse.json({ member: [], totalItems: 0 } satisfies InvoiceCollectionResponse),
    ),
  )

  const router = renderInvoicePage(draftInvoiceDetail.id)
  await user.type(await screen.findByLabelText('Poznámka'), 'Děkujeme.')
  await user.click(screen.getByRole('button', { name: 'Uložit' }))

  expect(await screen.findByText('Koncept faktury byl uložen.')).toBeInTheDocument()
  expect(updatedPaths).toEqual(['/api/invoices/01K0000000000000000000000A'])
  expect(router.state.location.pathname).toBe('/invoices/01K0000000000000000000000A')
})

test('a saved quantity opens as a person reads it, not as the API writes it', async () => {
  mswServer.use(
    http.get('/api/invoices/:invoiceId', () =>
      HttpResponse.json({
        ...draftInvoiceDetail,
        items: draftInvoiceDetail.items.map((item) => ({ ...item, quantity: '2.500' })),
      } satisfies InvoiceDetail),
    ),
  )

  renderInvoicePage(draftInvoiceDetail.id)

  expect(await screen.findByLabelText('Množství')).toHaveValue('2,5')
})

test('saving a draft refetches it, since the server recalculates what it shows', async () => {
  const user = userEvent.setup()
  const requestedDetailPaths: string[] = []
  mswServer.use(
    http.get('/api/invoices/:invoiceId', ({ request }) => {
      requestedDetailPaths.push(new URL(request.url).pathname)

      return HttpResponse.json(draftInvoiceDetail)
    }),
    http.put('/api/invoices/:invoiceId', () => HttpResponse.json(draftInvoiceDetail)),
    http.get('/api/invoices', () =>
      HttpResponse.json({ member: [], totalItems: 0 } satisfies InvoiceCollectionResponse),
    ),
  )

  renderInvoicePage(draftInvoiceDetail.id)
  await user.click(await screen.findByRole('button', { name: 'Uložit' }))

  await waitFor(() => {
    expect(requestedDetailPaths).toHaveLength(2)
  })
})

test('an issued invoice opens as the document, not the form', async () => {
  mswServer.use(http.get('/api/invoices/:invoiceId', () => HttpResponse.json(issuedInvoiceDetail)))

  renderInvoicePage(issuedInvoiceDetail.id)

  expect(await screen.findByRole('heading', { name: 'Faktura 2026-000007' })).toBeInTheDocument()
  expect(screen.queryByRole('button', { name: 'Uložit' })).not.toBeInTheDocument()
  expect(screen.queryAllByRole('textbox')).toHaveLength(0)
})

test('Stáhnout PDF downloads the rendered invoice under its own name', async () => {
  const user = userEvent.setup()
  const invoicePdf = {
    '@id': '/api/.well-known/genid/1',
    '@type': 'InvoicePdf',
    name: 'faktura-2026-000007.pdf',
    base64Content: 'JVBERi0xLjcK',
  } satisfies InvoicePdf
  mswServer.use(
    http.get('/api/invoices/:invoiceId', () => HttpResponse.json(issuedInvoiceDetail)),
    http.get('/api/invoices/:invoiceId/pdf', () => HttpResponse.json(invoicePdf)),
  )
  const clickedLinks: HTMLAnchorElement[] = []
  vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function recordClick(this: HTMLAnchorElement) {
    clickedLinks.push(this)
  })

  renderInvoicePage(issuedInvoiceDetail.id)
  await user.click(await screen.findByRole('button', { name: 'Stáhnout PDF' }))

  await waitFor(() => {
    expect(clickedLinks).toHaveLength(1)
  })
  expect(clickedLinks[0]?.download).toBe('faktura-2026-000007.pdf')
  expect(clickedLinks[0]?.href).toBe('data:application/pdf;base64,JVBERi0xLjcK')
})
