import createClient from 'openapi-fetch'
import type { paths } from '@/api/schema/schema'

/**
 * The paths carry /api and the Node Request in the tests rejects a relative URL, so the base is the origin.
 * fetch is looked up per call, or openapi-fetch keeps the one from before MSW patched it.
 */
export const apiClient = createClient<paths>({
  baseUrl: window.location.origin,
  headers: { Accept: 'application/ld+json' },
  fetch: (request) => fetch(request),
})
