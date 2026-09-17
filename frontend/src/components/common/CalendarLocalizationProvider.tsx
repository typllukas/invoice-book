import { AdapterDateFns } from '@mui/x-date-pickers/AdapterDateFns'
import { LocalizationProvider } from '@mui/x-date-pickers/LocalizationProvider'
import { csCZ } from '@mui/x-date-pickers/locales'
import { cs } from 'date-fns/locale/cs'
import type { ReactNode } from 'react'

export function CalendarLocalizationProvider({ children }: { children: ReactNode }) {
  return (
    <LocalizationProvider
      dateAdapter={AdapterDateFns}
      adapterLocale={cs}
      localeText={csCZ.components.MuiLocalizationProvider.defaultProps.localeText}
    >
      {children}
    </LocalizationProvider>
  )
}
