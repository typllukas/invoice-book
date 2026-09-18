import { z } from 'zod'

const savedInvoiceStateSchema = z.object({
  invoiceSaved: z.literal(true),
  invoiceIssued: z.literal(true).optional(),
})

export type SavedInvoiceState = z.infer<typeof savedInvoiceStateSchema>

export function wasInvoiceJustSaved(state: unknown): boolean {
  return savedInvoiceStateSchema.safeParse(state).success
}

export function wasInvoiceJustIssued(state: unknown): boolean {
  const parsed = savedInvoiceStateSchema.safeParse(state)

  return parsed.success && parsed.data.invoiceIssued === true
}
