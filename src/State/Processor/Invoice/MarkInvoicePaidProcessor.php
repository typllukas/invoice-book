<?php

declare(strict_types=1);

namespace App\State\Processor\Invoice;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Invoice;
use App\Enum\InvoiceStatus;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<Invoice, Invoice>
 */
final readonly class MarkInvoicePaidProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    #[Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Invoice
    {
        if ($data->getStatus() !== InvoiceStatus::ISSUED) {
            throw new UnprocessableEntityHttpException('Uhradit lze jen vystavenou fakturu.');
        }

        if ($data->getPaidAt() !== null) {
            throw new UnprocessableEntityHttpException('Faktura je už uhrazená.');
        }

        $data->markPaid($this->clock->now());

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }
}
