const MISSING_DATE = '—'

// the server dates an invoice by Invoice::CALENDAR_TIME_ZONE, so the screen prints every date in that zone too
const CALENDAR_TIME_ZONE = 'Europe/Prague'

const dateFormatter = new Intl.DateTimeFormat('cs-CZ', { dateStyle: 'medium', timeZone: CALENDAR_TIME_ZONE })

const momentFormatter = new Intl.DateTimeFormat('cs-CZ', {
  dateStyle: 'medium',
  timeStyle: 'short',
  timeZone: CALENDAR_TIME_ZONE,
})

// sv-SE formats a date as YYYY-MM-DD, the form the API uses for calendar dates
const calendarDateFormatter = new Intl.DateTimeFormat('sv-SE', { timeZone: CALENDAR_TIME_ZONE })

export function readTodayInCalendarZone(): string {
  return calendarDateFormatter.format(new Date())
}

export function formatDate(value: string | null): string {
  return value === null ? MISSING_DATE : dateFormatter.format(new Date(value))
}

export function formatMoment(value: string | null): string {
  return value === null ? MISSING_DATE : momentFormatter.format(new Date(value))
}
