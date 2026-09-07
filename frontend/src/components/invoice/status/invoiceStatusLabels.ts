import type { InvoiceStatusTone } from '@/domain/invoice/invoiceStatusTone'

export const INVOICE_STATUS_TONE_LABELS: Readonly<Record<InvoiceStatusTone, string>> = {
  draft: 'Koncept',
  unpaid: 'Neuhrazeno',
  overdue: 'Po splatnosti',
  paid: 'Uhrazeno',
}
