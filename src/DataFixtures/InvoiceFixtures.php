<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\DTO\Supplier;
use App\Entity\Invoice;
use App\Entity\InvoiceItem;
use App\Entity\InvoiceNumberSeries;
use App\Enum\VatRate;
use BcMath\Number;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Override;
use Symfony\Component\Uid\Ulid;

final class InvoiceFixtures extends Fixture implements FixtureGroupInterface
{
    public const string INVOICE_DRAFT_ULID = '01M34NVAAGP6PC166F661XMCFT';
    public const string INVOICE_ISSUED_UNPAID_ULID = '01M34NVAAEADWW5F2J556ZF6K5';
    public const string INVOICE_ISSUED_PAID_ULID = '01M34NVAACV27SSE5W40HT34P0';
    public const int NUMBER_SERIES_YEAR = 2025;

    public function __construct(private readonly Supplier $supplier)
    {
    }

    /**
     * @return list<string>
     */
    #[Override]
    public static function getGroups(): array
    {
        return ['test'];
    }

    #[Override]
    public function load(ObjectManager $manager): void
    {
        $numberSeries = new InvoiceNumberSeries()->setYear(self::NUMBER_SERIES_YEAR);
        $manager->persist($numberSeries);
        $manager->persist($this->createDraftInvoice());
        $manager->persist($this->createIssuedUnpaidInvoice($numberSeries));
        $manager->persist($this->createIssuedPaidInvoice($numberSeries));

        $manager->flush();
    }

    private function createDraftInvoice(): Invoice
    {
        return new Invoice(new Ulid(self::INVOICE_DRAFT_ULID))
            ->setClientName('Kovář Petr')
            ->setClientAddress('Nová 3, 000 03 Ukázkov')
            ->setClientCompanyId('11223341')
            ->setClientVatId(null)
            ->setDueAt(new DateTimeImmutable('2026-08-26'))
            ->setTaxPointAt(new DateTimeImmutable('2026-08-12'))
            ->addItem(
                new InvoiceItem()
                    ->setDescription('Konzultace')
                    ->setQuantity(new Number('8.000'))
                    ->setUnit('hod')
                    ->setUnitPriceNet(150000)
                    ->setVatRate(VatRate::STANDARD),
            )
            ->addItem(
                new InvoiceItem()
                    ->setDescription('Ubytování účastníků školení')
                    ->setQuantity(new Number('1.000'))
                    ->setUnit('ks')
                    ->setUnitPriceNet(2400000)
                    ->setVatRate(VatRate::REDUCED),
            );
    }

    private function createIssuedUnpaidInvoice(InvoiceNumberSeries $numberSeries): Invoice
    {
        $invoice = new Invoice(new Ulid(self::INVOICE_ISSUED_UNPAID_ULID))
            ->setClientName('Novák a syn s.r.o.')
            ->setClientAddress('Vzorová 12, 000 01 Příkladov')
            ->setClientCompanyId('12345687')
            ->setClientVatId('CZ12345687')
            ->setDueAt(new DateTimeImmutable('2025-08-25'))
            ->setTaxPointAt(new DateTimeImmutable('2025-08-11'))
            ->addItem(
                new InvoiceItem()
                    ->setDescription('Vývoj na míru')
                    ->setQuantity(new Number('40.000'))
                    ->setUnit('hod')
                    ->setUnitPriceNet(100000)
                    ->setVatRate(VatRate::STANDARD),
            );

        $numberSeries->incrementLastSequence();
        $invoice->issue($numberSeries->formatLastNumber(), new DateTimeImmutable('2025-08-11'), $this->supplier);

        return $invoice;
    }

    private function createIssuedPaidInvoice(InvoiceNumberSeries $numberSeries): Invoice
    {
        $invoice = new Invoice(new Ulid(self::INVOICE_ISSUED_PAID_ULID))
            ->setClientName('Dvořáková Jana')
            ->setClientAddress('Krátká 8, 000 02 Vzorovice')
            ->setClientCompanyId('34567895')
            ->setClientVatId(null)
            ->setDueAt(new DateTimeImmutable('2025-08-26'))
            ->setTaxPointAt(new DateTimeImmutable('2025-08-12'))
            ->addItem(
                new InvoiceItem()
                    ->setDescription('Školení')
                    ->setQuantity(new Number('1.000'))
                    ->setUnit('ks')
                    ->setUnitPriceNet(600000)
                    ->setVatRate(VatRate::STANDARD),
            );

        $numberSeries->incrementLastSequence();
        $invoice->issue($numberSeries->formatLastNumber(), new DateTimeImmutable('2025-08-12'), $this->supplier);
        $invoice->markPaid(new DateTimeImmutable('2025-08-14T09:12:00+02:00'));

        return $invoice;
    }
}
