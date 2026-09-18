import type { InvoiceDetail, InvoiceInput } from '@/api/schema/types'
import { formatMinorUnitsForInput, parseAmountToMinorUnits } from '@/domain/pricing/amount'
import { formatQuantity, formatThousandthsForApi, parseQuantityToThousandths } from '@/domain/pricing/quantity'
import type { VatRateValue } from '@/domain/pricing/vat'

export type InvoiceHeaderValues = Record<Exclude<keyof InvoiceInput, 'items'>, string>

export const EMPTY_HEADER_VALUES: InvoiceHeaderValues = {
  clientName: '',
  clientAddress: '',
  clientCompanyId: '',
  clientVatId: '',
  taxPointAt: '',
  dueAt: '',
  note: '',
}

export type InvoiceItemDraft = {
  /** Stable across renders, including for a row that has never been saved. */
  key: string
  /** The saved line this row stands for, null while it is only on screen. */
  id: string | null
  description: string
  quantity: string
  unit: string
  unitPrice: string
  vatRate: VatRateValue
}

export function createEmptyInvoiceItemDraft(): InvoiceItemDraft {
  return {
    key: crypto.randomUUID(),
    id: null,
    description: '',
    quantity: '1',
    unit: 'ks',
    unitPrice: '',
    vatRate: '21',
  }
}

// zero for the running recapitulation, the schema gets the null
export function readQuantityInThousandths(item: InvoiceItemDraft): number {
  return parseQuantityToThousandths(item.quantity) ?? 0
}

export function readUnitPriceNet(item: InvoiceItemDraft): number {
  return parseAmountToMinorUnits(item.unitPrice) ?? 0
}

// every key the API takes, the values left to the schema, so a misspelt key fails to compile
type InputKeys<Input> = { [Key in keyof Input]-?: unknown }

export function toItemInput(item: InvoiceItemDraft) {
  const quantityInThousandths = parseQuantityToThousandths(item.quantity)

  return {
    id: item.id,
    description: item.description,
    quantity: quantityInThousandths === null ? null : formatThousandthsForApi(quantityInThousandths),
    unit: item.unit,
    unitPriceNet: parseAmountToMinorUnits(item.unitPrice),
    vatRate: item.vatRate,
  } satisfies InputKeys<NonNullable<InvoiceInput['items']>[number]>
}

export function toInvoiceInput(values: InvoiceHeaderValues, items: readonly InvoiceItemDraft[]) {
  return {
    ...values,
    clientVatId: values.clientVatId === '' ? null : values.clientVatId,
    note: values.note === '' ? null : values.note,
    items: items.map(toItemInput),
  } satisfies InputKeys<InvoiceInput>
}

export function readHeaderValues(formData: FormData): InvoiceHeaderValues {
  const readField = (name: keyof InvoiceHeaderValues) => {
    const value = formData.get(name)

    return typeof value === 'string' ? value : ''
  }

  return {
    clientName: readField('clientName'),
    clientAddress: readField('clientAddress'),
    clientCompanyId: readField('clientCompanyId'),
    clientVatId: readField('clientVatId'),
    taxPointAt: readField('taxPointAt'),
    dueAt: readField('dueAt'),
    note: readField('note'),
  }
}

export function toInvoiceHeaderValues(invoice: InvoiceDetail) {
  return {
    clientName: invoice.clientName,
    clientAddress: invoice.clientAddress,
    clientCompanyId: invoice.clientCompanyId,
    clientVatId: invoice.clientVatId ?? '',
    taxPointAt: invoice.taxPointAt,
    dueAt: invoice.dueAt,
    note: invoice.note ?? '',
  } satisfies InvoiceHeaderValues
}

export function toInvoiceItemDrafts(items: InvoiceDetail['items']) {
  return items.map(
    (item) =>
      ({
        key: item.id,
        id: item.id,
        description: item.description,
        quantity: formatQuantity(item.quantity),
        unit: item.unit,
        unitPrice: formatMinorUnitsForInput(item.unitPriceNet),
        vatRate: item.vatRate,
      }) satisfies InvoiceItemDraft,
  )
}
