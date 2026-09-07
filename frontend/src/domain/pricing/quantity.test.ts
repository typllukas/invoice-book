import { expect, test } from 'vitest'
import { formatQuantity, formatThousandthsForApi, parseQuantityToThousandths } from './quantity'

test('a typed quantity goes out in the API format and reads back as it was typed', () => {
  expect(formatThousandthsForApi(8500)).toBe('8.500')

  for (const typedQuantity of ['8', '8,5', '0,125', '0,05']) {
    const quantityInThousandths = parseQuantityToThousandths(typedQuantity)
    if (quantityInThousandths === null) {
      throw new Error(`The quantity ${typedQuantity} did not parse.`)
    }

    expect(formatQuantity(formatThousandthsForApi(quantityInThousandths))).toBe(typedQuantity)
  }
})

test('a quantity with more than three decimals is refused', () => {
  expect(parseQuantityToThousandths('1,2345')).toBeNull()
})

test('a quantity loses the trailing zeros the API sends', () => {
  expect(formatQuantity('8.000')).toBe('8')
  expect(formatQuantity('2.500')).toBe('2,5')
})
