import type { InvoiceDetail } from '@/api/schema/types'

export type VatSummaryLine = InvoiceDetail['vatSummary'][number]

export type VatRateValue = VatSummaryLine['vatRate']

export const VAT_RATES = ['21', '12', '0'] as const satisfies readonly VatRateValue[]

/** An exempt supply, not a zero rate; the document must say it is exempt. */
export const EXEMPT_VAT_RATE = '0' satisfies VatRateValue

export type VatCalculationItem = {
  quantityInThousandths: number
  unitPriceNet: number
  vatRate: VatRateValue
}

export const VAT_RATE_LABELS: Readonly<Record<VatRateValue, string>> = {
  '21': '21 %',
  '12': '12 %',
  '0': 'osvobozeno',
}

function roundHalfUp(dividend: number, divisor: number): number {
  return Math.floor((dividend * 2 + divisor) / (divisor * 2))
}

export function calculateLineNetAmount(item: VatCalculationItem): number {
  const wholeUnits = Math.floor(item.quantityInThousandths / 1000)
  const fractionThousandths = item.quantityInThousandths % 1000

  return wholeUnits * item.unitPriceNet + roundHalfUp(fractionThousandths * item.unitPriceNet, 1000)
}

function summarizeVat(items: readonly VatCalculationItem[]): VatSummaryLine[] {
  const netAmountByRate = new Map<VatRateValue, number>()

  for (const item of items) {
    netAmountByRate.set(
      item.vatRate,
      (netAmountByRate.get(item.vatRate) ?? 0) + calculateLineNetAmount(item),
    )
  }

  return VAT_RATES.flatMap((vatRate) => {
    const netAmount = netAmountByRate.get(vatRate)

    if (netAmount === undefined) {
      return []
    }

    const vatAmount = roundHalfUp(netAmount * Number(vatRate), 100)

    return [{ vatRate, netAmount, vatAmount, grossAmount: netAmount + vatAmount }]
  })
}

export type VatSummary = {
  lines: readonly VatSummaryLine[]
  netAmount: number
  vatAmount: number
  grossAmount: number
}

export function calculateVatSummary(items: readonly VatCalculationItem[]): VatSummary {
  const lines = summarizeVat(items)

  return {
    lines,
    netAmount: lines.reduce((total, summaryLine) => total + summaryLine.netAmount, 0),
    vatAmount: lines.reduce((total, summaryLine) => total + summaryLine.vatAmount, 0),
    grossAmount: lines.reduce((total, summaryLine) => total + summaryLine.grossAmount, 0),
  }
}
