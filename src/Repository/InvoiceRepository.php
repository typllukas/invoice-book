<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Invoice;
use App\Exception\InvoiceNotFoundException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invoice>
 */
class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    /**
     * Inside a transaction the lock holds until it ends, and a concurrent edit has committed before it is granted.
     * Refreshing the invoice leaves its items as the read provider loaded them, so each is refreshed too.
     *
     * @throws InvoiceNotFoundException When a concurrent delete committed first, which refresh() does not report.
     */
    public function lockAndRefresh(Invoice $invoice): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->refresh($invoice, LockMode::PESSIMISTIC_WRITE);
        if ($this->count(['id' => $invoice->getId()]) === 0) {
            throw new InvoiceNotFoundException('Faktura byla mezitím smazána.');
        }

        foreach ($invoice->getItems() as $item) {
            $entityManager->refresh($item);
        }
    }
}
