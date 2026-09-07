<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\DTO\Supplier;
use App\Entity\Invoice;
use App\Entity\InvoiceItem;
use App\Enum\VatRate;
use BcMath\Number;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * @see Invoice
 */
final class InvoiceTest extends TestCase
{
    private function buildSupplier(): Supplier
    {
        return new Supplier(
            'Test supplier',
            'Test address',
            '00000001',
            'CZ00000001',
            '000000/0000',
            'Test register entry',
        );
    }

    public function testAnIssuedInvoiceCannotBeIssuedAgain(): void
    {
        $invoice = new Invoice();
        $invoice->issue('2027-000001', new DateTimeImmutable('2027-01-04'), $this->buildSupplier());

        $this->expectException(LogicException::class);

        $invoice->issue('2027-000002', new DateTimeImmutable('2027-01-05'), $this->buildSupplier());
    }

    public function testAnIssuedLineKeepsItsAmountWhenTheItemChangesLater(): void
    {
        $item = new InvoiceItem()
            ->setDescription('Test item')
            ->setQuantity(new Number('2.000'))
            ->setUnit('ks')
            ->setUnitPriceNet(10000)
            ->setVatRate(VatRate::STANDARD);
        $invoice = new Invoice()->addItem($item);
        self::assertSame(20000, $item->getNetAmount());

        $invoice->issue('2027-000001', new DateTimeImmutable('2027-01-04'), $this->buildSupplier());
        $item->setUnitPriceNet(99999);

        self::assertSame(20000, $item->getNetAmount());
    }

    public function testADraftCannotBeMarkedPaid(): void
    {
        $this->expectException(LogicException::class);

        new Invoice()->markPaid(new DateTimeImmutable('2027-01-04'));
    }

    public function testAPaidInvoiceCannotBeMarkedPaidAgain(): void
    {
        $invoice = new Invoice();
        $invoice->issue('2027-000001', new DateTimeImmutable('2027-01-04'), $this->buildSupplier());
        $invoice->markPaid(new DateTimeImmutable('2027-01-10'));

        $this->expectException(LogicException::class);

        $invoice->markPaid(new DateTimeImmutable('2027-01-11'));
    }
}
