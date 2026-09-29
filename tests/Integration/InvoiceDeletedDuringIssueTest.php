<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use ApiPlatform\Metadata\Post;
use App\DataFixtures\InvoiceFixtures;
use App\Entity\InvoiceNumberSeries;
use App\Exception\InvoiceNotFoundException;
use App\State\Processor\Invoice\IssueInvoiceProcessor;
use App\Tests\EntityGettersTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The read provider has loaded the draft when a concurrent delete commits, before the issue takes the lock.
 */
final class InvoiceDeletedDuringIssueTest extends KernelTestCase
{
    use EntityGettersTrait;

    public function testADraftDeletedBeforeTheLockIsNotIssuedAndTakesNoNumber(): void
    {
        $loadedDraftInvoice = self::getInvoiceEntity(InvoiceFixtures::INVOICE_DRAFT_ULID);
        self::assertNotSame([], $loadedDraftInvoice->getItems());
        $connection = self::getEntityManager()->getConnection();
        $connection->delete('invoice_item', ['invoice_id' => $loadedDraftInvoice->getId()->toBinary()]);
        $connection->delete('invoice', ['id' => $loadedDraftInvoice->getId()->toBinary()]);
        $seriesCountBefore = self::getEntityManager()->getRepository(InvoiceNumberSeries::class)->count();

        try {
            self::getContainer()->get(IssueInvoiceProcessor::class)->process($loadedDraftInvoice, new Post());
            self::fail('A deleted draft was issued.');
        } catch (InvoiceNotFoundException $exception) {
            self::assertSame('Faktura byla mezitím smazána.', $exception->getMessage());
        }

        self::assertSame(
            $seriesCountBefore,
            self::getEntityManager()->getRepository(InvoiceNumberSeries::class)->count(),
        );
    }
}
