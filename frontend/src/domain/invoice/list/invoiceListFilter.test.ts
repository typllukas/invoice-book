import { expect, test } from 'vitest'
import {
  EMPTY_INVOICE_LIST_FILTER,
  readFilterFromSearchParams,
  writeFilterToSearchParams,
  type InvoiceListFilter,
} from './invoiceListFilter'

test('a filter survives the round trip through the URL', () => {
  const filter = {
    clientName: 'Test Client',
    status: 'overdue',
    issuedFrom: '2026-01-01',
    issuedTo: '2026-01-31',
    sortBy: 'dueAt',
    sortDirection: 'desc',
    page: 3,
  } satisfies InvoiceListFilter

  expect(readFilterFromSearchParams(writeFilterToSearchParams(filter))).toEqual(filter)
})

test('the empty filter leaves the URL clean', () => {
  expect(writeFilterToSearchParams(EMPTY_INVOICE_LIST_FILTER).toString()).toBe('')
})

test('a value the list does not know falls back to its default', () => {
  const searchParams = new URLSearchParams('status=issued&sort=totalGrossAmount&dir=up&page=-2')

  expect(readFilterFromSearchParams(searchParams)).toEqual(EMPTY_INVOICE_LIST_FILTER)
})

test('a hand-edited date that is not a date is dropped rather than shown as a filter the list ignores', () => {
  const searchParams = new URLSearchParams('issuedFrom=yesterday&issuedTo=2026-02-30')

  expect(readFilterFromSearchParams(searchParams)).toEqual(EMPTY_INVOICE_LIST_FILTER)
})

test('a page that is not a whole number is the first page', () => {
  expect(readFilterFromSearchParams(new URLSearchParams('page=2.5')).page).toBe(1)
  expect(readFilterFromSearchParams(new URLSearchParams('page=abc')).page).toBe(1)
})
