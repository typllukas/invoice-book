<?php

declare(strict_types=1);

namespace App\Tests\Tools\PHPStan\Fixture;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

final class StringDqlQueries
{
    public function deleteEverythingWithADqlString(EntityManagerInterface $entityManager): void
    {
        $entityManager->createQuery('DELETE FROM App\Entity\Invoice invoice')->execute();
    }

    public function buildTheQueryInstead(EntityManagerInterface $entityManager): QueryBuilder
    {
        return $entityManager->createQueryBuilder();
    }

    public function createAQueryOnSomethingElse(ReportBuilder $reportBuilder): string
    {
        return $reportBuilder->createQuery('every invoice issued this month');
    }
}
