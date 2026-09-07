import '@testing-library/jest-dom/vitest'
import { cleanup } from '@testing-library/react'
import { afterAll, afterEach, beforeAll } from 'vitest'
import { mswServer } from '@/test/mswServer'

beforeAll(() => {
  mswServer.listen({ onUnhandledRequest: 'error' })
})
// Testing Library cleans up by itself only with Vitest globals, which this project does not use
afterEach(() => {
  cleanup()
  mswServer.resetHandlers()
})
afterAll(() => {
  mswServer.close()
})
