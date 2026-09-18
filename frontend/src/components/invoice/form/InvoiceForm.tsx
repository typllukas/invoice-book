import LabelImportantRoundedIcon from '@mui/icons-material/LabelImportantRounded'
import Alert from '@mui/material/Alert'
import Button from '@mui/material/Button'
import Divider from '@mui/material/Divider'
import Stack from '@mui/material/Stack'
import TextField from '@mui/material/TextField'
import { enqueueSnackbar } from 'notistack'
import { useActionState, useId, useState } from 'react'
import { useFormStatus } from 'react-dom'
import { Link as RouterLink, useBeforeUnload, useBlocker, useNavigate } from 'react-router'
import { describeFailure } from '@/api/client/apiError'
import type { InvoiceDetail } from '@/api/schema/types'
import { calculateVatSummary, type VatCalculationItem } from '@/domain/pricing/vat'
import { CalendarDateField } from '@/components/common/CalendarDateField'
import { ConfirmDialog } from '@/components/common/dialog/ConfirmDialog'
import { collectFieldErrorsFromViolations, collectFieldErrorsFromZod, type FieldErrors } from '@/domain/invoice/form/fieldErrors'
import { InvoiceBreadcrumbs } from '@/components/invoice/InvoiceBreadcrumbs'
import { IssueConfirmationDialog } from '@/components/invoice/dialog/IssueConfirmationDialog'
import { invoiceFormSchema } from '@/domain/invoice/form/invoiceFormSchema'
import { wasInvoiceJustSaved, type SavedInvoiceState } from '@/domain/invoice/invoiceNavigationState'
import { InvoiceItemRows } from './InvoiceItemRows'
import {
  createEmptyInvoiceItemDraft,
  readHeaderValues,
  readQuantityInThousandths,
  readUnitPriceNet,
  toInvoiceHeaderValues,
  toInvoiceInput,
  toInvoiceItemDrafts,
  type InvoiceHeaderValues,
  type InvoiceItemDraft,
} from '@/domain/invoice/form/invoiceFormValues'
import { useLiveFieldErrors } from '@/hooks/invoice/useLiveFieldErrors'
import { useIssueInvoice } from '@/api/invoice/useIssueInvoice'
import { useSaveInvoice } from '@/api/invoice/useSaveInvoice'
import { VatSummaryTable } from '@/components/invoice/VatSummaryTable'

const INTENT = { save: 'save', issue: 'issue' } as const

type InvoiceFormState = {
  fieldErrors: FieldErrors
  values: InvoiceHeaderValues
}

type InvoiceFormProps = {
  invoiceId: string | undefined
  heading: string
  initialValues: InvoiceHeaderValues
  initialItems: readonly InvoiceItemDraft[]
}

function SaveButton() {
  const { pending, data } = useFormStatus()

  return (
    <Button
      type="submit"
      name="intent"
      value={INTENT.save}
      variant="contained"
      loading={pending && data?.get('intent') === INTENT.save}
      disabled={pending}
    >
      Uložit
    </Button>
  )
}

function IssueButton({ onClick }: { onClick: () => void }) {
  const { pending, data } = useFormStatus()

  return (
    <Button
      variant="outlined"
      startIcon={<LabelImportantRoundedIcon />}
      loading={pending && data?.get('intent') === INTENT.issue}
      disabled={pending}
      onClick={onClick}
    >
      Vystavit
    </Button>
  )
}

const FORM_HAS_ERRORS = 'Formulář obsahuje chyby, opravte zvýrazněná pole.'

export function InvoiceForm({
  invoiceId,
  heading,
  initialValues,
  initialItems,
}: InvoiceFormProps) {
  const navigate = useNavigate()
  const saveInvoice = useSaveInvoice(invoiceId)
  const issueInvoice = useIssueInvoice()
  const [items, setItems] = useState(initialItems.length === 0 ? [createEmptyInvoiceItemDraft()] : initialItems)
  const [calendarDates, setCalendarDates] = useState({ taxPointAt: initialValues.taxPointAt, dueAt: initialValues.dueAt })
  const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false)
  const [isIssueConfirmationOpen, setIsIssueConfirmationOpen] = useState(false)
  const formId = useId()

  const unsavedChangesBlocker = useBlocker(
    ({ nextLocation }) => hasUnsavedChanges && !wasInvoiceJustSaved(nextLocation.state),
  )

  useBeforeUnload((event) => {
    if (hasUnsavedChanges) {
      event.preventDefault()
    }
  })

  const changeItems = (nextItems: InvoiceItemDraft[]) => {
    setItems(nextItems)
    setHasUnsavedChanges(true)
  }

  const calculationItems: VatCalculationItem[] = items.map((item) => ({
    quantityInThousandths: readQuantityInThousandths(item),
    unitPriceNet: readUnitPriceNet(item),
    vatRate: item.vatRate,
  }))

  const [formState, submitAction] = useActionState(
    async (_previousState: InvoiceFormState, formData: FormData): Promise<InvoiceFormState> => {
      const values = readHeaderValues(formData)

      const parsed = invoiceFormSchema.safeParse(toInvoiceInput(values, items))

      if (!parsed.success) {
        enqueueSnackbar(FORM_HAS_ERRORS, { variant: 'error' })

        return { fieldErrors: collectFieldErrorsFromZod(parsed.error), values }
      }

      let savedInvoice: InvoiceDetail
      try {
        savedInvoice = await saveInvoice.mutateAsync(parsed.data)
      } catch (error) {
        const fieldErrors = collectFieldErrorsFromViolations(error)
        enqueueSnackbar(fieldErrors.form ?? FORM_HAS_ERRORS, { variant: 'error' })

        return { fieldErrors, values }
      }
      setHasUnsavedChanges(false)

      const savedInvoiceId = savedInvoice.id

      if (formData.get('intent') !== INTENT.issue) {
        if (invoiceId === undefined) {
          enqueueSnackbar('Koncept faktury byl vytvořen.', { variant: 'success' })
          await navigate(`/invoices/${savedInvoiceId}`, {
            replace: true,
            state: { invoiceSaved: true } satisfies SavedInvoiceState,
          })

          return { fieldErrors: {}, values }
        }

        enqueueSnackbar('Koncept faktury byl uložen.', { variant: 'success' })
        // saved items get their ids, so the next save updates them instead of adding them again
        setItems(toInvoiceItemDrafts(savedInvoice.items))

        return { fieldErrors: {}, values: toInvoiceHeaderValues(savedInvoice) }
      }

      try {
        await issueInvoice.mutateAsync(savedInvoiceId)
        await navigate(`/invoices/${savedInvoiceId}`, {
          replace: true,
          state: { invoiceSaved: true, invoiceIssued: true } satisfies SavedInvoiceState,
        })

        return { fieldErrors: {}, values }
      } catch (error) {
        enqueueSnackbar(describeFailure(error, 'Fakturu se nepodařilo vystavit.'), { variant: 'error' })

        if (invoiceId === undefined) {
          await navigate(`/invoices/${savedInvoiceId}`, {
            replace: true,
            state: { invoiceSaved: true } satisfies SavedInvoiceState,
          })
        }

        return { fieldErrors: {}, values }
      }
    },
    { fieldErrors: {}, values: initialValues },
  )

  const liveFieldErrors = useLiveFieldErrors(items, formState.fieldErrors)
  const readFieldError = liveFieldErrors.readFieldError

  const bindHeaderField = (name: keyof InvoiceHeaderValues) => ({
    name,
    defaultValue: formState.values[name],
    error: readFieldError(name) !== undefined,
    helperText: readFieldError(name) ?? ' ',
  })

  const readFormDataWith = (fieldName: keyof typeof calendarDates, value: string) => {
    const form = document.getElementById(formId)
    const formData = new FormData(form instanceof HTMLFormElement ? form : undefined)
    formData.set(fieldName, value)

    return formData
  }

  const bindCalendarDateField = (name: keyof typeof calendarDates) => ({
    name,
    value: calendarDates[name],
    onChange: (value: string) => {
      setCalendarDates({ ...calendarDates, [name]: value })
      setHasUnsavedChanges(true)
      liveFieldErrors.recheckCorrectedValue(readFormDataWith(name, value), name)
    },
    onBlur: () => {
      liveFieldErrors.revealErrorOfField(readFormDataWith(name, calendarDates[name]), name)
    },
    error: readFieldError(name) !== undefined,
    helperText: readFieldError(name) ?? ' ',
  })

  return (
    <form
      id={formId}
      action={submitAction}
      onSubmit={liveFieldErrors.markFormSubmitted}
      onBlur={liveFieldErrors.revealErrorOfLeftField}
      onChange={(event) => {
        setHasUnsavedChanges(true)
        liveFieldErrors.recheckCorrectedField(event)
      }}
    >
      <InvoiceBreadcrumbs heading={heading} />

      <Stack sx={{ marginBlockEnd: 3 }}>
        <Stack direction="row" spacing={2}>
          <TextField
            label="Odběratel"
            required
            sx={{ flexGrow: 1 }}
            slotProps={{ htmlInput: { maxLength: 255 } }}
            {...bindHeaderField('clientName')}
          />
          <TextField label="IČO" required inputMode="numeric" {...bindHeaderField('clientCompanyId')} />
          <TextField label="DIČ" {...bindHeaderField('clientVatId')} />
        </Stack>
        <TextField
          label="Adresa"
          required
          slotProps={{ htmlInput: { maxLength: 255 } }}
          {...bindHeaderField('clientAddress')}
        />
        <Stack direction="row" spacing={2}>
          <CalendarDateField label="DUZP" required {...bindCalendarDateField('taxPointAt')} />
          <CalendarDateField label="Datum splatnosti" required {...bindCalendarDateField('dueAt')} />
        </Stack>
        <TextField
          label="Poznámka"
          multiline
          minRows={2}
          slotProps={{ htmlInput: { maxLength: 1000 } }}
          {...bindHeaderField('note')}
        />
      </Stack>

      <Divider sx={{ marginBlockEnd: 2 }} />

      <InvoiceItemRows
        items={items}
        onItemsChange={changeItems}
        readFieldError={readFieldError}
      />

      {formState.fieldErrors.items !== undefined && (
        <Alert severity="error" sx={{ marginBlock: 2 }}>
          {formState.fieldErrors.items}
        </Alert>
      )}

      <Stack sx={{ marginBlockStart: 3, alignItems: 'flex-end' }}>
        <VatSummaryTable summary={calculateVatSummary(calculationItems)} />
      </Stack>

      <Stack
        direction="row"
        spacing={2}
        sx={{ marginBlockStart: 3, justifyContent: 'space-between' }}
      >
        <Button component={RouterLink} to="/" variant="outlined">
          Zrušit
        </Button>
        <Stack direction="row" spacing={2}>
          <SaveButton />
          <IssueButton
            onClick={() => {
              setIsIssueConfirmationOpen(true)
            }}
          />
        </Stack>
      </Stack>

      <IssueConfirmationDialog
        open={isIssueConfirmationOpen}
        confirmButtonProps={{ type: 'submit', form: formId, name: 'intent', value: INTENT.issue }}
        onConfirm={() => {
          setIsIssueConfirmationOpen(false)
        }}
        onCancel={() => {
          setIsIssueConfirmationOpen(false)
        }}
      />

      <ConfirmDialog
        open={unsavedChangesBlocker.state === 'blocked'}
        title="Odejít?"
        message="Máte neuložené změny. Když odejdete, ztratíte je."
        confirmLabel="Odejít"
        cancelLabel="Zůstat"
        confirmColor="error"
        onConfirm={() => {
          unsavedChangesBlocker.proceed?.()
        }}
        onCancel={() => {
          unsavedChangesBlocker.reset?.()
        }}
      />
    </form>
  )
}
