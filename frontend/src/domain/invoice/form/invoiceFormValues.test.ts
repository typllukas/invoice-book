import { expect, test } from 'vitest'
import type { InvoiceDetail, InvoiceInput } from '@/api/schema/types'
import { draftInvoiceDetail } from '@/test/invoiceApiFakes'
import {
  readHeaderValues,
  toInvoiceHeaderValues,
  toInvoiceInput,
  toInvoiceItemDrafts,
  type InvoiceHeaderValues,
  type InvoiceItemDraft,
} from './invoiceFormValues'

const headerValues = {
  clientName: 'Novák a syn s.r.o.',
  clientAddress: 'Vzorová 12, 000 01 Příkladov',
  clientCompanyId: '12345687',
  clientVatId: '',
  taxPointAt: '2026-08-12',
  dueAt: '2026-08-26',
  note: '',
} satisfies InvoiceHeaderValues

const consultationDraft = {
  key: 'first',
  id: '01K4A000000000000000000901',
  description: 'Konzultace',
  quantity: '8',
  unit: 'hod',
  unitPrice: '1500,00',
  vatRate: '21',
} satisfies InvoiceItemDraft

test('the typed values become the API input, an empty DIČ and note as null', () => {
  expect(toInvoiceInput(headerValues, [consultationDraft])).toEqual({
    ...headerValues,
    clientVatId: null,
    note: null,
    items: [
      {
        id: '01K4A000000000000000000901',
        description: 'Konzultace',
        quantity: '8.000',
        unit: 'hod',
        unitPriceNet: 150000,
        vatRate: '21',
      },
    ],
  } satisfies InvoiceInput)
})

test('a quantity or price the parser refuses goes to the schema as null, not as zero', () => {
  const refusedDraft = { ...consultationDraft, quantity: '1,2345', unitPrice: '1.234,50' }

  expect(toInvoiceInput(headerValues, [refusedDraft]).items).toEqual([
    { id: consultationDraft.id, description: 'Konzultace', quantity: null, unit: 'hod', unitPriceNet: null, vatRate: '21' },
  ])
})

test('a header field missing from the form data reads as empty', () => {
  const formData = new FormData()
  formData.set('clientName', 'Novák a syn s.r.o.')

  expect(readHeaderValues(formData)).toEqual({
    clientName: 'Novák a syn s.r.o.',
    clientAddress: '',
    clientCompanyId: '',
    clientVatId: '',
    taxPointAt: '',
    dueAt: '',
    note: '',
  } satisfies InvoiceHeaderValues)
})

const editedDraftDetail = {
  ...draftInvoiceDetail,
  clientVatId: null,
  note: 'Děkujeme.',
  items: [
    {
      id: '01K0000000000000000000000C',
      description: 'Ubytování',
      quantity: '1.250',
      unit: 'den',
      vatRate: '12',
      unitPriceNet: 12345,
      netAmount: 15431,
    },
  ],
} satisfies InvoiceDetail

for (const [caseName, invoice] of [
  ['a loaded draft', draftInvoiceDetail],
  ['a draft with a note, no DIČ and a fractional quantity', editedDraftDetail],
] as const) {
  test(`${caseName} opens in the form and saves back unchanged`, () => {
    const savedInput = toInvoiceInput(toInvoiceHeaderValues(invoice), toInvoiceItemDrafts(invoice.items))

    expect(savedInput).toEqual({
      clientName: invoice.clientName,
      clientAddress: invoice.clientAddress,
      clientCompanyId: invoice.clientCompanyId,
      clientVatId: invoice.clientVatId,
      taxPointAt: invoice.taxPointAt,
      dueAt: invoice.dueAt,
      note: invoice.note,
      items: invoice.items.map(({ id, description, quantity, unit, unitPriceNet, vatRate }) => ({
        id,
        description,
        quantity,
        unit,
        unitPriceNet,
        vatRate,
      })),
    } satisfies InvoiceInput)
  })
}
