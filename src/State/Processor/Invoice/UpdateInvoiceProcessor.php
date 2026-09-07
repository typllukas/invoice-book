<?php

declare(strict_types=1);

namespace App\State\Processor\Invoice;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\DTO\InvoiceInput;
use App\Entity\Invoice;
use App\Enum\InvoiceStatus;
use App\Repository\InvoiceRepository;
use App\Service\InvoiceInputMapper;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Override;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<InvoiceInput, Invoice>
 */
final readonly class UpdateInvoiceProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private InvoiceRepository $invoiceRepository,
        private InvoiceInputMapper $invoiceInputMapper,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    #[Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Invoice
    {
        $invoice = $this->invoiceRepository->find($uriVariables['id']);
        if (!$invoice instanceof Invoice) {
            throw new LogicException('The read provider answers an unknown id with a 404 before this runs.');
        }

        if ($invoice->getStatus() !== InvoiceStatus::DRAFT) {
            throw new UnprocessableEntityHttpException('Upravit lze jen koncept faktury.');
        }

        $this->invoiceInputMapper->mapOntoInvoice($data, $invoice);

        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        return $invoice;
    }
}
