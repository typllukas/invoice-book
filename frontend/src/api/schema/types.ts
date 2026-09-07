import type { components, paths } from './schema'

type Schemas = components['schemas']

export type InvoiceRow = Schemas['Invoice.jsonld-invoice.read_invoice.operation.get_collection']

export type InvoiceCollectionResponse =
  paths['/api/invoices']['get']['responses'][200]['content']['application/ld+json']

export type InvoiceDetail = Schemas['Invoice.jsonld-invoice.read_invoice.operation.get']

export type InvoiceInput = Schemas['Invoice.InvoiceInput']

export type InvoicePdf = Schemas['Invoice.InvoicePdf.jsonld-invoice_pdf.read']

export type InvoiceListQuery = NonNullable<paths['/api/invoices']['get']['parameters']['query']>
