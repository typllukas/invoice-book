import { useState, type ChangeEvent, type FocusEvent } from 'react'
import { collectFieldErrorsFromZod, type FieldErrors } from '@/domain/invoice/form/fieldErrors'
import { invoiceFormSchema, invoiceItemSchema } from '@/domain/invoice/form/invoiceFormSchema'
import {
  readHeaderValues,
  toInvoiceInput,
  toItemInput,
  type InvoiceItemDraft,
} from '@/domain/invoice/form/invoiceFormValues'

function readFieldName(target: EventTarget): string | null {
  const isNamedField =
    (target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement) && target.name !== ''

  return isNamedField ? target.name : null
}

function toStableFieldName(fieldName: string, itemKeys: readonly string[]): string {
  return fieldName.replace(/^items\.(\d+)\./u, (_itemPrefix, index: string) => `items.${itemKeys[Number(index)] ?? index}.`)
}

export function useLiveFieldErrors(items: readonly InvoiceItemDraft[], submittedFieldErrors: FieldErrors) {
  const [touchedFields, setTouchedFields] = useState<ReadonlySet<string>>(new Set())
  const [submittedItemKeys, setSubmittedItemKeys] = useState<readonly string[]>([])
  const [liveHeaderErrors, setLiveHeaderErrors] = useState<ReadonlyMap<string, string>>(new Map())
  const itemKeys = items.map((item) => item.key)

  const submittedErrorsByStableFieldName: ReadonlyMap<string, string> = new Map(
    Object.entries(submittedFieldErrors).map(([fieldName, message]) => [
      toStableFieldName(fieldName, submittedItemKeys),
      message,
    ]),
  )

  const liveItemErrors: ReadonlyMap<string, string> = new Map(
    items.flatMap((item, index) => {
      const parsed = invoiceItemSchema.safeParse(toItemInput(item))

      return parsed.success
        ? []
        : Object.entries(collectFieldErrorsFromZod(parsed.error)).map(
            ([key, message]): [string, string] => [`items.${index}.${key}`, message],
          )
    }),
  )

  const readFieldError = (fieldName: string): string | undefined => {
    const stableFieldName = toStableFieldName(fieldName, itemKeys)

    if (!touchedFields.has(stableFieldName)) {
      return submittedErrorsByStableFieldName.get(stableFieldName)
    }

    return fieldName.startsWith('items.') ? liveItemErrors.get(fieldName) : liveHeaderErrors.get(fieldName)
  }

  const validateHeaderField = (formData: FormData, fieldName: string) => {
    const parsed = invoiceFormSchema.safeParse(toInvoiceInput(readHeaderValues(formData), items))
    const fieldError = parsed.success ? undefined : collectFieldErrorsFromZod(parsed.error)[fieldName]
    const nextErrors = new Map(liveHeaderErrors)

    if (fieldError === undefined) {
      nextErrors.delete(fieldName)
    } else {
      nextErrors.set(fieldName, fieldError)
    }
    setLiveHeaderErrors(nextErrors)
  }

  const touchField = (formData: FormData, fieldName: string) => {
    setTouchedFields(new Set(touchedFields).add(toStableFieldName(fieldName, itemKeys)))
    if (!fieldName.startsWith('items.')) {
      validateHeaderField(formData, fieldName)
    }
  }

  const recheckCorrectedValue = (formData: FormData, fieldName: string) => {
    if (readFieldError(fieldName) !== undefined) {
      touchField(formData, fieldName)
    }
  }

  return {
    readFieldError,
    markFormSubmitted: () => {
      setTouchedFields(new Set())
      setSubmittedItemKeys(itemKeys)
    },
    revealErrorOfLeftField: (event: FocusEvent<HTMLFormElement>) => {
      const fieldName = readFieldName(event.target)
      if (fieldName !== null) {
        touchField(new FormData(event.currentTarget), fieldName)
      }
    },
    revealErrorOfField: touchField,
    recheckCorrectedField: (event: ChangeEvent<HTMLFormElement>) => {
      const fieldName = readFieldName(event.target)
      if (fieldName !== null) {
        recheckCorrectedValue(new FormData(event.currentTarget), fieldName)
      }
    },
    recheckCorrectedValue,
  }
}
