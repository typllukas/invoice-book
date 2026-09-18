import { expect, test } from 'vitest'
import type { InvoiceInput } from '@/api/schema/types'
import { collectFieldErrorsFromZod } from './fieldErrors'
import { invoiceFormSchema } from './invoiceFormSchema'

type InvoiceItemInput = NonNullable<InvoiceInput['items']>[number]

const validItem = {
  id: null,
  description: 'Konzultace',
  quantity: '8.000',
  unit: 'hod',
  unitPriceNet: 150000,
  vatRate: '21',
} satisfies InvoiceItemInput

const validInvoice = {
  clientName: 'Novák a syn s.r.o.',
  clientAddress: 'Vzorová 12, 000 01 Příkladov',
  clientCompanyId: '12345687',
  clientVatId: 'CZ12345687',
  taxPointAt: '2026-08-12',
  dueAt: '2026-08-26',
  note: null,
  items: [validItem],
} satisfies InvoiceInput

function createInvoiceWith(invoiceChange: Partial<InvoiceInput>): InvoiceInput {
  return { ...validInvoice, ...invoiceChange }
}

function createInvoiceWithItem(itemChange: Partial<InvoiceItemInput>): InvoiceInput {
  return { ...validInvoice, items: [{ ...validItem, ...itemChange }] }
}

test('a complete invoice passes as it is', () => {
  expect(invoiceFormSchema.safeParse(validInvoice).success).toBe(true)
})

test.each([
  { rule: 'an empty client name', input: createInvoiceWith({ clientName: '' }), field: 'clientName', message: 'Vyplňte jméno odběratele.' },
  { rule: 'a client name of spaces only', input: createInvoiceWith({ clientName: '   ' }), field: 'clientName', message: 'Vyplňte jméno odběratele.' },
  { rule: 'an empty client address', input: createInvoiceWith({ clientAddress: '' }), field: 'clientAddress', message: 'Vyplňte adresu odběratele.' },
  { rule: 'an IČO short of eight digits', input: createInvoiceWith({ clientCompanyId: '123' }), field: 'clientCompanyId', message: 'IČO musí mít osm číslic.' },
  { rule: 'a DIČ without the country code', input: createInvoiceWith({ clientVatId: '12345687' }), field: 'clientVatId', message: 'DIČ musí mít tvar CZ a osm až deset číslic.' },
  { rule: 'an empty tax point', input: createInvoiceWith({ taxPointAt: '' }), field: 'taxPointAt', message: 'Vyplňte datum uskutečnění zdanitelného plnění.' },
  { rule: 'an empty due date', input: createInvoiceWith({ dueAt: '' }), field: 'dueAt', message: 'Vyplňte datum splatnosti.' },
  { rule: 'an invoice with no items', input: createInvoiceWith({ items: [] }), field: 'items', message: 'Faktura musí mít aspoň jednu položku.' },
  { rule: 'an empty item description', input: createInvoiceWithItem({ description: '' }), field: 'items.0.description', message: 'Vyplňte popis položky.' },
  { rule: 'an item description of spaces only', input: createInvoiceWithItem({ description: ' ' }), field: 'items.0.description', message: 'Vyplňte popis položky.' },
  { rule: 'a quantity of zero', input: createInvoiceWithItem({ quantity: '0.000' }), field: 'items.0.quantity', message: 'Množství musí být větší než nula.' },
  { rule: 'a negative quantity', input: createInvoiceWithItem({ quantity: '-1.000' }), field: 'items.0.quantity', message: 'Množství musí být větší než nula.' },
  { rule: 'an empty unit', input: createInvoiceWithItem({ unit: '' }), field: 'items.0.unit', message: 'Vyplňte měrnou jednotku.' },
  { rule: 'a negative unit price', input: createInvoiceWithItem({ unitPriceNet: -1 }), field: 'items.0.unitPriceNet', message: 'Cena za jednotku nesmí být záporná.' },
])('$rule is refused on $field alone', ({ input, field, message }) => {
  const parsed = invoiceFormSchema.safeParse(input)

  expect(parsed.error && collectFieldErrorsFromZod(parsed.error)).toEqual({ [field]: message })
})

test('a quantity or price the parser refused is named as not a number', () => {
  const parsed = invoiceFormSchema.safeParse({ ...validInvoice, items: [{ ...validItem, quantity: null, unitPriceNet: null }] })

  expect(parsed.error && collectFieldErrorsFromZod(parsed.error)).toEqual({
    'items.0.quantity': 'Množství musí být číslo, nejvýš na tři desetinná místa.',
    'items.0.unitPriceNet': 'Cena za jednotku musí být číslo, nejvýš na dvě desetinná místa.',
  })
})
