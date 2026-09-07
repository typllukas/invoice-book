<?php

declare(strict_types=1);

namespace App\Tests\Api\Invoice;

use App\DataFixtures\InvoiceFixtures;
use App\Entity\Invoice;
use App\Enum\InvoiceStatus;
use App\Tests\CustomApiTestCase;

use function array_find;
use function count;
use function is_array;
use function rsort;
use function sort;

/**
 * GET /api/invoices
 */
final class InvoiceGetCollectionApiTest extends CustomApiTestCase
{
    public function testCollectionListsAllInvoicesNewestFirst(): void
    {
        $response = self::createClient()->request('GET', '/api/invoices');
        self::assertResponseIsSuccessful();

        $invoiceCount = self::getEntityManager()->getRepository(Invoice::class)->count([]);
        self::assertGreaterThan(1, $invoiceCount);
        self::assertJsonContains(['totalItems' => $invoiceCount]);

        $returnedIds = self::getCollectionMemberValues($response, 'id');
        self::assertCount($invoiceCount, $returnedIds);
        $newestFirstIds = $returnedIds;
        rsort($newestFirstIds);
        self::assertSame($newestFirstIds, $returnedIds);
    }

    public function testCollectionOrdersByNumber(): void
    {
        $response = self::createClient()->request('GET', '/api/invoices?status=issued&order[number]=asc');

        self::assertResponseIsSuccessful();
        $returnedNumbers = self::getCollectionMemberValues($response, 'number');
        self::assertGreaterThan(1, count($returnedNumbers));
        $ascendingNumbers = $returnedNumbers;
        sort($ascendingNumbers);
        self::assertSame($ascendingNumbers, $returnedNumbers);
    }


    public function testDraftHasNoNumberAndNoIssueDate(): void
    {
        $client = self::createClient();

        $draftInvoice = self::getInvoiceEntity(InvoiceFixtures::INVOICE_DRAFT_ULID);
        self::assertSame(InvoiceStatus::DRAFT, $draftInvoice->getStatus());

        $response = $client->request('GET', '/api/invoices?status=draft');

        self::assertResponseIsSuccessful();
        $draftCountInDatabase = self::getEntityManager()
            ->getRepository(Invoice::class)
            ->count(['status' => InvoiceStatus::DRAFT]);
        self::assertJsonContains(['totalItems' => $draftCountInDatabase]);
        $members = $response->toArray()['member'];
        self::assertIsArray($members);
        $draftPayload = array_find(
            $members,
            static fn (mixed $member): bool => is_array($member)
                && $member['id'] === $draftInvoice->getId()->toBase32(),
        );
        self::assertIsArray($draftPayload);
        self::assertNull($draftPayload['number']);
        self::assertNull($draftPayload['issuedAt']);
        self::assertNull($draftPayload['paidAt']);
        self::assertSame($draftInvoice->getTotalGrossAmount(), $draftPayload['totalGrossAmount']);
    }

    public function testUnpaidFilterUsesTheExistsParameter(): void
    {
        $invoiceRepository = self::getEntityManager()->getRepository(Invoice::class);
        $unpaidCount = $invoiceRepository->count(['status' => InvoiceStatus::ISSUED, 'paidAt' => null]);
        self::assertGreaterThan(0, $unpaidCount);
        self::assertGreaterThan($unpaidCount, $invoiceRepository->count(['status' => InvoiceStatus::ISSUED]));

        self::createClient()->request('GET', '/api/invoices?status=issued&exists[paidAt]=false');

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['totalItems' => $unpaidCount]);
    }

    public function testClientSearchIgnoresCaseAndDiacritics(): void
    {
        $client = self::createClient();

        $matchingInvoice = self::getInvoiceEntity(InvoiceFixtures::INVOICE_ISSUED_UNPAID_ULID);
        self::assertStringContainsString('Novák', $matchingInvoice->getClientName());

        $otherInvoice = self::getInvoiceEntity(InvoiceFixtures::INVOICE_DRAFT_ULID);
        self::assertStringNotContainsStringIgnoringCase('novak', $otherInvoice->getClientName());

        $response = $client->request('GET', '/api/invoices?clientName=novak');

        self::assertResponseIsSuccessful();
        $returnedIds = self::getCollectionMemberValues($response, 'id');
        self::assertContains($matchingInvoice->getId()->toBase32(), $returnedIds);
        self::assertNotContains($otherInvoice->getId()->toBase32(), $returnedIds);
    }

    public function testIssuedAfterFilterIncludesTheBoundaryDayAndExcludesTheDayBefore(): void
    {
        $client = self::createClient();

        $issuedOnBoundaryInvoice = self::getInvoiceEntity(InvoiceFixtures::INVOICE_ISSUED_PAID_ULID);
        $boundaryDay = $issuedOnBoundaryInvoice->getIssuedAt();
        self::assertNotNull($boundaryDay);

        $issuedDayBeforeInvoice = self::getInvoiceEntity(InvoiceFixtures::INVOICE_ISSUED_UNPAID_ULID);
        self::assertSame(
            $boundaryDay->modify('-1 day')->format('Y-m-d'),
            $issuedDayBeforeInvoice->getIssuedAt()?->format('Y-m-d'),
        );

        $response = $client->request('GET', '/api/invoices?issuedAt[after]=' . $boundaryDay->format('Y-m-d'));

        self::assertResponseIsSuccessful();
        $returnedIds = self::getCollectionMemberValues($response, 'id');
        self::assertContains($issuedOnBoundaryInvoice->getId()->toBase32(), $returnedIds);
        self::assertNotContains($issuedDayBeforeInvoice->getId()->toBase32(), $returnedIds);
    }

    public function testDueStrictlyBeforeFilterExcludesTheBoundaryDay(): void
    {
        $client = self::createClient();

        $unpaidInvoice = self::getInvoiceEntity(InvoiceFixtures::INVOICE_ISSUED_UNPAID_ULID);
        $dueDay = $unpaidInvoice->getDueAt();

        $response = $client->request('GET', '/api/invoices?dueAt[strictly_before]=' . $dueDay->format('Y-m-d'));

        self::assertResponseIsSuccessful();
        self::assertNotContains($unpaidInvoice->getId()->toBase32(), self::getCollectionMemberValues($response, 'id'));

        $response = $client->request(
            'GET',
            '/api/invoices?dueAt[strictly_before]=' . $dueDay->modify('+1 day')->format('Y-m-d'),
        );

        self::assertResponseIsSuccessful();
        self::assertContains($unpaidInvoice->getId()->toBase32(), self::getCollectionMemberValues($response, 'id'));
    }
}
