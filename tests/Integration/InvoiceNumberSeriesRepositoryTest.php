<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\InvoiceFixtures;
use App\Entity\InvoiceNumberSeries;
use App\Helper\MixedToString;
use App\Repository\InvoiceNumberSeriesRepository;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @see InvoiceNumberSeriesRepository
 */
final class InvoiceNumberSeriesRepositoryTest extends KernelTestCase
{
    public function testAYearWithoutASeriesGetsOneStartingAtZero(): void
    {
        $yearWithoutSeries = 1999;
        $invoiceNumberSeriesRepository = self::getContainer()->get(InvoiceNumberSeriesRepository::class);
        self::assertNull($invoiceNumberSeriesRepository->findOneBy(['year' => $yearWithoutSeries]));

        $numberSeries = self::getContainer()->get(EntityManagerInterface::class)->wrapInTransaction(
            static fn (): InvoiceNumberSeries => $invoiceNumberSeriesRepository->findOrCreateLockedForYear(
                $yearWithoutSeries,
            ),
        );

        self::assertSame($yearWithoutSeries, $numberSeries->getYear());
        self::assertSame(0, $numberSeries->getLastSequence());
    }

    public function testAYearWithASeriesKeepsItsLastSequence(): void
    {
        $invoiceNumberSeriesRepository = self::getContainer()->get(InvoiceNumberSeriesRepository::class);
        $fixtureSeries = $invoiceNumberSeriesRepository->findOneBy(['year' => InvoiceFixtures::NUMBER_SERIES_YEAR]);
        self::assertInstanceOf(InvoiceNumberSeries::class, $fixtureSeries);
        self::assertGreaterThan(0, $fixtureSeries->getLastSequence());

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        $numberSeries = $entityManager->wrapInTransaction(
            static fn (): InvoiceNumberSeries => $invoiceNumberSeriesRepository->findOrCreateLockedForYear(
                $fixtureSeries->getYear(),
            ),
        );

        self::assertNotSame($fixtureSeries, $numberSeries);
        self::assertSame($fixtureSeries->getId()->toBase32(), $numberSeries->getId()->toBase32());
        self::assertSame($fixtureSeries->getLastSequence(), $numberSeries->getLastSequence());
    }

    public function testASecondConnectionCannotLockASeriesWhileItIsLocked(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $invoiceNumberSeriesRepository = self::getContainer()->get(InvoiceNumberSeriesRepository::class);
        $connectionParameters = $entityManager->getConnection()->getParams();
        self::assertIsInt($connectionParameters['port'] ?? null);
        $secondConnection = DriverManager::getConnection([
            'driver' => 'pdo_mysql',
            'host' => MixedToString::transformStrict($connectionParameters['host'] ?? null),
            'port' => $connectionParameters['port'],
            'user' => MixedToString::transformStrict($connectionParameters['user'] ?? null),
            'password' => MixedToString::transformStrict($connectionParameters['password'] ?? null),
            'dbname' => MixedToString::transformStrict($connectionParameters['dbname'] ?? null),
        ]);
        $lockingReadSql = 'SELECT id FROM invoice_number_series WHERE year = ? FOR UPDATE NOWAIT';
        self::assertNotFalse($secondConnection->fetchOne($lockingReadSql, [InvoiceFixtures::NUMBER_SERIES_YEAR]));

        $this->expectException(LockWaitTimeoutException::class);

        $entityManager->wrapInTransaction(
            static function () use ($invoiceNumberSeriesRepository, $secondConnection, $lockingReadSql): void {
                $invoiceNumberSeriesRepository->findOrCreateLockedForYear(InvoiceFixtures::NUMBER_SERIES_YEAR);
                $secondConnection->fetchOne($lockingReadSql, [InvoiceFixtures::NUMBER_SERIES_YEAR]);
            },
        );
    }
}
