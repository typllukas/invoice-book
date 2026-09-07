import { render, screen } from '@testing-library/react'
import { createMemoryRouter, RouterProvider } from 'react-router'
import { expect, test, vi } from 'vitest'
import { AppErrorBoundary } from './AppErrorBoundary'

function ThrowingPage(): never {
  throw new Error('Broken page')
}

function ThrowingShell(): never {
  throw new Error('Broken application shell')
}

test('an error the route error page cannot show lands in the app boundary', () => {
  vi.spyOn(console, 'error').mockImplementation(() => undefined)
  const router = createMemoryRouter(
    [{ errorElement: <ThrowingShell />, children: [{ path: '/', element: <ThrowingPage /> }] }],
    { initialEntries: ['/'] },
  )

  render(
    <AppErrorBoundary>
      <RouterProvider router={router} />
    </AppErrorBoundary>,
  )

  expect(screen.getByText('Aplikace narazila na neočekávanou chybu.')).toBeInTheDocument()
  expect(screen.getByRole('link', { name: 'Zpět na seznam' })).toHaveAttribute('href', '/')
  expect(screen.queryByText(/Unexpected Application Error/u)).toBeNull()
})
