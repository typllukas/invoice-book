import { createBrowserRouter, Outlet, type RouteObject } from 'react-router'
import { AppShell } from '@/layouts/AppShell'
import { NotFoundPage } from '@/pages/error/NotFoundPage'
import { RouteErrorPage } from '@/pages/error/RouteErrorPage'
import { InvoicePage } from '@/pages/invoice/InvoicePage'
import { InvoiceListPage } from '@/pages/invoice/InvoiceListPage'

export const routes = [
  {
    element: (
      <AppShell>
        <Outlet />
      </AppShell>
    ),
    errorElement: (
      <AppShell>
        <RouteErrorPage />
      </AppShell>
    ),
    children: [
      { path: '/', element: <InvoiceListPage /> },
      { path: '/invoices/new', element: <InvoicePage /> },
      { path: '/invoices/:invoiceId', element: <InvoicePage /> },
      { path: '*', element: <NotFoundPage /> },
    ],
  },
] satisfies RouteObject[]

export const router = createBrowserRouter(routes)
