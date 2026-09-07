<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Invoice;
use App\Entity\InvoiceItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

trait EntityGettersTrait
{
    protected static function getEntityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * @param class-string<T> $entityClass
     *
     * @return T
     *
     * @template T of object
     */
    protected static function getGenericEntity(string $ulid, string $entityClass): object
    {
        $entity = self::getEntityManager()->find($entityClass, Ulid::fromString($ulid));
        self::assertInstanceOf($entityClass, $entity);

        return $entity;
    }

    protected static function getInvoiceEntity(string $ulid): Invoice
    {
        return self::getGenericEntity($ulid, Invoice::class);
    }

    protected static function getInvoiceItemEntity(string $ulid): InvoiceItem
    {
        return self::getGenericEntity($ulid, InvoiceItem::class);
    }
}
