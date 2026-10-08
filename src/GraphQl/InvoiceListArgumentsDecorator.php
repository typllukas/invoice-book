<?php

declare(strict_types=1);

namespace App\GraphQl;

use ApiPlatform\GraphQl\Type\ContextAwareTypeBuilderInterface;
use ApiPlatform\GraphQl\Type\FieldsBuilderEnumInterface;
use ApiPlatform\Metadata\GraphQl\Operation;
use ApiPlatform\Metadata\GraphQl\Query;
use App\Entity\Invoice;
use App\Enum\InvoiceStatus;
use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Override;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function assert;
use function base64_decode;
use function filter_var;
use function is_array;
use function is_callable;
use function is_int;
use function is_string;
use function sprintf;

use const FILTER_VALIDATE_INT;
use const PHP_INT_MAX;

#[AsDecorator('api_platform.graphql.fields_builder')]
final readonly class InvoiceListArgumentsDecorator implements FieldsBuilderEnumInterface
{
    public function __construct(
        private FieldsBuilderEnumInterface $decorated,
        #[Autowire(service: 'api_platform.graphql.type_builder')]
        private ContextAwareTypeBuilderInterface $typeBuilder,
    ) {
    }

    /**
     * @param array<mixed>|null $ioMetadata
     *
     * @return array<mixed>
     */
    #[Override]
    public function getResourceObjectTypeFields(
        ?string $resourceClass,
        Operation $operation,
        bool $input,
        int $depth = 0,
        ?array $ioMetadata = null,
    ): array {
        return $this->decorated->getResourceObjectTypeFields($resourceClass, $operation, $input, $depth, $ioMetadata);
    }

    /**
     * @return array<mixed>
     */
    #[Override]
    public function getNodeQueryFields(): array
    {
        return $this->decorated->getNodeQueryFields();
    }

    /**
     * @param array<mixed> $configuration
     *
     * @return array<mixed>
     */
    #[Override]
    public function getItemQueryFields(string $resourceClass, Operation $operation, array $configuration): array
    {
        return $this->decorated->getItemQueryFields($resourceClass, $operation, $configuration);
    }

    /**
     * @param array<mixed> $configuration
     *
     * @return array<mixed>
     */
    #[Override]
    public function getCollectionQueryFields(string $resourceClass, Operation $operation, array $configuration): array
    {
        $fields = $this->decorated->getCollectionQueryFields($resourceClass, $operation, $configuration);
        if ($resourceClass !== Invoice::class) {
            return $fields;
        }

        $statusType = $this->typeBuilder->getEnumType(
            new Query()->withClass(InvoiceStatus::class)->withShortName('InvoiceStatus'),
        );
        $collectionField = $fields['invoices'];
        assert(is_array($collectionField));
        assert(is_array($collectionField['args']) && is_callable($collectionField['resolve']));
        $collectionField['args']['status'] = ['type' => $statusType];
        $collectionField['args']['status_list'] = ['type' => Type::listOf($statusType)];
        // API Platform numbers the edges of a backward page from 0 unless pageInfo is selected
        unset($collectionField['args']['last'], $collectionField['args']['before']);

        $resolveCollection = $collectionField['resolve'];
        $maximumPageSize = $operation->getPaginationMaximumItemsPerPage();
        assert(is_int($maximumPageSize));
        $collectionField['resolve'] = static function (
            mixed $source,
            array $queryArguments,
            mixed $context,
            ResolveInfo $resolveInfo,
        ) use (
            $resolveCollection,
            $maximumPageSize,
        ): mixed {
            $pageSize = $queryArguments['first'] ?? null;
            if (is_int($pageSize) && ($pageSize < 0 || $pageSize > $maximumPageSize)) {
                throw new UserError(sprintf('first must be between 0 and %d.', $maximumPageSize));
            }

            $cursor = $queryArguments['after'] ?? null;
            if (is_string($cursor)) {
                $decodedCursor = base64_decode($cursor, true);
                $cursorOptions = ['options' => ['min_range' => 0, 'max_range' => PHP_INT_MAX - $maximumPageSize]];
                if (
                    $decodedCursor === false
                    || filter_var($decodedCursor, FILTER_VALIDATE_INT, $cursorOptions) === false
                ) {
                    throw new UserError(sprintf('Cursor "%s" is invalid.', $cursor));
                }
            }

            return $resolveCollection($source, $queryArguments, $context, $resolveInfo);
        };
        $fields['invoices'] = $collectionField;

        return $fields;
    }

    /**
     * @return array<mixed>
     */
    #[Override]
    public function getMutationFields(string $resourceClass, Operation $operation): array
    {
        return $this->decorated->getMutationFields($resourceClass, $operation);
    }

    /**
     * @return array<mixed>
     */
    #[Override]
    public function getSubscriptionFields(string $resourceClass, Operation $operation): array
    {
        return $this->decorated->getSubscriptionFields($resourceClass, $operation);
    }

    /**
     * @return array<mixed>
     */
    #[Override]
    public function getEnumFields(string $enumClass): array
    {
        return $this->decorated->getEnumFields($enumClass);
    }

    /**
     * @param array<mixed> $args
     *
     * @return array<mixed>
     */
    #[Override]
    public function resolveResourceArgs(array $args, Operation $operation): array
    {
        return $this->decorated->resolveResourceArgs($args, $operation);
    }
}
