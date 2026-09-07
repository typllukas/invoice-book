import { styled } from '@mui/material/styles'
import { MaterialDesignContent, SnackbarProvider } from 'notistack'
import type { ReactNode } from 'react'

const ThemedToast = styled(MaterialDesignContent)(({ theme }) => ({
  fontFamily: theme.typography.fontFamily,
  fontWeight: 600,
  '&.notistack-MuiContent-success': { backgroundColor: theme.palette.success.main },
  '&.notistack-MuiContent-error': { backgroundColor: theme.palette.error.main },
}))

export function ToastProvider({ children }: { children: ReactNode }) {
  return (
    <SnackbarProvider
      maxSnack={3}
      autoHideDuration={5000}
      anchorOrigin={{ vertical: 'bottom', horizontal: 'center' }}
      Components={{ success: ThemedToast, error: ThemedToast }}
    >
      {children}
    </SnackbarProvider>
  )
}
