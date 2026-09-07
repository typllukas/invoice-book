export const INVOICE_STATUS_TONES = ['draft', 'unpaid', 'overdue', 'paid'] as const

export type InvoiceStatusTone = (typeof INVOICE_STATUS_TONES)[number]
