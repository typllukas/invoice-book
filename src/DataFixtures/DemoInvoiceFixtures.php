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
use DateTimeZone;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Override;
use Psr\Clock\ClockInterface;

use function count;
use function intval;
use function min;
use function sprintf;

final class DemoInvoiceFixtures extends Fixture implements FixtureGroupInterface
{
    private const int INVOICE_COUNT = 245;
    private const int PAYMENT_TERM_DAYS = 14;
    private const int RECENT_DRAFT_DAYS = 30;

    private const array CLIENTS = [
        [
            'name' => 'Novák a syn s.r.o.',
            'address' => 'Vzorová 12, 000 01 Příkladov',
            'companyId' => '12345687',
            'vatId' => 'CZ12345687',
        ],
        [
            'name' => 'STAVBY MORAVA a.s.',
            'address' => 'Nádražní 45, 000 05 Příkladná Ves',
            'companyId' => '12345695',
            'vatId' => 'CZ12345695',
        ],
        [
            'name' => 'Dvořáková Jana',
            'address' => 'Krátká 8, 000 02 Vzorovice',
            'companyId' => '34567895',
            'vatId' => null,
        ],
        [
            'name' => 'Šťastný Tomáš',
            'address' => 'Zahradní 2, 000 06 Ukázková Lhota',
            'companyId' => '45678901',
            'vatId' => null,
        ],
        [
            'name' => 'ELEKTRO VZOROVÁ LHOTA s.r.o.',
            'address' => 'Americká 30, 000 04 Vzorová Lhota',
            'companyId' => '56789017',
            'vatId' => 'CZ56789017',
        ],
    ];

    /**
     * @var array<int, InvoiceNumberSeries>
     */
    private array $numberSeriesByYear = [];

    public function __construct(
        private readonly Supplier $supplier,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @return list<string>
     */
    #[Override]
    public static function getGroups(): array
    {
        return ['demo'];
    }

    #[Override]
    public function load(ObjectManager $manager): void
    {
        $today = $this->clock->now()->setTimezone(new DateTimeZone(Invoice::CALENDAR_TIME_ZONE))->setTime(0, 0);
        for ($invoiceIndex = 1; $invoiceIndex <= self::INVOICE_COUNT; $invoiceIndex++) {
            $manager->persist($this->createInvoice($invoiceIndex, $today));
        }

        foreach ($this->numberSeriesByYear as $numberSeries) {
            $manager->persist($numberSeries);
        }

        $manager->flush();
    }

    private function createInvoice(int $invoiceIndex, DateTimeImmutable $today): Invoice
    {
        $client = self::CLIENTS[$invoiceIndex % count(self::CLIENTS)];
        $daysAgo = self::INVOICE_COUNT - $invoiceIndex;
        $taxPointAt = $today->modify(sprintf('-%d days', $daysAgo));

        $invoice = new Invoice()
            ->setClientName($client['name'])
            ->setClientAddress($client['address'])
            ->setClientCompanyId($client['companyId'])
            ->setClientVatId($client['vatId'])
            ->setDueAt($taxPointAt->modify(sprintf('+%d days', self::PAYMENT_TERM_DAYS)))
            ->setTaxPointAt($taxPointAt)
            ->addItem(
                new InvoiceItem()
                    ->setDescription('Konzultace')
                    ->setQuantity(new Number(sprintf('%d.000', 1 + ($invoiceIndex % 16))))
                    ->setUnit('hod')
                    ->setUnitPriceNet(150000)
                    ->setVatRate(VatRate::STANDARD),
            )
            ->addItem(
                new InvoiceItem()
                    ->setDescription('Ubytování účastníků školení')
                    ->setQuantity(new Number('1.000'))
                    ->setUnit('ks')
                    ->setUnitPriceNet(2400000 + ($invoiceIndex * 100))
                    ->setVatRate(VatRate::REDUCED),
            );

        if ($daysAgo < self::RECENT_DRAFT_DAYS && $invoiceIndex % 3 === 0) {
            return $invoice;
        }

        $year = intval($taxPointAt->format('Y'));
        $numberSeries = $this->numberSeriesByYear[$year] ??= new InvoiceNumberSeries()->setYear($year);
        $numberSeries->incrementLastSequence();
        $invoice->issue($numberSeries->formatLastNumber(), $taxPointAt, $this->supplier);

        $isPastDue = $daysAgo > self::PAYMENT_TERM_DAYS;
        if ($isPastDue ? $invoiceIndex % 12 !== 0 : $invoiceIndex % 2 === 0) {
            $paidAt = $taxPointAt
                ->modify(sprintf('+%d days', min($daysAgo, 2 + ($invoiceIndex % 10))))
                ->setTime(8 + ($invoiceIndex % 9), ($invoiceIndex * 7) % 60);
            $invoice->markPaid(min($paidAt, $this->clock->now()));
        }

        return $invoice;
    }
}
