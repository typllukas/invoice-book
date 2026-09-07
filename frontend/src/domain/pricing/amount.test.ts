import { expect, test } from 'vitest'
import { formatMinorUnits, formatMinorUnitsForInput, parseAmountToMinorUnits } from './amount'

test('an amount reads a Czech decimal comma and a thousands space', () => {
  expect(parseAmountToMinorUnits('1 500,00')).toBe(150000)
  expect(parseAmountToMinorUnits('1500.5')).toBe(150050)
  expect(parseAmountToMinorUnits('0,01')).toBe(1)
})

test('anything that is not an amount is refused', () => {
  expect(parseAmountToMinorUnits('')).toBeNull()
  expect(parseAmountToMinorUnits('1,005')).toBeNull()
  expect(parseAmountToMinorUnits('abc')).toBeNull()
})

test('haléře are shown as crowns with two decimals', () => {
  expect(formatMinorUnits(150050)).toBe('1 500,50 Kč')
})

test('a saved amount survives the round trip through the price field', () => {
  for (const minorUnits of [0, 1, 99, 100, 150050, 123456789]) {
    expect(parseAmountToMinorUnits(formatMinorUnitsForInput(minorUnits))).toBe(minorUnits)
  }
  expect(formatMinorUnitsForInput(123456789)).toBe('1234567,89')
})
