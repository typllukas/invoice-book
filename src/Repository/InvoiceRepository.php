<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Invoice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

use function sprintf;

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
     * Locked, so a concurrent issue waits until this one has written the next number.
     */
    public function findLastIssuedLockedForYear(int $year): ?Invoice
    {
        return $this->createQueryBuilder('invoice')
            ->andWhere('invoice.number LIKE :yearPrefix')
            ->setParameter('yearPrefix', sprintf('%d-%%', $year))
            ->orderBy('invoice.number', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();
    }
}
