<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\InvoiceFixtures;
use App\DTO\Supplier;
use App\Entity\Invoice;
use App\Entity\InvoiceItem;
use App\Enum\VatRate;
use App\Tests\EntityGettersTrait;
use BcMath\Number;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

use function array_first;

/**
 * templates/pdf/invoice.html.twig
 */
final class InvoicePdfTemplateTest extends KernelTestCase
{
    use EntityGettersTrait;

    /**
     * @param list<InvoiceItem> $items
     */
    private function buildIssuedInvoice(array $items): Invoice
    {
        $invoice = new Invoice()
            ->setClientName('Test Client')
            ->setClientAddress('Test Address 1')
            ->setClientCompanyId('00000001')
            ->setDueAt(new DateTimeImmutable('2026-09-30'))
            ->setTaxPointAt(new DateTimeImmutable('2026-09-16'));
        foreach ($items as $item) {
            $invoice->addItem($item);
        }

        $invoice->issue('2026-000001', new DateTimeImmutable('2026-09-16'), self::getContainer()->get(Supplier::class));

        return $invoice;
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

    public function testEachItemShowsItsTotalBeforeVat(): void
    {
        $invoiceHtml = $this->renderInvoice($this->buildIssuedInvoice([
            $this->buildItem('1.500', 333, VatRate::STANDARD),
            $this->buildItem('2.000', 100, VatRate::STANDARD),
        ]));

        self::assertStringContainsString('<td class="number">5,00 Kč</td>', $invoiceHtml);
        self::assertStringContainsString('<td class="number">2,00 Kč</td>', $invoiceHtml);
    }

    public function testAnExemptItemIsMarkedAndTheDocumentSaysTheSupplyIsExempt(): void
    {
        $invoiceHtml = $this->renderInvoice($this->buildIssuedInvoice([
            $this->buildItem('1.000', 50000, VatRate::EXEMPT),
        ]));

        self::assertStringContainsString('<td class="number">osvobozeno</td>', $invoiceHtml);
        self::assertStringContainsString('Plnění je osvobozeno od daně.', $invoiceHtml);
    }

    public function testEachItemShowsItsQuantity(): void
    {
        $issuedInvoice = self::getInvoiceEntity(InvoiceFixtures::INVOICE_ISSUED_UNPAID_ULID);
        self::assertSame('40.000', array_first($issuedInvoice->getItems())?->getQuantity()->value);

        self::assertStringContainsString('40 hod', $this->renderInvoice($issuedInvoice));
    }

    public function testAPaymentIsDatedByThePragueDay(): void
    {
        $issuedInvoice = self::getInvoiceEntity(InvoiceFixtures::INVOICE_ISSUED_UNPAID_ULID);
        self::assertNull($issuedInvoice->getPaidAt());
        $issuedInvoice->markPaid(new DateTimeImmutable('2026-09-26T22:30:00+00:00'));

        self::assertStringContainsString('27. 9. 2026', $this->renderInvoice($issuedInvoice));
    }

    private function renderInvoice(Invoice $invoice): string
    {
        $twig = self::getContainer()->get(Environment::class);
        self::assertInstanceOf(Environment::class, $twig);

        return $twig->render('pdf/invoice.html.twig', ['invoice' => $invoice]);
    }
}
