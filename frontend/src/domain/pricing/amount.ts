import { parseScaledInteger } from './decimal'

const currencyFormatter = new Intl.NumberFormat('cs-CZ', { style: 'currency', currency: 'CZK' })

const inputFormatter = new Intl.NumberFormat('cs-CZ', {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
  useGrouping: false,
})

export function formatMinorUnits(minorUnits: number): string {
  return currencyFormatter.format(minorUnits / 100)
}

export function formatMinorUnitsForInput(minorUnits: number): string {
  return inputFormatter.format(minorUnits / 100)
}

export function parseAmountToMinorUnits(value: string): number | null {
  return parseScaledInteger(value, 2)
}
