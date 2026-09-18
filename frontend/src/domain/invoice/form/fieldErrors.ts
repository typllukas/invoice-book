import type { ZodError } from 'zod'
import { ApiError, describeFailure } from '@/api/client/apiError'
import type { InvoiceInput } from '@/api/schema/types'

export type FieldErrors = Readonly<Record<string, string>>

/**
 * items[0].quantity from the API becomes items.0.quantity, the key Zod uses.
 */
function normalizePath(propertyPath: string): string {
  return propertyPath.replace(/\[(\d+)\]/gu, '.$1')
}

const INVOICE_FIELD_NAMES = [
  'clientName',
  'clientAddress',
  'clientCompanyId',
  'clientVatId',
  'taxPointAt',
  'dueAt',
  'note',
  'items',
] as const satisfies readonly (keyof InvoiceInput)[]

const ITEM_FIELD_NAMES = [
  'description',
  'quantity',
  'unit',
  'unitPriceNet',
  'vatRate',
] as const satisfies readonly (keyof NonNullable<InvoiceInput['items']>[number])[]

// every key the form shows an error on; a violation on any other path goes to the toast
const RENDERED_FIELD_PATTERN = new RegExp(
  `^(${INVOICE_FIELD_NAMES.join('|')}|items\\.\\d+\\.(${ITEM_FIELD_NAMES.join('|')}))$`,
  'u',
)

export function collectFieldErrorsFromZod(error: ZodError): FieldErrors {
  const fieldErrors: Record<string, string> = {}

  for (const issue of error.issues) {
    const key = issue.path.map((segment) => String(segment)).join('.')
    fieldErrors[key] ??= issue.message
  }

  return fieldErrors
}

export function collectFieldErrorsFromViolations(error: unknown): FieldErrors {
  if (!(error instanceof ApiError) || error.violations.length === 0) {
    return { form: describeFailure(error, 'Fakturu se nepodařilo uložit.') }
  }

  const fieldErrors: Record<string, string> = {}

  for (const violation of error.violations) {
    const key = normalizePath(violation.propertyPath)

    if (RENDERED_FIELD_PATTERN.test(key)) {
      fieldErrors[key] ??= violation.message
    } else {
      fieldErrors.form ??= violation.message
    }
  }

  return fieldErrors
}
