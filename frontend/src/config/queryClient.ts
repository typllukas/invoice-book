import { QueryClient } from '@tanstack/react-query'
import { ZodError } from 'zod'
import { ApiError } from '@/api/client/apiError'

/**
 * staleTime 0 would refetch on every tab switch, and a retried 4xx or a response that failed its
 * schema only repeats the same answer.
 */
export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 30_000,
      retry: (failureCount, error) =>
        !(error instanceof ZodError) &&
        !(error instanceof ApiError && error.isClientError) &&
        failureCount < 2,
    },
    mutations: { retry: false },
  },
})
