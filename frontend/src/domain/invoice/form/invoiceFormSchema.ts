import { z } from 'zod'
import type { InvoiceInput } from '@/api/schema/types'
import { VAT_RATES } from '@/domain/pricing/vat'

export const invoiceItemSchema = z.object({
  id: z.string().nullable(),
  description: z.string().trim().min(1, 'Vyplňte popis položky.'),
  // null when the parser refuses the quantity, e.g. "1,2345"
  quantity: z
    .string('Množství musí být číslo, nejvýš na tři desetinná místa.')
    .refine((quantity) => Number(quantity) > 0, 'Množství musí být větší než nula.'),
  unit: z.string().trim().min(1, 'Vyplňte měrnou jednotku.'),
  unitPriceNet: z
    .number('Cena za jednotku musí být číslo, nejvýš na dvě desetinná místa.')
    .int()
    .nonnegative('Cena za jednotku nesmí být záporná.'),
  vatRate: z.enum(VAT_RATES),
})

export const invoiceFormSchema: z.ZodType<InvoiceInput> = z.object({
  clientName: z.string().trim().min(1, 'Vyplňte jméno odběratele.'),
  clientAddress: z.string().trim().min(1, 'Vyplňte adresu odběratele.'),
  clientCompanyId: z.string().regex(/^\d{8}$/u, 'IČO musí mít osm číslic.'),
  clientVatId: z
    .string()
    .regex(/^CZ\d{8,10}$/u, 'DIČ musí mít tvar CZ a osm až deset číslic.')
    .nullable(),
  taxPointAt: z.string().min(1, 'Vyplňte datum uskutečnění zdanitelného plnění.'),
  dueAt: z.string().min(1, 'Vyplňte datum splatnosti.'),
  note: z.string().nullable(),
  items: z.array(invoiceItemSchema).min(1, 'Faktura musí mít aspoň jednu položku.'),
})
