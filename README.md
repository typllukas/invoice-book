# invoice-book

[![check](https://github.com/typllukas/invoice-book/actions/workflows/check.yml/badge.svg)](https://github.com/typllukas/invoice-book/actions/workflows/check.yml)

Invoicing under Czech VAT rules, covering the full lifecycle: a draft, an issued invoice with its
number and PDF, and finally its payment status. React 19 and TypeScript on top of Symfony 7.4 with
API Platform 4.3, in a single repository started with one command. A reference project for React
over API Platform, not a product. PHP 8.5, MariaDB 11.4, PHPStan level 10.

The frontend types are generated from the OpenAPI document published by API Platform, and responses
are validated with Zod at the boundary: the types enforce the contract at build time, the validation
at runtime.

![Invoice list: drafts without a number, issued invoices unpaid or paid, filters by client, status
and issue date above](docs/screenshot.png)

![Issued and paid invoice: supplier and client, dates, line items and the VAT recapitulation, with
the issued and paid stamps](docs/screenshot-detail.png)

## Features

- **Invoice list** with filtering, sorting and pagination
- **Invoice form** with line items, VAT rates of 21 %, 12 % or exempt, and a VAT recapitulation
  recalculated as the user types
- **Issuing** from both the list and the form, subject to confirmation
- **Marking as paid**, applied optimistically in the query cache, since the whole list row shows it,
  and reverted with the server's reason on rejection
- **Issued invoice** rendered on screen and as a PDF

## Running it

Requires only Docker with the Compose plugin; nothing is installed on the host. Make is optional.

```bash
git clone https://github.com/typllukas/invoice-book.git
cd invoice-book
make setup   # containers, dependencies, schema, 245 demo invoices
```

- application: `http://127.0.0.1:5173`
- Swagger UI: `http://127.0.0.1:8082/api/docs`

`make check` runs the complete quality gate for backend and frontend, identical to the one in GitHub
Actions.

Without Make, the equivalent setup on a fresh clone:

```bash
export HOST_UID="$(id -u)" HOST_GID="$(id -g)"
docker compose up -d --wait
docker compose exec --user "$HOST_UID:$HOST_GID" php composer install --no-interaction
docker compose exec --user "$HOST_UID:$HOST_GID" php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec --user "$HOST_UID:$HOST_GID" php php bin/console doctrine:fixtures:load --no-interaction
```

### Worth trying

- **Marking as paid.** Filter the list to unpaid invoices (**Stav: Neuhrazeno**) and press **Označit jako
  uhrazenou**. The invoice is marked paid immediately, receives a stamp and leaves the filtered list
- **Two tabs.** Open the same draft in two tabs, issue it in the first, then save it in the second.
  The save is rejected, since an issued invoice is immutable
- **VAT as you type.** In **Nová faktura**, add items at different VAT rates and observe the totals.
  Leaving the form with unsaved changes requires confirmation
- **PDF.** Open an issued invoice and press **Stáhnout PDF**

## Money, VAT and dates

- **Amounts** are stored as `DECIMAL` and handled as integer "haléře" in PHP and TypeScript. The VAT
  recapitulation sums the bases per rate and calculates VAT from each sum
- **Issuing** stores a snapshot of the supplier details, the line amounts and the VAT
  recapitulation, and the invoice detail and the PDF are rendered from it. After that, only the
  payment status can change
- **State changes** (issue, edit, delete, mark as paid) lock the invoice row and check the state
  inside the transaction. Tests cover a double issue and a save running concurrently with issuing
- **Dates.** Issue date, tax point and due date are calendar dates in the Prague time zone, the
  payment timestamp is stored in UTC. An invoice issued shortly after midnight on 1 January gets
  that date and a number from the new year's series

## Why a counter row for invoice numbers

The series must have neither gaps nor duplicates, even under concurrent issuing. Rejected
alternatives:

- **number at creation**: every deleted draft leaves a gap
- **`AUTO_INCREMENT` or `SEQUENCE`**: a value taken in a rolled-back transaction is lost, and
  neither restarts per year by itself
- **highest number plus one, under `FOR UPDATE`**: before the year's first invoice there is no row
  to lock, and of two concurrent first issues one ends in a deadlock (reproduced on MariaDB 11.4)

Each year has one counter row, locked in the issuing transaction. Odoo's `no_gap` sequences lock
their row the same way.
`YYYY-NNNNNN` has ten digits, the maximum length of a Czech variable symbol.

## Why the whole invoice is saved with one `PUT`

The form sends the invoice together with its items in one request, saved in one transaction.
The alternative was API Platform's embedded writes, which match items by `@id`. They accept an item
from another invoice and move it over, and with merge-patch they delete and recreate the kept items
([api-platform/core#5559](https://github.com/api-platform/core/issues/5559)). Both still hold in
API Platform 4.3.18.
`InvoiceInputMapper` matches items by `id` within the invoice being saved.

## State in React

Server data is held by TanStack Query. The list filter is in the URL and is part of the query key,
so going back to a previous filter shows cached results right away. The VAT recapitulation is
calculated during render, the form is reset by changing its `key`, and the router's blocker handles
unsaved changes. There is one `useEffect`, the debounce timer of the client filter.

## What it is not

- Not multi-user. There is no login, so no voters and no per-user data
- Not a complete invoicing system. It invoices Czech companies in CZK only (IČO required, DIČ
  `CZ…`), with no credit notes, reverse charge or bank reconciliation. The payment date is the
  moment someone marks the invoice paid
- Not a document store. The PDF is rendered on demand from the issued invoice's stored data, so it
  always comes out the same

## Endpoints

```
GET    /api/invoices                   filtering, sorting, 30 per page
POST   /api/invoices                   creates a draft with its items
GET    /api/invoices/{id}              one invoice with its VAT recapitulation
PUT    /api/invoices/{id}              saves a draft as a whole, items included
DELETE /api/invoices/{id}              deletes a draft
POST   /api/invoices/{id}/issue        numbers and freezes a draft
POST   /api/invoices/{id}/mark_paid    marks an issued invoice as paid now
GET    /api/invoices/{id}/pdf          the issued invoice as a PDF, base64-encoded in JSON
```
