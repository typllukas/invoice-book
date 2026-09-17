<?php

declare(strict_types=1);

namespace App\Tests\Api\Invoice;

use App\DataFixtures\InvoiceFixtures;
use App\Enum\InvoiceStatus;
use App\Helper\MixedToString;
use App\Tests\CustomApiTestCase;

use function base64_decode;

/**
 * GET /api/invoices/{id}/pdf
 */
final class InvoicePdfApiTest extends CustomApiTestCase
{
    public function testAnIssuedInvoiceComesAsAPdfNamedByItsNumber(): void
    {
        $client = self::createClient();

        $issuedInvoice = self::getInvoiceEntity(InvoiceFixtures::INVOICE_ISSUED_PAID_ULID);
        self::assertSame(InvoiceStatus::ISSUED, $issuedInvoice->getStatus());

        $response = $client->request('GET', '/api/invoices/' . InvoiceFixtures::INVOICE_ISSUED_PAID_ULID . '/pdf');

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['name' => 'faktura-' . $issuedInvoice->getNumber() . '.pdf']);
        $pdfContent = base64_decode(MixedToString::transformStrict($response->toArray()['base64Content']), true);
        self::assertIsString($pdfContent);
        self::assertStringStartsWith('%PDF-', $pdfContent);
    }

    public function testADraftHasNoPdf(): void
    {
        self::assertSame(
            InvoiceStatus::DRAFT,
            self::getInvoiceEntity(InvoiceFixtures::INVOICE_DRAFT_ULID)->getStatus(),
        );

        self::createClient()->request('GET', '/api/invoices/' . InvoiceFixtures::INVOICE_DRAFT_ULID . '/pdf');

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['detail' => 'Koncept není daňový doklad, PDF má jen vystavená faktura.']);
    }
}
