import { expect, test } from 'vitest'
import { isOverdue } from './isOverdue'

const TODAY = '2026-09-29'

test.each([
  { invoiceState: 'an issued unpaid invoice due yesterday', invoice: { status: 'issued', paidAt: null, dueAt: '2026-09-28' }, overdue: true },
  { invoiceState: 'an issued unpaid invoice due today', invoice: { status: 'issued', paidAt: null, dueAt: TODAY }, overdue: false },
  { invoiceState: 'a paid invoice due long ago', invoice: { status: 'issued', paidAt: '2026-09-01T10:00:00+00:00', dueAt: '2026-08-01' }, overdue: false },
  { invoiceState: 'a draft past its due date', invoice: { status: 'draft', paidAt: null, dueAt: '2026-08-01' }, overdue: false },
] as const)('$invoiceState is overdue: $overdue', ({ invoice, overdue }) => {
  expect(isOverdue(invoice, TODAY)).toBe(overdue)
})
