import { screen, within } from '@testing-library/react'
import { expect, test } from 'vitest'
import type { VatSummary } from '@/domain/pricing/vat'
import { renderWithProviders } from '@/test/renderWithProviders'
import { VatSummaryTable } from './VatSummaryTable'

const twoRateSummary = {
  lines: [
    { vatRate: '21', netAmount: 1200000, vatAmount: 252000, grossAmount: 1452000 },
    { vatRate: '12', netAmount: 2400000, vatAmount: 288000, grossAmount: 2688000 },
  ],
  netAmount: 3600000,
  vatAmount: 540000,
  grossAmount: 4140000,
} satisfies VatSummary

test('each summary line and the total are drawn in their own columns', () => {
  renderWithProviders(<VatSummaryTable summary={twoRateSummary} />)

  const recapitulation = screen.getByRole('table', { name: 'Rekapitulace DPH' })
  const rowTexts = within(recapitulation)
    .getAllByRole('row')
    .map((row) =>
      [...within(row).queryAllByRole('columnheader'), ...within(row).queryAllByRole('cell')].map(
        (cell) => cell.textContent.replace(/\s/gu, ' '),
      ),
    )
  expect(rowTexts).toEqual([
    ['Sazba', 'Základ daně', 'DPH', 'Celkem'],
    ['21 %', '12 000,00 Kč', '2 520,00 Kč', '14 520,00 Kč'],
    ['12 %', '24 000,00 Kč', '2 880,00 Kč', '26 880,00 Kč'],
    ['Celkem', '36 000,00 Kč', '5 400,00 Kč', '41 400,00 Kč'],
  ])
})
