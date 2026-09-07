<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\InvoiceInput;
use App\Entity\Invoice;
use App\Entity\InvoiceItem;
use App\Exception\UnmatchedInvoiceItemException;
use BcMath\Number;
use DateTimeImmutable;

use function array_find;
use function in_array;
use function sprintf;

/**
 * Matches items by id among this invoice's items only, so an edited item keeps its id.
 * API Platform matching by @id replaces nested items and accepts an item of another invoice.
 */
final readonly class InvoiceInputMapper
{
    /**
     * @throws UnmatchedInvoiceItemException
     */
    public function mapOntoInvoice(InvoiceInput $invoiceInput, Invoice $invoice): void
    {
        $invoice
            ->setClientName($invoiceInput->clientName)
            ->setClientAddress($invoiceInput->clientAddress)
            ->setClientCompanyId($invoiceInput->clientCompanyId)
            ->setClientVatId($invoiceInput->clientVatId)
            ->setDueAt(new DateTimeImmutable($invoiceInput->dueAt))
            ->setTaxPointAt(new DateTimeImmutable($invoiceInput->taxPointAt))
            ->setNote($invoiceInput->note);

        $keptItems = [];
        foreach ($invoiceInput->items as $itemInput) {
            $item = $itemInput->id === null
                ? new InvoiceItem()
                : array_find(
                    $invoice->getItems(),
                    static fn (InvoiceItem $existingItem): bool => $existingItem->getId()->equals($itemInput->id),
                );
            if (!$item instanceof InvoiceItem) {
                throw new UnmatchedInvoiceItemException(sprintf('Položka %s na této faktuře není.', $itemInput->id));
            }

            if (in_array($item, $keptItems, true)) {
                throw new UnmatchedInvoiceItemException(sprintf('Položka %s je v požadavku dvakrát.', $item->getId()));
            }

            $keptItems[] = $item
                ->setDescription($itemInput->description)
                ->setQuantity(new Number($itemInput->quantity))
                ->setUnit($itemInput->unit)
                ->setUnitPriceNet($itemInput->unitPriceNet)
                ->setVatRate($itemInput->vatRate);
        }

        foreach ($invoice->getItems() as $existingItem) {
            if (!in_array($existingItem, $keptItems, true)) {
                $invoice->removeItem($existingItem);
            }
        }

        foreach ($keptItems as $keptItem) {
            $invoice->addItem($keptItem);
        }
    }
}
