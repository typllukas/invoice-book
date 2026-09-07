import { z } from 'zod'
import { VAT_RATES } from '@/domain/pricing/vat'
import type { InvoiceCollectionResponse, InvoiceDetail, InvoicePdf, InvoiceRow } from '@/api/schema/types'

/*
 * Fails when a response disagrees with its own documentation, which the generated types cannot see.
 * Typed over them, so a field the contract adds, renames or retypes stops the build.
 */

const hydraItemFields = {
  '@id': z.string(),
  '@type': z.string(),
} satisfies z.ZodRawShape

const vatRateSchema = z.enum(VAT_RATES)

const invoiceStatusSchema = z.enum(['draft', 'issued'])

const calendarDateSchema = z.iso.date()

const momentSchema = z.iso.datetime({ offset: true })

const invoiceItemSchema = z.object({
  id: z.string(),
  description: z.string(),
  quantity: z.string(),
  unit: z.string(),
  vatRate: vatRateSchema,
  unitPriceNet: z.number(),
  netAmount: z.number(),
})

const invoiceRowSchema: z.ZodType<InvoiceRow> = z.object({
  ...hydraItemFields,
  id: z.string(),
  number: z.string().nullable(),
  status: invoiceStatusSchema,
  clientName: z.string(),
  issuedAt: calendarDateSchema.nullable(),
  dueAt: calendarDateSchema,
  paidAt: momentSchema.nullable(),
  items: z.array(invoiceItemSchema),
  totalGrossAmount: z.number(),
})

export const invoiceDetailSchema: z.ZodType<InvoiceDetail> = z.object({
  ...hydraItemFields,
  id: z.string(),
  number: z.string().nullable(),
  status: invoiceStatusSchema,
  clientName: z.string(),
  clientAddress: z.string(),
  clientCompanyId: z.string(),
  clientVatId: z.string().nullable(),
  supplierName: z.string().nullable(),
  supplierAddress: z.string().nullable(),
  supplierCompanyId: z.string().nullable(),
  supplierVatId: z.string().nullable(),
  supplierBankAccount: z.string().nullable(),
  supplierRegisterEntry: z.string().nullable(),
  issuedAt: calendarDateSchema.nullable(),
  dueAt: calendarDateSchema,
  taxPointAt: calendarDateSchema,
  paidAt: momentSchema.nullable(),
  note: z.string().nullable(),
  items: z.array(invoiceItemSchema),
  variableSymbol: z.string().nullable(),
  vatSummary: z.array(
    z.object({
      vatRate: vatRateSchema,
      netAmount: z.number(),
      vatAmount: z.number(),
      grossAmount: z.number(),
    }),
  ),
  totalNetAmount: z.number(),
  totalVatAmount: z.number(),
  totalGrossAmount: z.number(),
})

export const invoicePdfSchema: z.ZodType<InvoicePdf> = z.object({
  ...hydraItemFields,
  name: z.string(),
  base64Content: z.string(),
})

export const invoiceListResponseSchema: z.ZodType<InvoiceCollectionResponse> = z.looseObject({
  member: z.array(invoiceRowSchema),
  totalItems: z.number().optional(),
})
