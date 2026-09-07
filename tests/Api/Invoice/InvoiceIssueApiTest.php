<?php

declare(strict_types=1);

namespace App\Tests\Api\Invoice;

use App\DataFixtures\InvoiceFixtures;
use App\DTO\Supplier;
use App\Entity\InvoiceItem;
use App\Enum\InvoiceStatus;
use App\Tests\CustomApiTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * POST /api/invoices/{id}/issue
 */
final class InvoiceIssueApiTest extends CustomApiTestCase
{
    use ClockSensitiveTrait;

    public function testADraftIssuedAfterMidnightInPragueIsDatedAndNumberedByThePragueDay(): void
    {
        $client = self::createClient();
        self::mockTime('2026-12-31T23:30:00+00:00');

        $supplier = self::getContainer()->get(Supplier::class);
        $draftInvoice = self::getInvoiceEntity(InvoiceFixtures::INVOICE_DRAFT_ULID);
        self::assertSame(InvoiceStatus::DRAFT, $draftInvoice->getStatus());

        $client->request(
            'POST',
            '/api/invoices/' . $draftInvoice->getId()->toBase32() . '/issue',
            ['json' => [], 'headers' => ['Content-Type' => 'application/ld+json']],
        );

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'status' => InvoiceStatus::ISSUED->value,
            'issuedAt' => '2027-01-01',
            'number' => '2027-000001',
            'variableSymbol' => '2027000001',
            'supplierName' => $supplier->name,
            'supplierBankAccount' => $supplier->bankAccount,
            'supplierRegisterEntry' => $supplier->registerEntry,
        ]);
    }

    public function testTheNextIssueInAYearTakesTheNextNumber(): void
    {
        $client = self::createClient();
        self::mockTime('2025-09-01T10:00:00+00:00');

        $client->request(
            'POST',
            '/api/invoices/' . InvoiceFixtures::INVOICE_DRAFT_ULID . '/issue',
            ['json' => [], 'headers' => ['Content-Type' => 'application/ld+json']],
        );

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['number' => '2025-000003', 'variableSymbol' => '2025000003']);
    }



    public function testIssuingAnAlreadyIssuedInvoiceIsRejected(): void
    {
        self::createClient()->request(
            'POST',
            '/api/invoices/' . InvoiceFixtures::INVOICE_ISSUED_UNPAID_ULID . '/issue',
            ['json' => [], 'headers' => ['Content-Type' => 'application/ld+json']],
        );

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['detail' => 'Vystavit lze jen koncept faktury.']);
    }

    public function testDraftWithoutItemsCannotBeIssued(): void
    {
        $client = self::createClient();

        $draftInvoice = self::getInvoiceEntity(InvoiceFixtures::INVOICE_DRAFT_ULID);
        self::assertSame(InvoiceStatus::DRAFT, $draftInvoice->getStatus());

        foreach ($draftInvoice->getItems() as $item) {
            self::assertInstanceOf(InvoiceItem::class, $item);
            $draftInvoice->removeItem($item);
        }

        self::getEntityManager()->flush();
        self::assertCount(0, $draftInvoice->getItems());

        $client->request(
            'POST',
            '/api/invoices/' . $draftInvoice->getId()->toBase32() . '/issue',
            ['json' => [], 'headers' => ['Content-Type' => 'application/ld+json']],
        );

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['detail' => 'Fakturu bez položek nelze vystavit.']);
    }

}
