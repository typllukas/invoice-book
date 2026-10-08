<?php

declare(strict_types=1);

namespace App\GraphQl;

use ApiPlatform\GraphQl\Resolver\Factory\ResolverFactoryInterface;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\GraphQl\Operation;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Override;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Throwable;

/**
 * Mutation fields of one request run serially, so after a failed wrapInTransaction() the next field would
 * find Doctrine shut.
 */
#[AsDecorator('api_platform.graphql.resolver.factory')]
final readonly class ClosedEntityManagerResetDecorator implements ResolverFactoryInterface
{
    public function __construct(
        private ResolverFactoryInterface $decorated,
        private ManagerRegistry $managerRegistry,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function __invoke(
        ?string $resourceClass = null,
        ?string $rootClass = null,
        ?Operation $operation = null,
        ?PropertyMetadataFactoryInterface $propertyMetadataFactory = null,
    ): callable {
        $resolve = ($this->decorated)($resourceClass, $rootClass, $operation, $propertyMetadataFactory);
        if (!$operation instanceof Mutation) {
            return $resolve;
        }

        return function (mixed ...$resolverArguments) use ($resolve): mixed {
            try {
                return $resolve(...$resolverArguments);
            } catch (Throwable $throwable) {
                if (!$this->entityManager->isOpen()) {
                    $this->managerRegistry->resetManager();
                }

                throw $throwable;
            }
        };
    }
}
