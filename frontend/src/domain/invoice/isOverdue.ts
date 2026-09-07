import type { InvoiceRow } from '@/api/schema/types'

export function isOverdue(invoice: Pick<InvoiceRow, 'status' | 'paidAt' | 'dueAt'>, today: string): boolean {
  return invoice.status === 'issued' && invoice.paidAt === null && invoice.dueAt < today
}
