import { screen, within } from '@testing-library/react'
import { expect, test } from 'vitest'
import type { InvoiceDetail } from '@/api/schema/types'
import { issuedInvoiceDetail } from '@/test/invoiceApiFakes'
import { renderWithProviders } from '@/test/renderWithProviders'
import { InvoiceDocument } from './InvoiceDocument'

function renderDocument(invoice: InvoiceDetail) {
  renderWithProviders(
    <InvoiceDocument
      invoice={invoice}
      isMarkingPaid={false}
      isDownloadingPdf={false}
      animateIssuedStamp={false}
      animatePaidStamp={false}
      onMarkPaid={() => undefined}
      onDownloadPdf={() => undefined}
    />,
  )
}

test('an issued invoice is printed as a document, with nothing to edit', () => {
  renderDocument(issuedInvoiceDetail)

  expect(screen.getByRole('heading', { name: 'Faktura 2026-000007' })).toBeInTheDocument()
  expect(screen.getByText('Vzorová dodavatelská s.r.o.')).toBeInTheDocument()
  expect(screen.getByText('Novák a syn s.r.o.')).toBeInTheDocument()
  expect(screen.getByText('2026000007')).toBeInTheDocument()
  expect(screen.queryByText('Plnění je osvobozeno od daně.')).toBeNull()
  expect(screen.getByText('8 hod')).toBeInTheDocument()
  expect(within(screen.getByRole('row', { name: /^Konzultace/u })).getByText('12 000,00 Kč')).toBeInTheDocument()
  expect(screen.queryAllByRole('textbox')).toHaveLength(0)
  expect(screen.getByRole('button', { name: 'Označit jako uhrazenou' })).toBeInTheDocument()
})

test('a paid invoice offers no payment button', () => {
  renderDocument({ ...issuedInvoiceDetail, paidAt: '2026-08-20T10:00:00+00:00' })

  expect(screen.queryByRole('button', { name: 'Označit jako uhrazenou' })).not.toBeInTheDocument()
})

test('an issued invoice carries its stamp for good', () => {
  renderDocument(issuedInvoiceDetail)

  expect(screen.getByRole('img', { name: 'Razítko Vystaveno 2026-000007' })).toBeInTheDocument()
  expect(screen.queryByRole('img', { name: /^Razítko Uhrazeno/u })).not.toBeInTheDocument()
})

test('a paid invoice carries a second stamp with the payment date', () => {
  renderDocument({ ...issuedInvoiceDetail, paidAt: '2026-08-20T10:00:00+00:00' })

  expect(screen.getByRole('img', { name: 'Razítko Vystaveno 2026-000007' })).toBeInTheDocument()
  expect(screen.getByRole('img', { name: 'Razítko Uhrazeno 20. 8. 2026' })).toBeInTheDocument()
})

test('a document with an exempt line says the supply is exempt, as a tax document must', () => {
  renderDocument({
        ...issuedInvoiceDetail,
        items: [
          {
            id: '01K0000000000000000000000C',
            description: 'Kniha',
            quantity: '8.000',
            unit: 'ks',
            vatRate: '0',
            unitPriceNet: 150000,
            netAmount: 1200000,
          },
        ],
        vatSummary: [{ vatRate: '0', netAmount: 1200000, vatAmount: 0, grossAmount: 1200000 }],
        totalVatAmount: 0,
        totalGrossAmount: 1200000,
      })

  expect(screen.getByText('Plnění je osvobozeno od daně.')).toBeInTheDocument()
  const exemptSummaryRow = screen.getByRole('row', { name: /^osvobozeno/u })
  expect(within(exemptSummaryRow).getAllByText('12 000,00 Kč')).toHaveLength(2)
})
