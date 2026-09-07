import { expect, test } from 'vitest'
import { z } from 'zod'
import { ApiError } from '@/api/client/apiError'
import { queryClient } from './queryClient'

function readRetryRule() {
  const retryRule = queryClient.getDefaultOptions().queries?.retry
  if (typeof retryRule !== 'function') {
    throw new Error('The query client has no retry rule.')
  }

  return retryRule
}

test('a response that fails its schema is not retried', () => {
  const schemaFailure = z.string().safeParse(42).error
  if (schemaFailure === undefined) {
    throw new Error('The schema accepted a number.')
  }

  expect(readRetryRule()(0, schemaFailure)).toBe(false)
})

test('a refused request is not retried', () => {
  expect(readRetryRule()(0, new ApiError(422, null, []))).toBe(false)
  expect(readRetryRule()(0, new ApiError(404, null, []))).toBe(false)
})

test('a server or network failure is retried twice, then given up', () => {
  const retryRule = readRetryRule()
  const serverFailure = new ApiError(503, null, [])

  expect(retryRule(0, serverFailure)).toBe(true)
  expect(retryRule(1, serverFailure)).toBe(true)
  expect(retryRule(2, serverFailure)).toBe(false)
  expect(retryRule(0, new TypeError('Failed to fetch'))).toBe(true)
})
