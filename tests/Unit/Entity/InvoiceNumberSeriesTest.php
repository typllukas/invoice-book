<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\InvoiceNumberSeries;
use PHPUnit\Framework\TestCase;

/**
 * @see InvoiceNumberSeries
 */
final class InvoiceNumberSeriesTest extends TestCase
{
    public function testTheFirstNumberOfAYearIsTheYearAndSixDigits(): void
    {
        $numberSeries = new InvoiceNumberSeries()->setYear(2027);
        self::assertSame(0, $numberSeries->getLastSequence());

        $numberSeries->incrementLastSequence();

        self::assertSame('2027-000001', $numberSeries->formatLastNumber());
    }

    public function testASeriesIsExhaustedOnlyAtTheLastSixDigitNumber(): void
    {
        $numberSeries = new InvoiceNumberSeries()->setYear(2027);

        for ($issuedCount = 1; $issuedCount < 999999; $issuedCount++) {
            $numberSeries->incrementLastSequence();
        }

        self::assertFalse($numberSeries->isExhausted());

        $numberSeries->incrementLastSequence();

        self::assertSame('2027-999999', $numberSeries->formatLastNumber());
        self::assertTrue($numberSeries->isExhausted());
    }
}
