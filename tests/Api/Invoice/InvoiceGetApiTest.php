<?php

declare(strict_types=1);

namespace App\Tests\Api\Invoice;

use App\DataFixtures\InvoiceFixtures;
use App\Entity\InvoiceItem;
use App\Tests\CustomApiTestCase;
use BcMath\Number;

use function array_first;
use function count;

/**
 * GET /api/invoices/{id}
 */
final class InvoiceGetApiTest extends CustomApiTestCase
{
    public function testItemGetReturnsTheItemsAndTheVatRecapitulation(): void
    {
        $client = self::createClient();

        $draftInvoice = self::getInvoiceEntity(InvoiceFixtures::INVOICE_DRAFT_ULID);
        $expectedRecapitulation = [];

        foreach ($draftInvoice->getVatSummary() as $summaryLine) {
            $expectedRecapitulation[] = [
                'vatRate' => $summaryLine->vatRate->value,
                'netAmount' => $summaryLine->netAmount,
                'vatAmount' => $summaryLine->vatAmount,
                'grossAmount' => $summaryLine->grossAmount,
            ];
        }

        self::assertGreaterThan(1, count($expectedRecapitulation));

        $response = $client->request('GET', '/api/invoices/' . $draftInvoice->getId()->toBase32());
        self::assertResponseIsSuccessful();

        $payload = $response->toArray();
        self::assertIsArray($payload['items']);
        self::assertCount(count($draftInvoice->getItems()), $payload['items']);

        self::assertIsArray($payload['vatSummary']);
        self::assertCount(count($expectedRecapitulation), $payload['vatSummary']);
        self::assertJsonContains(['vatSummary' => $expectedRecapitulation]);
    }

    public function testAnIssuedInvoiceKeepsTheAmountsItWasIssuedFor(): void
    {
        $client = self::createClient();

        $issuedInvoice = self::getInvoiceEntity(InvoiceFixtures::INVOICE_ISSUED_UNPAID_ULID);
        $issuedTotalGrossAmount = $issuedInvoice->getTotalGrossAmount();
        $issuedVatSummary = $issuedInvoice->getVatSummary();
        self::assertNotSame([], $issuedVatSummary);

        $firstItem = array_first($issuedInvoice->getItems());
        self::assertInstanceOf(InvoiceItem::class, $firstItem);
        $firstItem->setQuantity(new Number('999.000'));
        self::getEntityManager()->flush();

        $response = $client->request('GET', '/api/invoices/' . $issuedInvoice->getId()->toBase32());

        self::assertResponseIsSuccessful();
        self::assertSame($issuedTotalGrossAmount, $response->toArray()['totalGrossAmount']);

        $vatSummary = $response->toArray()['vatSummary'];
        self::assertIsArray($vatSummary);
        $firstSummaryLine = $vatSummary[0];
        self::assertIsArray($firstSummaryLine);
        self::assertSame($issuedVatSummary[0]->netAmount, $firstSummaryLine['netAmount']);
    }

    public function testADraftShowsTheAmountsItsItemsCurrentlyAddUpTo(): void
    {
        $client = self::createClient();

        $draftInvoice = self::getInvoiceEntity(InvoiceFixtures::INVOICE_DRAFT_ULID);
        $grossAmountBeforeChange = $draftInvoice->getTotalGrossAmount();
        $firstItem = array_first($draftInvoice->getItems());
        self::assertInstanceOf(InvoiceItem::class, $firstItem);
        $firstItem->setQuantity(new Number('1.000'));
        self::getEntityManager()->flush();
        self::assertNotSame($grossAmountBeforeChange, $draftInvoice->getTotalGrossAmount());

        $response = $client->request('GET', '/api/invoices/' . $draftInvoice->getId()->toBase32());

        self::assertResponseIsSuccessful();
        self::assertSame($draftInvoice->getTotalGrossAmount(), $response->toArray()['totalGrossAmount']);
    }
}
