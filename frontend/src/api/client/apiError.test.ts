import { expect, test } from 'vitest'
import { describeFailure, toApiError } from './apiError'

test('a 422 shows the message the backend wrote for the user', () => {
  const apiError = toApiError(422, { detail: 'Faktura je už uhrazená.' })

  expect(describeFailure(apiError, 'Akci se nepodařilo dokončit.')).toBe('Faktura je už uhrazená.')
  expect(apiError.isClientError).toBe(true)
})

test('any other status shows the caller fallback, not the framework text', () => {
  expect(describeFailure(toApiError(404, { detail: 'Not Found' }), 'PDF se nepodařilo stáhnout.')).toBe(
    'PDF se nepodařilo stáhnout.',
  )
  expect(describeFailure(toApiError(500, { detail: 'Internal Server Error' }), 'PDF se nepodařilo stáhnout.')).toBe(
    'PDF se nepodařilo stáhnout.',
  )
})

test('violations are read even when the body has no detail', () => {
  const apiError = toApiError(422, {
    violations: [{ propertyPath: 'items[0].quantity', message: 'Množství musí být kladné.' }],
  })

  expect(apiError.violations).toEqual([
    { propertyPath: 'items[0].quantity', message: 'Množství musí být kladné.' },
  ])
  expect(apiError.userMessage).toBeNull()
})

test('a body that is not an error document still becomes an ApiError', () => {
  expect(toApiError(502, '<html>Bad Gateway</html>').violations).toEqual([])
})
