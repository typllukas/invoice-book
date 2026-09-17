import type { InvoiceRow } from '@/api/schema/types'

export function findIssueBlocker(invoice: Pick<InvoiceRow, 'items'>): string | null {
  return invoice.items.length === 0 ? 'Faktura nemá žádnou položku. Doplňte ji v konceptu.' : null
}
