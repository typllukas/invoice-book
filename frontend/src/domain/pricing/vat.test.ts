import { expect, test } from 'vitest'
import { z } from 'zod'
// eslint-disable-next-line no-restricted-imports -- shared with the backend test, outside what @/ reaches
import vatCalculatorCases from '../../../../tests/Unit/Helper/vat-calculator-cases.json'
import { parseQuantityToThousandths } from './quantity'
import { calculateVatSummary, VAT_RATES } from './vat'

const vatRateSchema = z.enum(VAT_RATES)

const vatCaseSchema = z.object({
  items: z.array(z.object({ quantity: z.string(), unitPriceNet: z.number(), vatRate: vatRateSchema })),
  lines: z.array(
    z.object({ vatRate: vatRateSchema, netAmount: z.number(), vatAmount: z.number(), grossAmount: z.number() }),
  ),
  netAmount: z.number(),
  vatAmount: z.number(),
  grossAmount: z.number(),
})

const vatCases = Object.entries(z.record(z.string(), vatCaseSchema).parse(vatCalculatorCases))

test.each(vatCases)('%s', (_caseName, vatCase) => {
  const items = vatCase.items.map((item) => ({
    quantityInThousandths: parseQuantityToThousandths(item.quantity) ?? Number.NaN,
    unitPriceNet: item.unitPriceNet,
    vatRate: item.vatRate,
  }))

  expect(calculateVatSummary(items)).toEqual({
    lines: vatCase.lines,
    netAmount: vatCase.netAmount,
    vatAmount: vatCase.vatAmount,
    grossAmount: vatCase.grossAmount,
  })
})
