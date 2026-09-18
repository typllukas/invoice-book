import { expect, test } from 'vitest'
import { toApiError } from '@/api/client/apiError'
import type { ConstraintViolationResponse } from '@/test/invoiceApiFakes'
import { collectFieldErrorsFromViolations } from './fieldErrors'

function createViolationError(propertyPath: string, message: string) {
  return toApiError(422, {
    status: 422,
    detail: `${propertyPath}: ${message}`,
    violations: [{ propertyPath, message }],
  } satisfies ConstraintViolationResponse)
}

test('a violation on an item field lands on the key the form reads it under', () => {
  const error = createViolationError('items[0].quantity', 'Množství musí být kladné.')

  expect(collectFieldErrorsFromViolations(error)).toEqual({ 'items.0.quantity': 'Množství musí být kladné.' })
})

test('a violation on a path no field renders goes to the form-wide message', () => {
  const error = createViolationError('items[0].id', 'Položka k faktuře nepatří.')

  expect(collectFieldErrorsFromViolations(error)).toEqual({ form: 'Položka k faktuře nepatří.' })
})
