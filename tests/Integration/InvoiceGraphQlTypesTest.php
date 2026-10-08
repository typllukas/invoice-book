<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\InvoiceFixtures;
use App\GraphQl\InvoiceLineType;
use App\GraphQl\VatSummaryLineType;
use App\Tests\EntityGettersTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

use function array_first;
use function array_keys;

/**
 * @see InvoiceLineType
 * @see VatSummaryLineType
 */
final class InvoiceGraphQlTypesTest extends KernelTestCase
{
    use EntityGettersTrait;

    private const array GRAPHQL_QUERY_GROUPS = ['invoice:read', 'invoice:operation:get'];

    /**
     * @return list<string>
     */
    private function getNormalizedKeys(object $line): array
    {
        $normalizedLine = self::getContainer()->get(NormalizerInterface::class)->normalize(
            $line,
            null,
            ['groups' => self::GRAPHQL_QUERY_GROUPS],
        );
        self::assertIsArray($normalizedLine);

        return array_keys($normalizedLine);
    }

    public function testInvoiceLineTypeHasExactlyTheFieldsRestSerializesForALine(): void
    {
        $invoiceItem = array_first(self::getInvoiceEntity(InvoiceFixtures::INVOICE_ISSUED_UNPAID_ULID)->getItems());
        self::assertNotNull($invoiceItem);

        self::assertEqualsCanonicalizing(
            $this->getNormalizedKeys($invoiceItem),
            array_keys(self::getContainer()->get(InvoiceLineType::class)->getFields()),
        );
    }

    public function testVatSummaryLineTypeHasExactlyTheFieldsRestSerializesForARecapitulationLine(): void
    {
        $vatSummaryLine = array_first(
            self::getInvoiceEntity(InvoiceFixtures::INVOICE_ISSUED_UNPAID_ULID)->getVatSummary(),
        );
        self::assertNotNull($vatSummaryLine);

        self::assertEqualsCanonicalizing(
            $this->getNormalizedKeys($vatSummaryLine),
            array_keys(self::getContainer()->get(VatSummaryLineType::class)->getFields()),
        );
    }
}
