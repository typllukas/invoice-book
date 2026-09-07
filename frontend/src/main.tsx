import '@fontsource-variable/source-sans-3/wght.css'
import CssBaseline from '@mui/material/CssBaseline'
import { ThemeProvider } from '@mui/material/styles'
import { QueryClientProvider } from '@tanstack/react-query'
import { MotionConfig } from 'motion/react'
import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { RouterProvider } from 'react-router'
import { AppErrorBoundary } from './components/common/error/AppErrorBoundary'
import { queryClient } from './config/queryClient'
import { router } from './router/routes'
import { theme } from './config/theme'
import { ToastProvider } from './components/common/ToastProvider'

const rootElement = document.getElementById('root')

if (rootElement === null) {
  throw new Error('index.html has no #root element.')
}

createRoot(rootElement).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <ThemeProvider theme={theme}>
        <CssBaseline />
        <MotionConfig reducedMotion="user">
          <ToastProvider>
            <AppErrorBoundary>
              <RouterProvider router={router} />
            </AppErrorBoundary>
          </ToastProvider>
        </MotionConfig>
      </ThemeProvider>
    </QueryClientProvider>
  </StrictMode>,
)
