import { screen, waitFor, waitForElementToBeRemoved, within } from '@testing-library/react'
import { userEvent } from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { createMemoryRouter } from 'react-router'
import { expect, test, vi } from 'vitest'
import type { InvoiceDetail, InvoicePdf, InvoiceRow } from '@/api/schema/types'
import {
  createCollectionResponse,
  createInvoiceDetailResponse,
  draftInvoiceRow,
  emptyDraftInvoiceRow,
  issuedUnpaidInvoiceRow,
  serveInvoiceRows,
  type ConstraintViolationResponse,
} from '@/test/invoiceApiFakes'
import { mswServer } from '@/test/mswServer'
import { renderRouterWithProviders, renderWithProviders } from '@/test/renderWithProviders'
import { InvoiceListPage } from './InvoiceListPage'

const secondUnpaidInvoiceRow = {
  ...issuedUnpaidInvoiceRow,
  '@id': '/api/invoices/01K4A000000000000000000145',
  id: '01K4A000000000000000000145',
  number: '2026-000145',
} satisfies InvoiceRow

// the stamp lands before the list lets the row go
const STAMP_LIFETIME_MILLISECONDS = 3000

function respondWithPaidInvoice() {
  return HttpResponse.json({
    ...createInvoiceDetailResponse(issuedUnpaidInvoiceRow.id, 'issued'),
    paidAt: '2026-09-25T00:00:00+00:00',
  } satisfies InvoiceDetail)
}

function createHeldResponse() {
  let releaseResponse: () => void = () => undefined
  const released = new Promise<void>((resolve) => {
    releaseResponse = resolve
  })

  return {
    released,
    release: () => {
      releaseResponse()
    },
  }
}

test('issuing from the list asks first and issues only after the confirmation', async () => {
  const user = userEvent.setup()
  const issuedPaths: string[] = []
  mswServer.use(
    serveInvoiceRows([draftInvoiceRow]),
    http.post('/api/invoices/:invoiceId/issue', ({ request }) => {
      issuedPaths.push(new URL(request.url).pathname)

      return HttpResponse.json(createInvoiceDetailResponse(draftInvoiceRow.id, 'issued'))
    }),
  )

  renderWithProviders(<InvoiceListPage />)
  await user.click(await screen.findByRole('button', { name: 'Vystavit' }))
  const confirmation = await screen.findByRole('dialog', { name: 'Vystavit fakturu?' })

  expect(issuedPaths).toEqual([])

  await user.click(within(confirmation).getByRole('button', { name: 'Vystavit' }))

  await waitFor(() => {
    expect(issuedPaths).toEqual(['/api/invoices/01K4A000000000000000000143/issue'])
  })
})

test('Zrušit in the issue confirmation sends nothing', async () => {
  const user = userEvent.setup()
  const issuedPaths: string[] = []
  mswServer.use(
    serveInvoiceRows([draftInvoiceRow]),
    http.post('/api/invoices/:invoiceId/issue', ({ request }) => {
      issuedPaths.push(new URL(request.url).pathname)

      return HttpResponse.json(createInvoiceDetailResponse(draftInvoiceRow.id, 'issued'))
    }),
  )

  renderWithProviders(<InvoiceListPage />)
  await user.click(await screen.findByRole('button', { name: 'Vystavit' }))
  const confirmation = await screen.findByRole('dialog', { name: 'Vystavit fakturu?' })
  await user.click(within(confirmation).getByRole('button', { name: 'Zrušit' }))

  await waitForElementToBeRemoved(confirmation)
  expect(issuedPaths).toEqual([])
})

test('deleting a draft asks first and deletes only after the confirmation', async () => {
  const user = userEvent.setup()
  const deletedPaths: string[] = []
  mswServer.use(
    serveInvoiceRows([draftInvoiceRow]),
    http.delete('/api/invoices/:invoiceId', ({ request }) => {
      deletedPaths.push(new URL(request.url).pathname)

      return new HttpResponse(null, { status: 204 })
    }),
  )

  renderWithProviders(<InvoiceListPage />)
  await user.click(await screen.findByRole('button', { name: 'Smazat koncept pro Novák a syn s.r.o.' }))
  const confirmation = await screen.findByRole('dialog', { name: 'Smazat koncept?' })

  expect(deletedPaths).toEqual([])

  await user.click(within(confirmation).getByRole('button', { name: 'Smazat' }))

  await waitFor(() => {
    expect(deletedPaths).toEqual(['/api/invoices/01K4A000000000000000000143'])
  })
})

test('Zrušit in the delete confirmation sends nothing', async () => {
  const user = userEvent.setup()
  const deletedPaths: string[] = []
  mswServer.use(
    serveInvoiceRows([draftInvoiceRow]),
    http.delete('/api/invoices/:invoiceId', ({ request }) => {
      deletedPaths.push(new URL(request.url).pathname)

      return new HttpResponse(null, { status: 204 })
    }),
  )

  renderWithProviders(<InvoiceListPage />)
  await user.click(await screen.findByRole('button', { name: 'Smazat koncept pro Novák a syn s.r.o.' }))
  const confirmation = await screen.findByRole('dialog', { name: 'Smazat koncept?' })
  await user.click(within(confirmation).getByRole('button', { name: 'Zrušit' }))

  await waitForElementToBeRemoved(confirmation)
  expect(deletedPaths).toEqual([])
})

test('a delete the server accepts says so and shows no error', async () => {
  const user = userEvent.setup()
  mswServer.use(
    serveInvoiceRows([draftInvoiceRow]),
    http.delete('/api/invoices/:invoiceId', () => new HttpResponse(null, { status: 204 })),
  )

  renderWithProviders(<InvoiceListPage />)
  await user.click(await screen.findByRole('button', { name: 'Smazat koncept pro Novák a syn s.r.o.' }))
  const confirmation = await screen.findByRole('dialog', { name: 'Smazat koncept?' })
  await user.click(within(confirmation).getByRole('button', { name: 'Smazat' }))

  expect(await screen.findByText('Koncept faktury byl smazán.')).toBeInTheDocument()
  expect(screen.queryByText('Akci se nepodařilo dokončit.')).not.toBeInTheDocument()
})

test('a delete the server refuses says so', async () => {
  const user = userEvent.setup()
  mswServer.use(
    serveInvoiceRows([draftInvoiceRow]),
    http.delete('/api/invoices/:invoiceId', () => new HttpResponse(null, { status: 500 })),
  )

  renderWithProviders(<InvoiceListPage />)
  await user.click(await screen.findByRole('button', { name: 'Smazat koncept pro Novák a syn s.r.o.' }))
  await user.click(within(await screen.findByRole('dialog')).getByRole('button', { name: 'Smazat' }))

  expect(await screen.findByText('Akci se nepodařilo dokončit.')).toBeInTheDocument()
})

test('a draft with no items says why it cannot be issued and offers to edit it instead', async () => {
  const user = userEvent.setup()
  const issuedPaths: string[] = []
  mswServer.use(
    serveInvoiceRows([emptyDraftInvoiceRow]),
    http.post('/api/invoices/:invoiceId/issue', ({ request }) => {
      issuedPaths.push(new URL(request.url).pathname)

      return HttpResponse.json(createInvoiceDetailResponse(emptyDraftInvoiceRow.id, 'issued'))
    }),
  )
  const router = createMemoryRouter(
    [
      { path: '/', element: <InvoiceListPage /> },
      { path: '/invoices/:invoiceId', element: <p>Koncept</p> },
    ],
    { initialEntries: ['/'] },
  )

  renderRouterWithProviders(router)
  await user.click(await screen.findByRole('button', { name: 'Vystavit' }))
  const blockedDialog = await screen.findByRole('dialog', { name: 'Fakturu zatím nelze vystavit' })

  expect(within(blockedDialog).getByText('Faktura nemá žádnou položku. Doplňte ji v konceptu.')).toBeInTheDocument()
  expect(within(blockedDialog).queryByRole('button', { name: 'Vystavit' })).not.toBeInTheDocument()

  await user.click(within(blockedDialog).getByRole('button', { name: 'Upravit koncept' }))

  expect(router.state.location.pathname).toBe('/invoices/01K4A000000000000000000144')
  expect(issuedPaths).toEqual([])
})

test('a 422 on mark_paid takes the stamp off the row and shows the reason', async () => {
  const user = userEvent.setup()
  const markPaidResponse = createHeldResponse()
  mswServer.use(
    serveInvoiceRows([issuedUnpaidInvoiceRow]),
    http.post('/api/invoices/:invoiceId/mark_paid', async () => {
      await markPaidResponse.released

      return HttpResponse.json(
        { status: 422, detail: 'Faktura je už uhrazená.' } satisfies ConstraintViolationResponse,
        { status: 422 },
      )
    }),
  )

  renderWithProviders(<InvoiceListPage />, '/?status=unpaid')
  await user.click(await screen.findByRole('button', { name: 'Označit jako uhrazenou' }))
  expect(screen.getByRole('img', { name: 'Razítko Uhrazeno', hidden: true })).toBeInTheDocument()
  markPaidResponse.release()

  expect(await screen.findByText('Faktura je už uhrazená.')).toBeInTheDocument()
  expect(screen.queryByRole('img', { name: 'Razítko Uhrazeno', hidden: true })).not.toBeInTheDocument()
  expect(screen.getByText(issuedUnpaidInvoiceRow.number)).toBeInTheDocument()
})

test('paying a row under the unpaid filter says so and lets the refetch take the row only after its stamp has landed', async () => {
  const user = userEvent.setup()
  let isPaid = false
  mswServer.use(
    http.get('/api/invoices', () =>
      createCollectionResponse(isPaid ? [secondUnpaidInvoiceRow] : [issuedUnpaidInvoiceRow, secondUnpaidInvoiceRow]),
    ),
    http.post('/api/invoices/:invoiceId/mark_paid', () => {
      isPaid = true

      return respondWithPaidInvoice()
    }),
  )

  renderWithProviders(<InvoiceListPage />, '/?status=unpaid')
  await screen.findByText('1–2 z 2')
  const firstRow = screen.getByRole('row', { name: new RegExp(issuedUnpaidInvoiceRow.number, 'u') })
  await user.click(within(firstRow).getByRole('button', { name: 'Označit jako uhrazenou' }))

  expect(await screen.findByText('Faktura byla označena jako uhrazená.')).toBeInTheDocument()
  expect(within(firstRow).getByRole('img', { name: 'Razítko Uhrazeno', hidden: true })).toBeInTheDocument()
  await waitForElementToBeRemoved(firstRow, { timeout: STAMP_LIFETIME_MILLISECONDS })
  expect(screen.getByText(secondUnpaidInvoiceRow.number)).toBeInTheDocument()
})

test('the list refetches only after the last of several mark_paid calls, or it shows a row paid meanwhile as unpaid', async () => {
  const user = userEvent.setup()
  const firstResponse = createHeldResponse()
  const secondResponse = createHeldResponse()
  let listRequestCount = 0
  mswServer.use(
    http.get('/api/invoices', () => {
      listRequestCount += 1

      return createCollectionResponse(
        listRequestCount === 1 ? [issuedUnpaidInvoiceRow, secondUnpaidInvoiceRow] : [secondUnpaidInvoiceRow],
      )
    }),
    http.post('/api/invoices/:invoiceId/mark_paid', async ({ params }) => {
      await (params.invoiceId === issuedUnpaidInvoiceRow.id ? firstResponse : secondResponse).released

      return respondWithPaidInvoice()
    }),
  )

  renderWithProviders(<InvoiceListPage />, '/?status=unpaid')
  await screen.findByText('1–2 z 2')
  const firstRow = screen.getByRole('row', { name: new RegExp(issuedUnpaidInvoiceRow.number, 'u') })
  const secondRow = screen.getByRole('row', { name: new RegExp(secondUnpaidInvoiceRow.number, 'u') })
  await user.click(within(firstRow).getByRole('button', { name: 'Označit jako uhrazenou' }))
  await user.click(within(secondRow).getByRole('button', { name: 'Označit jako uhrazenou' }))
  firstResponse.release()
  await new Promise((resolve) => setTimeout(resolve, 200))

  expect(listRequestCount).toBe(1)
  secondResponse.release()
  await waitFor(() => {
    expect(listRequestCount).toBe(2)
  })
})

test('a failed mark_paid leaves the stamp of the row paid after it', async () => {
  const user = userEvent.setup()
  const failingResponse = createHeldResponse()
  const succeedingResponse = createHeldResponse()
  mswServer.use(
    serveInvoiceRows([issuedUnpaidInvoiceRow, secondUnpaidInvoiceRow]),
    http.post('/api/invoices/:invoiceId/mark_paid', async ({ params }) => {
      if (params.invoiceId === issuedUnpaidInvoiceRow.id) {
        await failingResponse.released

        return HttpResponse.json(
          { status: 422, detail: 'Faktura je už uhrazená.' } satisfies ConstraintViolationResponse,
          { status: 422 },
        )
      }
      await succeedingResponse.released

      return respondWithPaidInvoice()
    }),
  )

  renderWithProviders(<InvoiceListPage />)
  await screen.findByText(issuedUnpaidInvoiceRow.number)
  const firstRow = screen.getByRole('row', { name: new RegExp(issuedUnpaidInvoiceRow.number, 'u') })
  const secondRow = screen.getByRole('row', { name: new RegExp(secondUnpaidInvoiceRow.number, 'u') })
  await user.click(within(firstRow).getByRole('button', { name: 'Označit jako uhrazenou' }))
  await user.click(within(secondRow).getByRole('button', { name: 'Označit jako uhrazenou' }))
  failingResponse.release()

  expect(await screen.findByText('Faktura je už uhrazená.')).toBeInTheDocument()
  expect(within(secondRow).getByRole('img', { name: 'Razítko Uhrazeno', hidden: true })).toBeInTheDocument()
  succeedingResponse.release()
})

test('a row paid away under the unpaid filter comes back unstamped once the filter is cleared', async () => {
  const user = userEvent.setup()
  const paidRow = { ...issuedUnpaidInvoiceRow, paidAt: '2026-09-25T00:00:00+00:00' } satisfies InvoiceRow
  let isPaid = false
  mswServer.use(
    http.get('/api/invoices', ({ request }) => {
      const isUnpaidFilter = new URL(request.url).searchParams.get('exists[paidAt]') === 'false'

      return createCollectionResponse(isPaid ? (isUnpaidFilter ? [] : [paidRow]) : [issuedUnpaidInvoiceRow])
    }),
    http.post('/api/invoices/:invoiceId/mark_paid', () => {
      isPaid = true

      return respondWithPaidInvoice()
    }),
  )

  renderWithProviders(<InvoiceListPage />, '/?status=unpaid')
  await user.click(await screen.findByRole('button', { name: 'Označit jako uhrazenou' }))
  await waitFor(
    () => {
      expect(screen.queryByText('Novák a syn s.r.o.')).not.toBeInTheDocument()
    },
    { timeout: STAMP_LIFETIME_MILLISECONDS },
  )

  await user.click(screen.getByRole('combobox', { name: 'Stav' }))
  await user.click(screen.getByRole('option', { name: 'Vše' }))

  expect(await screen.findByText('Novák a syn s.r.o.')).toBeInTheDocument()
  expect(screen.queryByRole('img', { name: 'Razítko Uhrazeno', hidden: true })).not.toBeInTheDocument()
})

test('a draft issued away under the draft filter comes back unstamped once the filter is cleared', async () => {
  const user = userEvent.setup()
  const issuedRow = { ...draftInvoiceRow, number: '2026-000143', status: 'issued', issuedAt: '2026-09-25' } satisfies InvoiceRow
  let isIssued = false
  mswServer.use(
    http.get('/api/invoices', ({ request }) => {
      const isDraftFilter = new URL(request.url).searchParams.get('status') === 'draft'

      return createCollectionResponse(isIssued ? (isDraftFilter ? [] : [issuedRow]) : [draftInvoiceRow])
    }),
    http.post('/api/invoices/:invoiceId/issue', () => {
      isIssued = true

      return HttpResponse.json(createInvoiceDetailResponse(draftInvoiceRow.id, 'issued'))
    }),
  )

  renderWithProviders(<InvoiceListPage />, '/?status=draft')
  await user.click(await screen.findByRole('button', { name: 'Vystavit' }))
  await user.click(within(await screen.findByRole('dialog')).getByRole('button', { name: 'Vystavit' }))
  expect(await screen.findByText('Faktura byla vystavena.')).toBeInTheDocument()
  await waitFor(
    () => {
      expect(screen.queryByText('Novák a syn s.r.o.')).not.toBeInTheDocument()
    },
    { timeout: STAMP_LIFETIME_MILLISECONDS },
  )

  await user.click(await screen.findByRole('combobox', { name: 'Stav' }))
  await user.click(screen.getByRole('option', { name: 'Vše' }))

  expect(await screen.findByText('2026-000143')).toBeInTheDocument()
  expect(screen.queryByRole('img', { name: 'Razítko Vystaveno', hidden: true })).not.toBeInTheDocument()
})

test('an issued row downloads its PDF from the last action, and a draft row offers none', async () => {
  const user = userEvent.setup()
  const invoicePdf = {
    '@id': '/api/.well-known/genid/1',
    '@type': 'InvoicePdf',
    name: 'faktura-2026-000142.pdf',
    base64Content: 'JVBERi0xLjcK',
  } satisfies InvoicePdf
  const requestedPdfPaths: string[] = []
  mswServer.use(
    serveInvoiceRows([issuedUnpaidInvoiceRow, draftInvoiceRow]),
    http.get('/api/invoices/:invoiceId/pdf', ({ request }) => {
      requestedPdfPaths.push(new URL(request.url).pathname)

      return HttpResponse.json(invoicePdf)
    }),
  )
  const clickedLinks: HTMLAnchorElement[] = []
  vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function recordClick(this: HTMLAnchorElement) {
    clickedLinks.push(this)
  })

  renderWithProviders(<InvoiceListPage />)

  await screen.findByText(issuedUnpaidInvoiceRow.number)
  const issuedRow = screen.getByRole('row', { name: new RegExp(issuedUnpaidInvoiceRow.number, 'u') })
  const issuedRowButtons = within(issuedRow).getAllByRole('button')
  expect(issuedRowButtons.at(-1)).toHaveAccessibleName(`Stáhnout PDF faktury ${issuedUnpaidInvoiceRow.number}`)

  await user.click(within(issuedRow).getByRole('button', { name: `Stáhnout PDF faktury ${issuedUnpaidInvoiceRow.number}` }))

  await waitFor(() => {
    expect(clickedLinks).toHaveLength(1)
  })
  expect(requestedPdfPaths).toEqual([`/api/invoices/${issuedUnpaidInvoiceRow.id}/pdf`])
  expect(clickedLinks[0]?.download).toBe('faktura-2026-000142.pdf')
  expect(screen.getAllByRole('button', { name: /^Stáhnout PDF/u })).toHaveLength(1)
})

const REFETCHING_ACTIONS = [
  {
    outcome: 'a paid invoice',
    reason: 'the server has the date and other pages move',
    shownRow: issuedUnpaidInvoiceRow,
    actionHandler: http.post('/api/invoices/:invoiceId/mark_paid', () => respondWithPaidInvoice()),
    actionLabel: 'Označit jako uhrazenou',
    confirmationLabel: null,
  },
  {
    outcome: 'an issued draft',
    reason: 'only the server knows its number',
    shownRow: draftInvoiceRow,
    actionHandler: http.post('/api/invoices/:invoiceId/issue', () =>
      HttpResponse.json(createInvoiceDetailResponse(draftInvoiceRow.id, 'issued')),
    ),
    actionLabel: 'Vystavit',
    confirmationLabel: 'Vystavit',
  },
  {
    outcome: 'a deleted draft',
    reason: 'other pages move',
    shownRow: draftInvoiceRow,
    actionHandler: http.delete('/api/invoices/:invoiceId', () => new HttpResponse(null, { status: 204 })),
    actionLabel: 'Smazat koncept pro Novák a syn s.r.o.',
    confirmationLabel: 'Smazat',
  },
] as const

test.each(REFETCHING_ACTIONS)(
  '$outcome refetches the list, since $reason',
  async ({ shownRow, actionHandler, actionLabel, confirmationLabel }) => {
    const user = userEvent.setup()
    const requestedUrls: string[] = []
    mswServer.use(serveInvoiceRows([shownRow], requestedUrls), actionHandler)

    renderWithProviders(<InvoiceListPage />)
    await user.click(await screen.findByRole('button', { name: actionLabel }))
    if (confirmationLabel !== null) {
      await user.click(within(await screen.findByRole('dialog')).getByRole('button', { name: confirmationLabel }))
    }

    await waitFor(() => {
      expect(requestedUrls).toHaveLength(2)
    })
  },
)
