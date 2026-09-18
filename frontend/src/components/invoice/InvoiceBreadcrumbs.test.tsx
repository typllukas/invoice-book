import { screen, within } from '@testing-library/react'
import { expect, test } from 'vitest'
import { renderWithProviders } from '@/test/renderWithProviders'
import { InvoiceBreadcrumbs } from './InvoiceBreadcrumbs'

test('the trail is a named navigation whose last item is the current page and its heading', () => {
  renderWithProviders(<InvoiceBreadcrumbs heading="Faktura 2026-000007" />)

  const breadcrumbNavigation = screen.getByRole('navigation', { name: 'Drobečková navigace' })
  expect(within(breadcrumbNavigation).getByRole('link', { name: 'Seznam faktur' })).toHaveAttribute('href', '/')
  expect(within(breadcrumbNavigation).getByRole('heading', { level: 1, name: 'Faktura 2026-000007' })).toHaveAttribute(
    'aria-current',
    'page',
  )
})
