<?php

declare(strict_types=1);

namespace App\Tests\Api\Invoice;

use App\DataFixtures\InvoiceFixtures;
use App\Entity\Invoice;
use App\Enum\InvoiceStatus;
use App\Tests\CustomApiTestCase;

/**
 * DELETE /api/invoices/{id}
 */
final class InvoiceDeleteApiTest extends CustomApiTestCase
{
    public function testADraftCanBeDeleted(): void
    {
        $client = self::createClient();

        $draftInvoiceId = self::getInvoiceEntity(InvoiceFixtures::INVOICE_DRAFT_ULID)->getId();

        $client->request('DELETE', '/api/invoices/' . $draftInvoiceId->toBase32());

        self::assertResponseStatusCodeSame(204);
        self::assertNull(self::getEntityManager()->find(Invoice::class, $draftInvoiceId));
    }

    public function testAnIssuedInvoiceCannotBeDeleted(): void
    {
        $client = self::createClient();

        $issuedInvoiceId = self::getInvoiceEntity(InvoiceFixtures::INVOICE_ISSUED_UNPAID_ULID)->getId();

        $client->request('DELETE', '/api/invoices/' . $issuedInvoiceId->toBase32());

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['detail' => 'Smazat lze jen koncept faktury.']);
        self::assertNotNull(self::getEntityManager()->find(Invoice::class, $issuedInvoiceId));
    }

    public function testADraftIssuedByAConcurrentRequestIsNotDeleted(): void
    {
        $client = self::createClient();

        $staleDraftInvoice = self::getInvoiceEntity(InvoiceFixtures::INVOICE_DRAFT_ULID);
        self::getEntityManager()->getConnection()->update(
            'invoice',
            ['status' => InvoiceStatus::ISSUED->value, 'number' => '2025-000003'],
            ['id' => $staleDraftInvoice->getId()->toBinary()],
        );

        $client->request('DELETE', '/api/invoices/' . InvoiceFixtures::INVOICE_DRAFT_ULID);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['detail' => 'Smazat lze jen koncept faktury.']);
        self::assertNotNull(self::getEntityManager()->find(Invoice::class, $staleDraftInvoice->getId()));
    }
}
