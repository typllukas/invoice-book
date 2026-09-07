<?php

declare(strict_types=1);

namespace App\State\Processor\Invoice;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\DTO\Supplier;
use App\Entity\Invoice;
use App\Enum\InvoiceStatus;
use App\Repository\InvoiceRepository;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

use function intval;
use function sprintf;
use function substr;

/**
 * @implements ProcessorInterface<Invoice, Invoice>
 */
final readonly class IssueInvoiceProcessor implements ProcessorInterface
{
    /**
     * The year and six digits are the ten the variable symbol of a Czech payment holds.
     */
    private const int HIGHEST_SEQUENCE = 999999;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private InvoiceRepository $invoiceRepository,
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

        // the last invoice of the year stays locked until the invoice carrying the next number is written
        $this->entityManager->wrapInTransaction(function () use ($data, $issuedAt): void {
            $year = intval($issuedAt->format('Y'));
            $lastIssuedInvoice = $this->invoiceRepository->findLastIssuedLockedForYear($year);
            $lastSequence = $lastIssuedInvoice instanceof Invoice
                ? intval(substr($lastIssuedInvoice->getNumber() ?? '', 5))
                : 0;
            if ($lastSequence >= self::HIGHEST_SEQUENCE) {
                throw new UnprocessableEntityHttpException(
                    sprintf('Číselná řada faktur roku %d je vyčerpaná.', $year),
                );
            }

            $data->issue(sprintf('%d-%06d', $year, $lastSequence + 1), $issuedAt, $this->supplier);

            $this->entityManager->persist($data);
            $this->entityManager->flush();
        });

        return $data;
    }
}
