import { ThemeProvider } from '@mui/material/styles'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render } from '@testing-library/react'
import type { ReactElement } from 'react'
import { MemoryRouter, RouterProvider, type DataRouter } from 'react-router'
import { theme } from '@/config/theme'
import { CalendarLocalizationProvider } from '@/components/common/CalendarLocalizationProvider'
import { ToastProvider } from '@/components/common/ToastProvider'

function renderWithoutRouter(element: ReactElement) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  return render(
    <QueryClientProvider client={queryClient}>
      <ThemeProvider theme={theme}>
        <CalendarLocalizationProvider>
          <ToastProvider>{element}</ToastProvider>
        </CalendarLocalizationProvider>
      </ThemeProvider>
    </QueryClientProvider>,
  )
}

export function renderWithProviders(element: ReactElement, initialPath = '/') {
  return renderWithoutRouter(<MemoryRouter initialEntries={[initialPath]}>{element}</MemoryRouter>)
}

export function renderRouterWithProviders(router: DataRouter) {
  return renderWithoutRouter(<RouterProvider router={router} />)
}
