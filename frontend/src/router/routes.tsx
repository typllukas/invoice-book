import { createBrowserRouter, Outlet, type RouteObject } from 'react-router'
import { AppShell } from '@/layouts/AppShell'
import { NotFoundPage } from '@/pages/error/NotFoundPage'
import { RouteErrorPage } from '@/pages/error/RouteErrorPage'
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
      { path: '*', element: <NotFoundPage /> },
    ],
  },
] satisfies RouteObject[]

export const router = createBrowserRouter(routes)
