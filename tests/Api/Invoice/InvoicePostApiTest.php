<?php

declare(strict_types=1);

namespace App\Tests\Api\Invoice;

use App\Enum\InvoiceStatus;
use App\Tests\CustomApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

use function array_diff_key;
use function str_repeat;

/**
 * POST /api/invoices
 *
 * @phpstan-type ItemBody array{
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
final class InvoicePostApiTest extends CustomApiTestCase
{
    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function provideBodiesTheApiMustRefuse(): array
    {
        $body = self::buildBodyWithQuantity('1.000');

        return [
            'no client name' => [
                array_diff_key($body, ['clientName' => true]),
                'clientName',
                'Tato hodnota nesmí být prázdná.',
            ],
            'client name of spaces only' => [
                [...$body, 'clientName' => '   '],
                'clientName',
                'Tato hodnota nesmí být prázdná.',
            ],
            'tax point on a day the calendar does not have' => [
                [...$body, 'taxPointAt' => '2026-02-30'],
                'taxPointAt',
                'Tato hodnota není platné datum.',
            ],
            'item description of spaces only' => [
                [...$body, 'items' => [[...self::buildItem('1.000', 150000), 'description' => ' ']]],
                'items[0].description',
                'Tato hodnota nesmí být prázdná.',
            ],
            'company id that is not eight digits' => [
                [...$body, 'clientCompanyId' => '1234567'],
                'clientCompanyId',
                'IČO musí mít osm číslic.',
            ],
            'VAT id without the CZ prefix' => [
                [...$body, 'clientVatId' => '12345678'],
                'clientVatId',
                'DIČ musí mít tvar CZ a osm až deset číslic.',
            ],
            'VAT id with a wrong check digit' => [
                [...$body, 'clientVatId' => 'CZ00000000'],
                'clientVatId',
                'DIČ nemá platnou kontrolní číslici.',
            ],
            'company id with a wrong check digit' => [
                [...$body, 'clientCompanyId' => '00000000'],
                'clientCompanyId',
                'IČO nemá platnou kontrolní číslici.',
            ],
            'due date before the tax point' => [
                [...$body, 'dueAt' => '2026-09-15'],
                'dueAt',
                'Splatnost nemůže být dřív než datum uskutečnění plnění.',
            ],
            'zero quantity' => [
                self::buildBodyWithQuantity('0.000'),
                'items[0].quantity',
                'Tato hodnota musí být větší než 0.',
            ],
            'item without a price' => [
                [...$body, 'items' => [array_diff_key(self::buildItem('1.000', 150000), ['unitPriceNet' => true])]],
                'items[0].unitPriceNet',
                'Tato hodnota nesmí být prázdná.',
            ],
            'VAT rate the law does not have' => [
                [...$body, 'items' => [[...self::buildItem('1.000', 150000), 'vatRate' => '15']]],
                'items[0].vatRate',
                'Tato hodnota není platná.',
            ],
            'quantity in exponent notation' => [
                [...$body, 'items' => [self::buildItem('1e5', 150000)]],
                'items[0].quantity',
                'Množství musí být číslo, nejvýš na tři desetinná místa.',
            ],
            'quantity with a trailing newline' => [
                [...$body, 'items' => [self::buildItem("1.000\n", 150000)]],
                'items[0].quantity',
                'Množství musí být číslo, nejvýš na tři desetinná místa.',
            ],
            'client name past the column' => [
                [...$body, 'clientName' => str_repeat('a', 256)],
                'clientName',
                'Tato hodnota je příliš dlouhá. Musí obsahovat maximálně 255 znaků.',
            ],
            'note past the column' => [
                [...$body, 'note' => str_repeat('a', 1001)],
                'note',
                'Tato hodnota je příliš dlouhá. Musí obsahovat maximálně 1000 znaků.',
            ],
            'line amount past the money column' => [
                [...$body, 'items' => [self::buildItem('10000.000', 999999999999)]],
                'items[0].unitPriceNet',
                'Částka za řádek je příliš velká.',
            ],
        ];
    }

    /**
     * @return ItemBody
     */
    private static function buildItem(string $quantity, int $unitPriceNet): array
    {
        return [
            'description' => 'Test item',
            'quantity' => $quantity,
            'unit' => 'hod',
            'unitPriceNet' => $unitPriceNet,
            'vatRate' => '21',
        ];
    }

    /**
     * @return InvoiceBody
     */
    private static function buildBodyWithQuantity(string $quantity): array
    {
        return [
            'clientName' => 'Test Client',
            'clientAddress' => 'Test Address 1',
            'clientCompanyId' => '00000001',
            'clientVatId' => null,
            'dueAt' => '2026-09-30',
            'taxPointAt' => '2026-09-16',
            'note' => null,
            'items' => [self::buildItem($quantity, 150000)],
        ];
    }

    public function testPostCreatesADraftWithoutANumberAndComputesTheTotals(): void
    {
        $response = self::createClient()->request('POST', '/api/invoices', [
            'json' => self::buildBodyWithQuantity('2.500'),
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonContains([
            'status' => InvoiceStatus::DRAFT->value,
            'number' => null,
            'totalNetAmount' => 375000,
            'totalVatAmount' => 78750,
            'totalGrossAmount' => 453750,
        ]);
        self::assertNull($response->toArray()['supplierName']);

        $invoiceIri = $response->toArray()['@id'];
        self::assertIsString($invoiceIri);
        self::createClient()->request('GET', $invoiceIri);
        self::assertJsonContains(['dueAt' => '2026-09-30', 'taxPointAt' => '2026-09-16']);
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('provideBodiesTheApiMustRefuse')]
    public function testAnInvalidBodyIsRefusedWithTheFieldAtFault(
        array $body,
        string $propertyPath,
        string $message,
    ): void {
        self::createClient()->request('POST', '/api/invoices', [
            'json' => $body,
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains([
            'violations' => [
                [
                    'propertyPath' => $propertyPath,
                    'message' => $message,
                ],
            ],
        ]);
    }
}
