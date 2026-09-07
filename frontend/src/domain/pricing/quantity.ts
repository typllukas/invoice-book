import { parseScaledInteger } from './decimal'

const quantityFormatter = new Intl.NumberFormat('cs-CZ', { maximumFractionDigits: 3 })

export function parseQuantityToThousandths(value: string): number | null {
  return parseScaledInteger(value, 3)
}

/** The API's "2.000" as a person reads it, so a document does not print trailing zeros. */
export function formatQuantity(quantity: string): string {
  return quantityFormatter.format(Number(quantity))
}

export function formatThousandthsForApi(quantityInThousandths: number): string {
  const wholeUnits = Math.floor(quantityInThousandths / 1000)

  return `${String(wholeUnits)}.${String(quantityInThousandths % 1000).padStart(3, '0')}`
}
