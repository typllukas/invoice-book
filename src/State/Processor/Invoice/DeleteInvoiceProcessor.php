<?php

declare(strict_types=1);

namespace App\State\Processor\Invoice;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Invoice;
use App\Enum\InvoiceStatus;
use App\Repository\InvoiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<Invoice, null>
 */
final readonly class DeleteInvoiceProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private InvoiceRepository $invoiceRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    #[Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $this->entityManager->wrapInTransaction(function () use ($data): void {
            $this->invoiceRepository->lockAndRefresh($data);
            if ($data->getStatus() !== InvoiceStatus::DRAFT) {
                throw new UnprocessableEntityHttpException('Smazat lze jen koncept faktury.');
            }

            $this->entityManager->remove($data);
            $this->entityManager->flush();
        });

        return null;
    }
}
