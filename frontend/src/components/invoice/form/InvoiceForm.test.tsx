import { screen, waitFor, waitForElementToBeRemoved, within } from '@testing-library/react'
import { userEvent, type UserEvent } from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { createMemoryRouter } from 'react-router'
import { expect, test } from 'vitest'
import type { InvoiceDetail, InvoiceInput } from '@/api/schema/types'
import { createInvoiceDetailResponse, draftInvoiceDetail, type ConstraintViolationResponse } from '@/test/invoiceApiFakes'
import { mswServer } from '@/test/mswServer'
import { renderRouterWithProviders } from '@/test/renderWithProviders'
import { toInvoiceHeaderValues, toInvoiceItemDrafts, type InvoiceHeaderValues } from '@/domain/invoice/form/invoiceFormValues'
import { InvoiceForm } from './InvoiceForm'

const CREATED_INVOICE_ID = '01K4A000000000000000000142'

const filledHeader: InvoiceHeaderValues = {
  clientName: 'Novák a syn s.r.o.',
  clientAddress: 'Vzorová 12, 000 01 Příkladov',
  clientCompanyId: '12345687',
  clientVatId: 'CZ12345687',
  taxPointAt: '2026-08-12',
  dueAt: '2026-08-26',
  note: '',
}

const newInvoiceForm = (
  <InvoiceForm
    invoiceId={undefined}
    heading="Nová faktura · koncept"
    initialValues={filledHeader}
    initialItems={[
      {
        key: 'first',
        id: null,
        description: 'Konzultace',
        quantity: '8',
        unit: 'hod',
        unitPrice: '1500,00',
        vatRate: '21',
      },
    ]}
  />
)

function renderForm() {
  const router = createMemoryRouter(
    [
      { path: '/', element: <p>Seznam faktur</p> },
      { path: '/invoices/new', element: newInvoiceForm },
      { path: '/invoices/:invoiceId', element: <p>Faktura</p> },
    ],
    { initialEntries: ['/invoices/new'] },
  )
  renderRouterWithProviders(router)

  return router
}

async function issueThroughConfirmation(user: UserEvent) {
  await user.click(screen.getByRole('button', { name: 'Vystavit' }))
  const confirmation = await screen.findByRole('dialog', { name: 'Vystavit fakturu?' })
  await user.click(within(confirmation).getByRole('button', { name: 'Vystavit' }))
}

function respondWithCreatedInvoice() {
  return HttpResponse.json(createInvoiceDetailResponse(CREATED_INVOICE_ID, 'draft'), { status: 201 })
}

function handleCreateAndIssue(issueResponse: Response) {
  const requests: { createdBodies: unknown[]; issuedPaths: string[] } = { createdBodies: [], issuedPaths: [] }
  mswServer.use(
    http.post('/api/invoices', async ({ request }) => {
      requests.createdBodies.push(await request.json())

      return respondWithCreatedInvoice()
    }),
    http.post('/api/invoices/:invoiceId/issue', ({ request }) => {
      requests.issuedPaths.push(new URL(request.url).pathname)

      return issueResponse
    }),
  )

  return requests
}

function countCreateRequests() {
  const createRequests = { count: 0 }
  mswServer.use(
    http.post('/api/invoices', () => {
      createRequests.count += 1

      return respondWithCreatedInvoice()
    }),
  )

  return createRequests
}

test('the vat recapitulation follows what is typed, without saving', async () => {
  const user = userEvent.setup()
  renderForm()

  expect(within(screen.getByRole('row', { name: /^Celkem/u })).getByText('14 520,00 Kč')).toBeInTheDocument()

  await user.clear(screen.getByLabelText('Množství'))
  await user.type(screen.getByLabelText('Množství'), '4')

  expect(within(screen.getByRole('row', { name: /^Celkem/u })).getByText('7 260,00 Kč')).toBeInTheDocument()
})

test('a 422 from the API lands on the field it names and the form keeps what was typed', async () => {
  const user = userEvent.setup()
  mswServer.use(
    http.post('/api/invoices', () =>
      HttpResponse.json(
        {
          status: 422,
          detail: 'clientCompanyId: IČO musí mít osm číslic.',
          violations: [{ propertyPath: 'clientCompanyId', message: 'IČO musí mít osm číslic.' }],
        } satisfies ConstraintViolationResponse,
        { status: 422 },
      ),
    ),
  )

  renderForm()
  await user.click(screen.getByRole('button', { name: 'Uložit' }))

  expect(await screen.findByText('IČO musí mít osm číslic.')).toBeInTheDocument()
  // loose label, MUI appends the asterisk of a required field
  expect(screen.getByLabelText(/^Odběratel/u)).toHaveValue('Novák a syn s.r.o.')
})

test('the header text fields stop at the lengths the API stores', () => {
  renderForm()

  expect(screen.getByLabelText(/^Odběratel/u)).toHaveAttribute('maxlength', '255')
  expect(screen.getByLabelText(/^Adresa/u)).toHaveAttribute('maxlength', '255')
  expect(screen.getByLabelText('Poznámka')).toHaveAttribute('maxlength', '1000')
})

test('an empty item description blocks the save and is named on its field', async () => {
  const user = userEvent.setup()
  const createRequests = countCreateRequests()
  renderForm()

  await user.clear(screen.getByLabelText('Popis'))
  await user.click(screen.getByRole('button', { name: 'Uložit' }))

  expect(await screen.findByText('Formulář obsahuje chyby, opravte zvýrazněná pole.')).toBeInTheDocument()
  expect(screen.getByLabelText('Popis')).toHaveAccessibleDescription('Vyplňte popis položky.')
  expect(createRequests.count).toBe(0)
})

test('the vat rates are offered in the order the recapitulation lists them', async () => {
  const user = userEvent.setup()
  renderForm()

  await user.click(screen.getByRole('combobox', { name: 'Sazba DPH' }))

  expect(screen.getAllByRole('option').map((option) => option.textContent)).toEqual(['21 %', '12 %', 'osvobozeno'])
})

test('a created invoice stays open under its new id, which says it was created', async () => {
  const user = userEvent.setup()
  countCreateRequests()
  const router = renderForm()

  await user.type(screen.getByLabelText('Poznámka'), 'Děkujeme.')
  await user.click(screen.getByRole('button', { name: 'Uložit' }))

  expect(await screen.findByText('Koncept faktury byl vytvořen.')).toBeInTheDocument()
  expect(router.state.location.pathname).toBe(`/invoices/${CREATED_INVOICE_ID}`)
})

test('a second save of a draft sends the item the first save added under the id it got', async () => {
  const user = userEvent.setup()
  const addedItem = {
    id: '01K0000000000000000000000C',
    description: 'Školení',
    quantity: '1.000',
    unit: 'ks',
    vatRate: '21',
    unitPriceNet: 10000,
    netAmount: 10000,
  } satisfies InvoiceDetail['items'][number]
  const updatedBodies: unknown[] = []
  mswServer.use(
    http.put('/api/invoices/:invoiceId', async ({ request }) => {
      updatedBodies.push(await request.json())

      return HttpResponse.json({ ...draftInvoiceDetail, items: [...draftInvoiceDetail.items, addedItem] })
    }),
  )
  const router = createMemoryRouter(
    [
      {
        path: '/invoices/:invoiceId',
        element: (
          <InvoiceForm
            invoiceId={draftInvoiceDetail.id}
            heading="Faktura · koncept"
            initialValues={toInvoiceHeaderValues(draftInvoiceDetail)}
            initialItems={toInvoiceItemDrafts(draftInvoiceDetail.items)}
          />
        ),
      },
    ],
    { initialEntries: [`/invoices/${draftInvoiceDetail.id}`] },
  )
  renderRouterWithProviders(router)

  await user.click(screen.getByRole('button', { name: 'Přidat položku' }))
  await user.type(getItemField('Popis', 1), 'Školení')
  await user.type(getItemField('Cena za MJ bez DPH', 1), '100')
  await user.click(screen.getByRole('button', { name: 'Uložit' }))
  expect(await screen.findByText('Koncept faktury byl uložen.')).toBeInTheDocument()
  await waitFor(() => {
    expect(screen.getByRole('button', { name: 'Uložit' })).toBeEnabled()
  })
  await user.click(screen.getByRole('button', { name: 'Uložit' }))

  await waitFor(() => {
    expect(updatedBodies).toHaveLength(2)
  })
  expect(updatedBodies[0]).toMatchObject({ items: [{ id: '01K0000000000000000000000B' }, { id: null }] })
  expect(updatedBodies[1]).toMatchObject({ items: [{ id: '01K0000000000000000000000B' }, { id: addedItem.id }] })
  expect(router.state.location.pathname).toBe(`/invoices/${draftInvoiceDetail.id}`)
})

test('creating an invoice sends the one content type the API accepts', async () => {
  const user = userEvent.setup()
  const contentTypes: (string | null)[] = []
  mswServer.use(
    http.post('/api/invoices', ({ request }) => {
      contentTypes.push(request.headers.get('Content-Type'))

      return respondWithCreatedInvoice()
    }),
  )

  renderForm()
  await user.click(screen.getByRole('button', { name: 'Uložit' }))

  await waitFor(() => {
    expect(contentTypes).toEqual(['application/ld+json'])
  })
})

test('Vystavit saves what is on screen and then issues it', async () => {
  const user = userEvent.setup()
  const requests = handleCreateAndIssue(
    HttpResponse.json(createInvoiceDetailResponse(CREATED_INVOICE_ID, 'issued')),
  )
  const router = renderForm()

  await user.type(screen.getByLabelText('Poznámka'), 'Děkujeme.')
  await issueThroughConfirmation(user)

  await waitFor(() => {
    expect(router.state.location.pathname).toBe('/invoices/01K4A000000000000000000142')
  })
  expect(screen.getByText('Faktura byla vystavena.')).toBeInTheDocument()
  const expectedBody = {
    clientName: 'Novák a syn s.r.o.',
    clientAddress: 'Vzorová 12, 000 01 Příkladov',
    clientCompanyId: '12345687',
    clientVatId: 'CZ12345687',
    taxPointAt: '2026-08-12',
    dueAt: '2026-08-26',
    note: 'Děkujeme.',
    items: [
      { id: null, description: 'Konzultace', quantity: '8.000', unit: 'hod', unitPriceNet: 150000, vatRate: '21' },
    ],
  } satisfies InvoiceInput
  expect(requests.createdBodies).toEqual([expectedBody])
  expect(requests.issuedPaths).toEqual(['/api/invoices/01K4A000000000000000000142/issue'])
})

test('Zrušit in the issue confirmation sends nothing', async () => {
  const user = userEvent.setup()
  const requests = handleCreateAndIssue(
    HttpResponse.json(createInvoiceDetailResponse(CREATED_INVOICE_ID, 'issued')),
  )
  renderForm()

  await user.click(screen.getByRole('button', { name: 'Vystavit' }))
  const confirmation = await screen.findByRole('dialog', { name: 'Vystavit fakturu?' })
  await user.click(within(confirmation).getByRole('button', { name: 'Zrušit' }))

  await waitForElementToBeRemoved(confirmation)
  expect(requests.createdBodies).toEqual([])
  expect(requests.issuedPaths).toEqual([])
})

test('a refused issue of a new invoice moves to the saved draft and says why in a toast', async () => {
  const user = userEvent.setup()
  handleCreateAndIssue(
    HttpResponse.json(
      { status: 422, detail: 'Faktura nemá dodavatele.' } satisfies ConstraintViolationResponse,
      { status: 422 },
    ),
  )
  const router = renderForm()

  await issueThroughConfirmation(user)

  expect(await screen.findByText('Faktura nemá dodavatele.')).toBeInTheDocument()
  expect(router.state.location.pathname).toBe('/invoices/01K4A000000000000000000142')
})

test('leaving with unsaved changes asks first, and Zůstat keeps what was typed', async () => {
  const user = userEvent.setup()
  const router = renderForm()

  await user.type(screen.getByLabelText('Poznámka'), 'Děkujeme.')
  await user.click(screen.getByRole('link', { name: 'Zrušit' }))

  const leaveConfirmation = await screen.findByRole('dialog', { name: 'Odejít?' })
  expect(router.state.location.pathname).toBe('/invoices/new')

  await user.click(within(leaveConfirmation).getByRole('button', { name: 'Zůstat' }))
  await waitForElementToBeRemoved(leaveConfirmation)
  expect(screen.getByLabelText('Poznámka')).toHaveValue('Děkujeme.')

  await user.click(screen.getByRole('link', { name: 'Zrušit' }))
  await user.click(await screen.findByRole('button', { name: 'Odejít' }))

  expect(router.state.location.pathname).toBe('/')
})

test('adding an item arms the tab-close guard', async () => {
  const user = userEvent.setup()
  renderForm()
  const beforeUnloadBeforeEdit = new Event('beforeunload', { cancelable: true })
  window.dispatchEvent(beforeUnloadBeforeEdit)

  await user.click(screen.getByRole('button', { name: 'Přidat položku' }))
  const beforeUnloadAfterEdit = new Event('beforeunload', { cancelable: true })
  window.dispatchEvent(beforeUnloadAfterEdit)

  expect(beforeUnloadBeforeEdit.defaultPrevented).toBe(false)
  expect(beforeUnloadAfterEdit.defaultPrevented).toBe(true)
})

test('an untouched form leaves without asking', async () => {
  const user = userEvent.setup()
  const router = renderForm()

  await user.click(screen.getByRole('link', { name: 'Zrušit' }))

  expect(router.state.location.pathname).toBe('/')
  expect(screen.queryByRole('dialog', { name: 'Odejít?' })).not.toBeInTheDocument()
})

test('a field shows its error once it is left, and clears as soon as it is right', async () => {
  const user = userEvent.setup()
  renderForm()
  const companyIdField = screen.getByLabelText(/^IČO/u)

  await user.clear(companyIdField)
  await user.type(companyIdField, '123')
  expect(screen.queryByText('IČO musí mít osm číslic.')).not.toBeInTheDocument()

  await user.tab()
  expect(await screen.findByText('IČO musí mít osm číslic.')).toBeInTheDocument()

  await user.type(companyIdField, '45687')
  await waitFor(() => {
    expect(screen.queryByText('IČO musí mít osm číslic.')).not.toBeInTheDocument()
  })
})

test('an item field shows its error once it is left, without saving', async () => {
  const user = userEvent.setup()
  renderForm()

  await user.clear(screen.getByLabelText('Popis'))
  await user.tab()

  await waitFor(() => {
    expect(screen.getByLabelText('Popis')).toHaveAccessibleDescription('Vyplňte popis položky.')
  })
})

function getItemField(label: string, itemIndex: number): HTMLElement {
  const itemField = screen.getAllByLabelText(label)[itemIndex]
  if (itemField === undefined) {
    throw new Error(`Item ${itemIndex + 1} has no ${label} field`)
  }

  return itemField
}

test('a deleted item takes its save error along, not onto the item below it', async () => {
  const user = userEvent.setup()
  renderForm()

  await user.click(screen.getByRole('button', { name: 'Přidat položku' }))
  await user.type(getItemField('Popis', 1), 'Školení')
  await user.type(getItemField('Cena za MJ bez DPH', 1), '100')
  await user.clear(getItemField('Popis', 0))
  await user.click(screen.getByRole('button', { name: 'Uložit' }))
  expect(await screen.findByText('Formulář obsahuje chyby, opravte zvýrazněná pole.')).toBeInTheDocument()
  await user.click(screen.getByRole('button', { name: 'Smazat položku 1' }))

  expect(screen.getByLabelText('Popis')).toHaveValue('Školení')
  expect(screen.getByLabelText('Popis')).not.toHaveAccessibleDescription('Vyplňte popis položky.')
})

test('an item added after a deleted one does not inherit that it was left', async () => {
  const user = userEvent.setup()
  renderForm()

  await user.clear(screen.getByLabelText('Popis'))
  await user.tab()
  await user.click(screen.getByRole('button', { name: 'Přidat položku' }))
  await user.click(screen.getByRole('button', { name: 'Smazat položku 1' }))

  expect(screen.getByLabelText('Popis')).toHaveValue('')
  expect(screen.getByLabelText('Popis')).not.toHaveAccessibleDescription('Vyplňte popis položky.')
})

test('a quantity that is not a number is named as such, not as zero', async () => {
  const user = userEvent.setup()
  renderForm()

  await user.clear(screen.getByLabelText('Množství'))
  await user.type(screen.getByLabelText('Množství'), '1,2345')
  await user.tab()

  await waitFor(() => {
    expect(screen.getByLabelText('Množství')).toHaveAccessibleDescription(
      'Množství musí být číslo, nejvýš na tři desetinná místa.',
    )
  })
})
