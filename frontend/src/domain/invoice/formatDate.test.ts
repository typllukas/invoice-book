import { afterAll, beforeAll, expect, test, vi } from 'vitest'

// read in Los Angeles, where the reader's day differs from the Prague one the invoice is dated by
beforeAll(() => {
  vi.stubEnv('TZ', 'America/Los_Angeles')
})

afterAll(() => {
  vi.unstubAllEnvs()
})

// a static import would build the formatters in Prague, before the zone above is set
async function importDateFormatters() {
  return import('./formatDate')
}

test('a calendar date keeps its day in any time zone', async () => {
  const { formatDate } = await importDateFormatters()

  expect(formatDate('2026-09-26')).toBe('26. 9. 2026')
})

test('a moment is dated by the Prague calendar wherever it is read', async () => {
  const { formatDate } = await importDateFormatters()

  expect(formatDate('2026-08-19T23:30:00+00:00')).toBe('20. 8. 2026')
})

test('a moment with its time is printed on the Prague clock wherever it is read', async () => {
  const { formatMoment } = await importDateFormatters()

  expect(formatMoment('2026-08-19T23:30:00+00:00')).toBe('20. 8. 2026 1:30')
})

test('today is the Prague day, which is already tomorrow for a reader in Los Angeles in the evening', async () => {
  const { readTodayInCalendarZone } = await importDateFormatters()
  vi.useFakeTimers({ toFake: ['Date'] })
  vi.setSystemTime(new Date('2026-09-26T22:30:00+00:00'))

  expect(readTodayInCalendarZone()).toBe('2026-09-27')

  vi.useRealTimers()
})
