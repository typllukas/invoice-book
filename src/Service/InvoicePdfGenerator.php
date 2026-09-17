<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\InvoicePdf;
use App\Entity\Invoice;
use LogicException;

use function base64_encode;

final readonly class InvoicePdfGenerator
{
    public function __construct(private PdfGenerator $pdfGenerator)
    {
    }

    public function generate(Invoice $invoice): InvoicePdf
    {
        $number = $invoice->getNumber() ?? throw new LogicException('Only an issued invoice has a PDF.');

        return new InvoicePdf(
            'faktura-' . $number . '.pdf',
            base64_encode($this->pdfGenerator->generate('pdf/invoice.html.twig', ['invoice' => $invoice])),
        );
    }
}
