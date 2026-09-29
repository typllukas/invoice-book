<?php

declare(strict_types=1);

namespace App\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;
use Override;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

use function sprintf;

/**
 * Ends every collection order on the id, so rows tied on a sorted column keep one order across pages.
 * Runs after the order filter and before API Platform's default order, which then sees an order and adds none.
 */
#[AsTaggedItem(priority: -31)]
final readonly class IdTiebreakerOrderExtension implements QueryCollectionExtensionInterface
{
    /**
     * @param array<string, mixed> $context
     */
    #[Override]
    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $queryBuilder->addOrderBy(sprintf('%s.id', $queryBuilder->getRootAliases()[0]), 'DESC');
    }
}
