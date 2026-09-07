<?php

declare(strict_types=1);

namespace App\Helper;

use App\DTO\VatSummaryLine;
use App\Entity\InvoiceItem;
use App\Enum\VatRate;
use NoDiscard;

use function intdiv;
use function intval;
use function strval;

/**
 * All amounts are whole "haléře".
 */
final class VatCalculator
{
    /**
     * @param iterable<InvoiceItem> $items
     *
     * @return list<VatSummaryLine>
     */
    #[NoDiscard]
    public static function summarize(iterable $items): array
    {
        $netAmountByRate = [];

        foreach ($items as $item) {
            $rate = $item->getVatRate()->value;
            $netAmountByRate[$rate] = ($netAmountByRate[$rate] ?? 0) + self::calculateLineNetAmount($item);
        }

        $summaryLines = [];

        foreach (VatRate::cases() as $vatRate) {
            $netAmount = $netAmountByRate[$vatRate->value] ?? null;

            if ($netAmount === null) {
                continue;
            }

            $vatAmount = self::roundHalfUp($netAmount * $vatRate->toPercentage(), 100);
            $summaryLines[] = new VatSummaryLine($vatRate, $netAmount, $vatAmount, $netAmount + $vatAmount);
        }

        return $summaryLines;
    }

    /**
     * @param list<VatSummaryLine> $vatSummaryLines
     */
    #[NoDiscard]
    public static function calculateTotalNetAmount(array $vatSummaryLines): int
    {
        $totalNetAmount = 0;

        foreach ($vatSummaryLines as $summaryLine) {
            $totalNetAmount += $summaryLine->netAmount;
        }

        return $totalNetAmount;
    }

    /**
     * @param list<VatSummaryLine> $vatSummaryLines
     */
    #[NoDiscard]
    public static function calculateTotalVatAmount(array $vatSummaryLines): int
    {
        $totalVatAmount = 0;

        foreach ($vatSummaryLines as $summaryLine) {
            $totalVatAmount += $summaryLine->vatAmount;
        }

        return $totalVatAmount;
    }

    /**
     * @param list<VatSummaryLine> $vatSummaryLines
     */
    #[NoDiscard]
    public static function calculateTotalGrossAmount(array $vatSummaryLines): int
    {
        $totalGrossAmount = 0;

        foreach ($vatSummaryLines as $summaryLine) {
            $totalGrossAmount += $summaryLine->grossAmount;
        }

        return $totalGrossAmount;
    }

    /**
     * Quantity is thousandths and the unit price "haléře", so their product is "haléře" times thousandths.
     */
    #[NoDiscard]
    public static function calculateLineNetAmount(InvoiceItem $item): int
    {
        $quantityInThousandths = intval(strval($item->getQuantity()->mul(1000)));

        return self::roundHalfUp($quantityInThousandths * $item->getUnitPriceNet(), 1000);
    }

    private static function roundHalfUp(int $dividend, int $divisor): int
    {
        return intdiv($dividend * 2 + $divisor, $divisor * 2);
    }
}
