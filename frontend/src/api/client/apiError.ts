import { z } from 'zod'

const violationSchema = z.object({ propertyPath: z.string(), message: z.string() })

const apiErrorBodySchema = z.object({
  detail: z.string().optional(),
  violations: z.array(violationSchema).optional(),
})

export type Violation = z.infer<typeof violationSchema>

/**
 * What every failed API call throws: instanceof tells it apart from a network or parse failure.
 */
export class ApiError extends Error {
  constructor(
    readonly status: number,
    readonly userMessage: string | null,
    readonly violations: readonly Violation[],
  ) {
    super(`The API answered ${String(status)}.`)
    this.name = 'ApiError'
  }

  get isClientError(): boolean {
    return this.status >= 400 && this.status < 500
  }
}

export function toApiError(status: number, body: unknown): ApiError {
  const parsed = apiErrorBodySchema.safeParse(body)

  return new ApiError(
    status,
    status === 422 && parsed.success ? (parsed.data.detail ?? null) : null,
    parsed.success ? (parsed.data.violations ?? []) : [],
  )
}

export function describeFailure(error: unknown, fallback: string): string {
  return error instanceof ApiError && error.userMessage !== null ? error.userMessage : fallback
}
