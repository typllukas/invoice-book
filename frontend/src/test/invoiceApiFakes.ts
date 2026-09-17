import type { components } from '@/api/schema/schema'
import { http, HttpResponse } from 'msw'
import type { InvoiceCollectionResponse, InvoiceDetail, InvoiceRow } from '@/api/schema/types'

export type ConstraintViolationResponse = components['schemas']['ConstraintViolation']

export type ErrorResponse = components['schemas']['Error']

export const issuedInvoiceDetail = {
  '@id': '/api/invoices/01K0000000000000000000000A',
  '@type': 'Invoice',
  id: '01K0000000000000000000000A',
  number: '2026-000007',
  status: 'issued',
  clientName: 'Novák a syn s.r.o.',
  clientAddress: 'Vzorová 12, 000 01 Příkladov',
  clientCompanyId: '12345687',
  clientVatId: 'CZ12345687',
  supplierName: 'Vzorová dodavatelská s.r.o.',
  supplierAddress: 'Ukázková 1, 000 00 Vzorov',
  supplierCompanyId: '87654326',
  supplierVatId: 'CZ87654326',
  supplierBankAccount: '123456789/0800',
  supplierRegisterEntry: 'Zapsáno v obchodním rejstříku vedeném Krajským soudem ve Vzorově, oddíl C, vložka 00000',
  issuedAt: '2026-08-12',
  dueAt: '2026-08-26',
  taxPointAt: '2026-08-12',
  paidAt: null,
  note: null,
  variableSymbol: '2026000007',
  items: [
    {
      id: '01K0000000000000000000000B',
      description: 'Konzultace',
      quantity: '8.000',
      unit: 'hod',
      vatRate: '21',
      unitPriceNet: 150000,
      netAmount: 1200000,
    },
  ],
  vatSummary: [{ vatRate: '21', netAmount: 1200000, vatAmount: 252000, grossAmount: 1452000 }],
  totalNetAmount: 1200000,
  totalVatAmount: 252000,
  totalGrossAmount: 1452000,
} satisfies InvoiceDetail

// the supplier and the number are stamped onto an invoice when it is issued
export const draftInvoiceDetail = {
  ...issuedInvoiceDetail,
  number: null,
  status: 'draft',
  supplierName: null,
  supplierAddress: null,
  supplierCompanyId: null,
  supplierVatId: null,
  supplierBankAccount: null,
  supplierRegisterEntry: null,
  issuedAt: null,
  variableSymbol: null,
} satisfies InvoiceDetail

/** What a create or issue call answers with, which is the whole invoice under the given id. */
export function createInvoiceDetailResponse(invoiceId: string, status: InvoiceDetail['status']) {
  const invoice = status === 'issued' ? issuedInvoiceDetail : draftInvoiceDetail

  return { ...invoice, '@id': `/api/invoices/${invoiceId}`, id: invoiceId } satisfies InvoiceDetail
}

export const issuedUnpaidInvoiceRow = {
  '@id': '/api/invoices/01K4A000000000000000000142',
  '@type': 'Invoice',
  id: '01K4A000000000000000000142',
  number: '2026-000142',
  status: 'issued',
  clientName: 'Novák a syn s.r.o.',
  issuedAt: '2026-08-12',
  dueAt: '2026-08-26',
  paidAt: null,
  items: [
    {
      id: '01K4A000000000000000000801',
      description: 'Vývoj na míru',
      quantity: '40.000',
      unit: 'hod',
      vatRate: '21',
      unitPriceNet: 100000,
      netAmount: 4000000,
    },
  ],
  totalGrossAmount: 4840000,
} satisfies InvoiceRow

export const draftInvoiceRow = {
  ...issuedUnpaidInvoiceRow,
  '@id': '/api/invoices/01K4A000000000000000000143',
  id: '01K4A000000000000000000143',
  number: null,
  status: 'draft',
  issuedAt: null,
  items: [
    {
      id: '01K4A000000000000000000901',
      description: 'Konzultace',
      quantity: '8.000',
      unit: 'hod',
      vatRate: '21',
      unitPriceNet: 150000,
      netAmount: 1200000,
    },
  ],
} satisfies InvoiceRow

export const emptyDraftInvoiceRow = {
  ...draftInvoiceRow,
  '@id': '/api/invoices/01K4A000000000000000000144',
  id: '01K4A000000000000000000144',
  items: [],
} satisfies InvoiceRow

export function createCollectionResponse(members: InvoiceRow[]) {
  return HttpResponse.json({ member: members, totalItems: members.length } satisfies InvoiceCollectionResponse)
}

export function serveInvoiceRows(members: InvoiceRow[], requestedUrls?: string[]) {
  return http.get('/api/invoices', ({ request }) => {
    requestedUrls?.push(decodeURIComponent(request.url))

    return createCollectionResponse(members)
  })
}
