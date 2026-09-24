<?php

declare(strict_types=1);

namespace App\State\Processor\Invoice;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\DTO\Supplier;
use App\Entity\Invoice;
use App\Enum\InvoiceStatus;
use App\Repository\InvoiceNumberSeriesRepository;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

use function intval;
use function sprintf;

/**
 * @implements ProcessorInterface<Invoice, Invoice>
 */
final readonly class IssueInvoiceProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private InvoiceNumberSeriesRepository $invoiceNumberSeriesRepository,
        private Supplier $supplier,
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
        if ($data->getStatus() !== InvoiceStatus::DRAFT) {
            throw new UnprocessableEntityHttpException('Vystavit lze jen koncept faktury.');
        }

        if ($data->getItems() === []) {
            throw new UnprocessableEntityHttpException('Fakturu bez položek nelze vystavit.');
        }

        $issuedAt = $this->clock->now()->setTimezone(new DateTimeZone(Invoice::CALENDAR_TIME_ZONE));

        // the series row stays locked until the invoice carrying its number is written
        $this->entityManager->wrapInTransaction(function () use ($data, $issuedAt): void {
            $numberSeries = $this->invoiceNumberSeriesRepository->findOrCreateLockedForYear(
                intval($issuedAt->format('Y')),
            );
            if ($numberSeries->isExhausted()) {
                throw new UnprocessableEntityHttpException(
                    sprintf('Číselná řada faktur roku %d je vyčerpaná.', $numberSeries->getYear()),
                );
            }

            $numberSeries->incrementLastSequence();
            $data->issue($numberSeries->formatLastNumber(), $issuedAt, $this->supplier);

            $this->entityManager->persist($numberSeries);
            $this->entityManager->persist($data);
            $this->entityManager->flush();
        });

        return $data;
    }
}
