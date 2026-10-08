<?php

declare(strict_types=1);

namespace App\Tests\Api\Invoice;

use App\DataFixtures\InvoiceFixtures;
use App\Enum\InvoiceStatus;
use App\Tests\CustomApiTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * POST /api/invoices/{id}/mark_paid
 */
final class InvoiceMarkPaidApiTest extends CustomApiTestCase
{
    use ClockSensitiveTrait;

    public function testMarkingAnIssuedInvoicePaidRecordsTheMomentInUtc(): void
    {
        $client = self::createClient();
        self::mockTime('2026-09-26T23:30:00+02:00');

        $unpaidInvoice = self::getInvoiceEntity(InvoiceFixtures::INVOICE_ISSUED_UNPAID_ULID);
        self::assertNull($unpaidInvoice->getPaidAt());

        $client->request(
            'POST',
            '/api/invoices/' . $unpaidInvoice->getId()->toBase32() . '/mark_paid',
            ['json' => [], 'headers' => ['Content-Type' => 'application/ld+json']],
        );

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['paidAt' => '2026-09-26T21:30:00+00:00']);
    }

    public function testMarkingAPaidInvoicePaidAgainIsRejected(): void
    {
        $client = self::createClient();

        $paidInvoice = self::getInvoiceEntity(InvoiceFixtures::INVOICE_ISSUED_PAID_ULID);
        self::assertNotNull($paidInvoice->getPaidAt());

        // Exclude the other 422 cause
        self::assertSame(InvoiceStatus::ISSUED, $paidInvoice->getStatus());

        $client->request(
            'POST',
            '/api/invoices/' . $paidInvoice->getId()->toBase32() . '/mark_paid',
            ['json' => [], 'headers' => ['Content-Type' => 'application/ld+json']],
        );

        self::assertResponseStatusCodeSame(422);
    }

    public function testADraftCannotBeMarkedPaid(): void
    {
        $client = self::createClient();

        $draftInvoice = self::getInvoiceEntity(InvoiceFixtures::INVOICE_DRAFT_ULID);
        self::assertSame(InvoiceStatus::DRAFT, $draftInvoice->getStatus());

        $client->request(
            'POST',
            '/api/invoices/' . $draftInvoice->getId()->toBase32() . '/mark_paid',
            ['json' => [], 'headers' => ['Content-Type' => 'application/ld+json']],
        );

        self::assertResponseStatusCodeSame(422);
    }
}
