<?php

declare(strict_types=1);

namespace App\Tests\Unit\Helper;

use App\Entity\InvoiceItem;
use App\Enum\VatRate;
use App\Helper\VatCalculator;
use BcMath\Number;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function json_decode;
use function strval;

use const JSON_THROW_ON_ERROR;

/**
 * @see VatCalculator
 *
 * @phpstan-type VatCalculatorCase array{
 *     items: list<array{quantity: numeric-string, unitPriceNet: int, vatRate: string}>,
 *     lines: list<array{vatRate: string, netAmount: int, vatAmount: int, grossAmount: int}>,
 *     netAmount: int,
 *     vatAmount: int,
 *     grossAmount: int,
 * }
 */
final class VatCalculatorTest extends TestCase
{
    /**
     * The frontend runs these as shared test vectors too, in frontend/src/domain/pricing/vat.test.ts,
     * against the form's copy of the method.
     *
     * @return array<string, array{VatCalculatorCase}>
     */
    public static function provideSharedCases(): array
    {
        /** @var array<string, VatCalculatorCase> $vatCases */
        $vatCases = json_decode(
            strval(file_get_contents(__DIR__ . '/vat-calculator-cases.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $providedCases = [];

        foreach ($vatCases as $caseName => $vatCase) {
            $providedCases[$caseName] = [$vatCase];
        }

        return $providedCases;
    }

    /**
     * @param numeric-string $quantity
     */
    private function buildItem(string $quantity, int $unitPriceNet, VatRate $vatRate): InvoiceItem
    {
        return new InvoiceItem()
            ->setDescription('Test item')
            ->setQuantity(new Number($quantity))
            ->setUnit('ks')
            ->setUnitPriceNet($unitPriceNet)
            ->setVatRate($vatRate);
    }

    /**
     * @param VatCalculatorCase $vatCase
     */
    #[DataProvider('provideSharedCases')]
    public function testTheRecapitulationMatchesTheSharedCase(array $vatCase): void
    {
        $items = [];

        foreach ($vatCase['items'] as $item) {
            $items[] = $this->buildItem($item['quantity'], $item['unitPriceNet'], VatRate::from($item['vatRate']));
        }

        $summary = VatCalculator::summarize($items);
        $lines = [];

        foreach ($summary as $summaryLine) {
            $lines[] = [
                'vatRate' => $summaryLine->vatRate->value,
                'netAmount' => $summaryLine->netAmount,
                'vatAmount' => $summaryLine->vatAmount,
                'grossAmount' => $summaryLine->grossAmount,
            ];
        }

        self::assertSame($vatCase['lines'], $lines);
        self::assertSame($vatCase['netAmount'], VatCalculator::calculateTotalNetAmount($summary));
        self::assertSame($vatCase['vatAmount'], VatCalculator::calculateTotalVatAmount($summary));
        self::assertSame($vatCase['grossAmount'], VatCalculator::calculateTotalGrossAmount($summary));
    }
}
