import { DatePicker } from '@mui/x-date-pickers/DatePicker'
import { format, isValid, parseISO } from 'date-fns'
import { useState, type FocusEvent } from 'react'

const API_DATE_FORMAT = 'yyyy-MM-dd'

function toShownDate(value: string): Date | null {
  return value === '' ? null : parseISO(value)
}

type CalendarDateFieldProps = {
  label: string
  value: string
  onChange: (value: string) => void
  onBlur?: () => void
  name?: string
  required?: boolean
  error?: boolean
  helperText?: string
}

/**
 * Takes and emits yyyy-MM-dd, and an unfinished date as an empty string.
 * The hidden input carries that value into FormData, the picker's own one holds the shown text.
 */
export function CalendarDateField({
  label,
  value,
  onChange,
  onBlur,
  name,
  required = false,
  error = false,
  helperText,
}: CalendarDateFieldProps) {
  const [shownDate, setShownDate] = useState(() => toShownDate(value))
  const [shownValue, setShownValue] = useState(value)
  const [isOpen, setIsOpen] = useState(false)

  // a half-typed date is kept on screen while the parent holds the empty string
  if (value !== shownValue) {
    setShownValue(value)
    setShownDate(toShownDate(value))
  }

  return (
    <>
      <DatePicker
        label={label}
        format="dd. MM. yyyy"
        value={shownDate}
        open={isOpen}
        onOpen={() => {
          setIsOpen(true)
        }}
        onClose={() => {
          setIsOpen(false)
        }}
        onChange={(date) => {
          const nextValue = date !== null && isValid(date) ? format(date, API_DATE_FORMAT) : ''
          setShownDate(date)
          setShownValue(nextValue)
          onChange(nextValue)
        }}
        slotProps={{
          textField: {
            size: 'small',
            required,
            error,
            helperText,
            sx: { width: 180 },
            onBlur: (event: FocusEvent<HTMLDivElement>) => {
              if (!isOpen && !event.currentTarget.contains(event.relatedTarget)) {
                onBlur?.()
              }
            },
          },
        }}
      />
      {name !== undefined && <input type="hidden" name={name} value={value} />}
    </>
  )
}
