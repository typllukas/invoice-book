import { expect, test } from 'vitest'
import { issuedInvoiceDetail } from '@/test/invoiceApiFakes'
import { invoiceDetailSchema } from './invoiceResponseSchema'

test('an invoice with dates as the API writes them passes', () => {
  expect(invoiceDetailSchema.safeParse({ ...issuedInvoiceDetail, paidAt: '2026-08-20T10:00:00+00:00' }).success).toBe(true)
})

test.each([
  { rule: 'a due date carrying a time', invoice: { ...issuedInvoiceDetail, dueAt: '2026-08-26T00:00:00+00:00' } },
  { rule: 'a tax point that is no date', invoice: { ...issuedInvoiceDetail, taxPointAt: '26. 8. 2026' } },
  { rule: 'a payment moment without its offset', invoice: { ...issuedInvoiceDetail, paidAt: '2026-08-20T10:00:00' } },
])('an invoice with $rule is refused', ({ invoice }) => {
  expect(invoiceDetailSchema.safeParse(invoice).success).toBe(false)
})
