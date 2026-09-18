import { screen } from '@testing-library/react'
import { createMemoryRouter } from 'react-router'
import { expect, test, vi } from 'vitest'
import { routes } from './routes'
import { renderRouterWithProviders } from '@/test/renderWithProviders'

function ThrowingPage(): never {
  throw new Error('Broken page')
}

test('an unknown path renders the not-found page inside the shell', () => {
  const router = createMemoryRouter(routes, { initialEntries: ['/invoices/nonsense/deep'] })

  renderRouterWithProviders(router)

  expect(screen.getByText('Kniha faktur')).toBeInTheDocument()
  expect(screen.getByText('Stránka nenalezena')).toBeInTheDocument()
  expect(screen.getByRole('link', { name: 'Zpět na seznam' })).toBeInTheDocument()
})

test('a page that throws renders the fallback inside the shell, not a stack trace', () => {
  vi.spyOn(console, 'error').mockImplementation(() => undefined)
  const [layoutRoute] = routes
  if (layoutRoute === undefined) {
    throw new Error('The route table has no layout route.')
  }

  const router = createMemoryRouter(
    [{ ...layoutRoute, children: [{ path: '/', element: <ThrowingPage /> }] }],
    { initialEntries: ['/'] },
  )

  renderRouterWithProviders(router)

  expect(screen.getByText('Kniha faktur')).toBeInTheDocument()
  expect(screen.getByText('Aplikace narazila na neočekávanou chybu.')).toBeInTheDocument()
  expect(screen.queryByText(/Unexpected Application Error/u)).toBeNull()
})
