<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\InvoiceNumberSeries;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<InvoiceNumberSeries>
 */
class InvoiceNumberSeriesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InvoiceNumberSeries::class);
    }

    /**
     * A concurrent issue waits for the locked row instead of taking the same number.
     */
    public function findOrCreateLockedForYear(int $year): InvoiceNumberSeries
    {
        $this->insertSeriesUnlessExists($year);

        $numberSeries = $this->createQueryBuilder('invoice_number_series')
            ->andWhere('invoice_number_series.year = :year')
            ->setParameter('year', $year)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();

        if (!$numberSeries instanceof InvoiceNumberSeries) {
            throw new LogicException('The number series row was inserted and is not there.');
        }

        return $numberSeries;
    }

    /**
     * A lock on a year with no row serializes nothing, and one of two first issues deadlocks (MariaDB 11.4).
     * Plain SQL, since the ORM has no insert that skips an existing key.
     */
    private function insertSeriesUnlessExists(int $year): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            'INSERT INTO invoice_number_series (id, year, last_sequence) VALUES (:id, :year, 0)'
            . ' ON DUPLICATE KEY UPDATE year = year',
            ['id' => new Ulid()->toBinary(), 'year' => $year],
        );
    }
}
