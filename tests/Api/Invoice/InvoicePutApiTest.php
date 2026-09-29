<?php

declare(strict_types=1);

namespace App\Tests\Api\Invoice;

use App\DataFixtures\InvoiceFixtures;
use App\Entity\InvoiceItem;
use App\Enum\InvoiceStatus;
use App\Tests\CustomApiTestCase;

use function array_column;
use function array_first;
use function array_last;
use function count;
use function sprintf;

/**
 * PUT /api/invoices/{id}
 *
 * @phpstan-type ItemBody array{
 *     id?: string|null,
 *     description: string,
 *     quantity: string,
 *     unit: string,
 *     unitPriceNet: int,
 *     vatRate: string,
 * }
 * @phpstan-type InvoiceBody array{
 *     clientName: string,
 *     clientAddress: string,
 *     clientCompanyId: string,
 *     clientVatId: null,
 *     dueAt: string,
 *     taxPointAt: string,
 *     note: null,
 *     items: list<ItemBody>,
 * }
 */
final class InvoicePutApiTest extends CustomApiTestCase
{
    /**
     * @param list<ItemBody> $items
     *
     * @return InvoiceBody
     */
    private function buildBodyWithItems(array $items): array
    {
        return [
            'clientName' => 'Test Client',
            'clientAddress' => 'Test Address 1',
            'clientCompanyId' => '00000001',
            'clientVatId' => null,
            'dueAt' => '2026-09-30',
            'taxPointAt' => '2026-09-16',
            'note' => null,
            'items' => $items,
        ];
    }

    public function testPutReplacesTheHeaderAndTheItemCollection(): void
    {
        $client = self::createClient();

        $clientName = 'Test Client Renamed';
        $draftInvoice = self::getInvoiceEntity(InvoiceFixtures::INVOICE_DRAFT_ULID);
        self::assertNotSame($clientName, $draftInvoice->getClientName());
        self::assertGreaterThan(1, count($draftInvoice->getItems()));

        $response = $client->request('PUT', '/api/invoices/' . $draftInvoice->getId()->toBase32(), [
            'json' => ['clientName' => $clientName] + $this->buildBodyWithItems([
                [
                    'description' => 'Test item',
                    'quantity' => '4.000',
                    'unit' => 'hod',
                    'unitPriceNet' => 150000,
                    'vatRate' => '21',
                ],
            ]),
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'clientName' => $clientName,
            'totalNetAmount' => 600000,
            'totalGrossAmount' => 726000,
        ]);
        $items = $response->toArray()['items'];
        self::assertIsArray($items);
        self::assertCount(1, $items);
    }

    public function testAnItemKeepsItsIdentityWhenTheClientSendsItBack(): void
    {
        $client = self::createClient();

        $draftInvoice = self::getInvoiceEntity(InvoiceFixtures::INVOICE_DRAFT_ULID);
        self::assertGreaterThan(1, count($draftInvoice->getItems()));
        $keptItem = array_first($draftInvoice->getItems());
        self::assertInstanceOf(InvoiceItem::class, $keptItem);
        $keptItemId = $keptItem->getId()->toBase32();
        $droppedItem = array_last($draftInvoice->getItems());
        self::assertInstanceOf(InvoiceItem::class, $droppedItem);

        $response = $client->request('PUT', '/api/invoices/' . $draftInvoice->getId()->toBase32(), [
            'json' => $this->buildBodyWithItems([
                [
                    'id' => $keptItemId,
                    'description' => 'Test item edited',
                    'quantity' => '4.000',
                    'unit' => 'hod',
                    'unitPriceNet' => 150000,
                    'vatRate' => '21',
                ],
                [
                    'id' => null,
                    'description' => 'Test new item',
                    'quantity' => '1.000',
                    'unit' => 'ks',
                    'unitPriceNet' => 100000,
                    'vatRate' => '12',
                ],
            ]),
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        self::assertResponseIsSuccessful();

        $items = $response->toArray()['items'];
        self::assertIsArray($items);
        self::assertCount(2, $items);
        self::assertNotContains($droppedItem->getId()->toBase32(), array_column($items, 'id'));

        $keptItemPayload = $items[0];
        self::assertIsArray($keptItemPayload);
        self::assertSame($keptItemId, $keptItemPayload['id']);
        self::assertSame('Test item edited', $keptItemPayload['description']);

        $newItemPayload = $items[1];
        self::assertIsArray($newItemPayload);
        self::assertNotSame($keptItemId, $newItemPayload['id']);
        self::assertSame('Test new item', $newItemPayload['description']);
    }

    public function testAnItemFromAnotherInvoiceIsRefused(): void
    {
        $client = self::createClient();

        $foreignItem = array_first(self::getInvoiceEntity(InvoiceFixtures::INVOICE_ISSUED_UNPAID_ULID)->getItems());
        self::assertInstanceOf(InvoiceItem::class, $foreignItem);
        $foreignItemId = $foreignItem->getId()->toBase32();

        $client->request('PUT', '/api/invoices/' . InvoiceFixtures::INVOICE_DRAFT_ULID, [
            'json' => $this->buildBodyWithItems([
                [
                    'id' => $foreignItemId,
                    'description' => 'Test foreign item',
                    'quantity' => '1.000',
                    'unit' => 'ks',
                    'unitPriceNet' => 100000,
                    'vatRate' => '21',
                ],
            ]),
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['detail' => sprintf('Položka %s na této faktuře není.', $foreignItemId)]);
    }

    public function testAnItemSentTwiceIsRefusedRatherThanMerged(): void
    {
        $client = self::createClient();

        $draftItem = array_first(self::getInvoiceEntity(InvoiceFixtures::INVOICE_DRAFT_ULID)->getItems());
        self::assertInstanceOf(InvoiceItem::class, $draftItem);
        $draftItemId = $draftItem->getId()->toBase32();
        $itemBody = [
            'id' => $draftItemId,
            'description' => 'Test item',
            'quantity' => '1.000',
            'unit' => 'ks',
            'unitPriceNet' => 100000,
            'vatRate' => '21',
        ];

        $client->request('PUT', '/api/invoices/' . InvoiceFixtures::INVOICE_DRAFT_ULID, [
            'json' => $this->buildBodyWithItems([$itemBody, ['description' => 'Test item again'] + $itemBody]),
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['detail' => sprintf('Položka %s je v požadavku dvakrát.', $draftItemId)]);
    }

    public function testAnItemViolationNamesThePositionTheClientSent(): void
    {
        $client = self::createClient();

        $draftInvoice = self::getInvoiceEntity(InvoiceFixtures::INVOICE_DRAFT_ULID);

        $client->request('PUT', '/api/invoices/' . $draftInvoice->getId()->toBase32(), [
            'json' => $this->buildBodyWithItems([
                [
                    'description' => 'Test item',
                    'quantity' => '4.000',
                    'unit' => 'hod',
                    'unitPriceNet' => 150000,
                    'vatRate' => '21',
                ],
                [
                    'description' => 'Test second item',
                    'quantity' => '0.000',
                    'unit' => 'ks',
                    'unitPriceNet' => 2400000,
                    'vatRate' => '12',
                ],
            ]),
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains([
            'violations' => [
                [
                    'propertyPath' => 'items[1].quantity',
                    'message' => 'Tato hodnota musí být větší než 0.',
                ],
            ],
        ]);
    }

    public function testAnIssuedInvoiceCannotBeReplaced(): void
    {
        $client = self::createClient();

        $clientName = 'Test Client Renamed';
        $issuedInvoice = self::getInvoiceEntity(InvoiceFixtures::INVOICE_ISSUED_UNPAID_ULID);
        $originalClientName = $issuedInvoice->getClientName();
        $itemCountBefore = count($issuedInvoice->getItems());
        self::assertNotSame($clientName, $originalClientName);
        self::assertGreaterThan(0, $itemCountBefore);
        $issuedInvoiceUrl = '/api/invoices/' . $issuedInvoice->getId()->toBase32();

        $client->request('PUT', $issuedInvoiceUrl, [
            'json' => ['clientName' => $clientName] + $this->buildBodyWithItems([]),
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['detail' => 'Upravit lze jen koncept faktury.']);

        $payload = $client->request('GET', $issuedInvoiceUrl)->toArray();
        self::assertSame($originalClientName, $payload['clientName']);
        self::assertIsArray($payload['items']);
        self::assertCount($itemCountBefore, $payload['items']);
    }

    public function testADraftIssuedByAConcurrentRequestIsNotEdited(): void
    {
        $client = self::createClient();

        $staleDraftInvoice = self::getInvoiceEntity(InvoiceFixtures::INVOICE_DRAFT_ULID);
        $originalClientName = $staleDraftInvoice->getClientName();
        self::getEntityManager()->getConnection()->update(
            'invoice',
            ['status' => InvoiceStatus::ISSUED->value, 'number' => '2025-000003'],
            ['id' => $staleDraftInvoice->getId()->toBinary()],
        );

        $client->request('PUT', '/api/invoices/' . InvoiceFixtures::INVOICE_DRAFT_ULID, [
            'json' => ['clientName' => 'Test Client Renamed'] + $this->buildBodyWithItems([]),
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['detail' => 'Upravit lze jen koncept faktury.']);
        self::assertSame(
            $originalClientName,
            self::getInvoiceEntity(InvoiceFixtures::INVOICE_DRAFT_ULID)->getClientName(),
        );
    }
}
