<?php

declare(strict_types=1);

namespace App\GraphQl;

use ApiPlatform\GraphQl\Type\FieldsBuilderEnumInterface;
use ApiPlatform\Metadata\GraphQl\Operation;
use App\Entity\Invoice;
use GraphQL\Type\Definition\Type;
use Override;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

#[AsDecorator('api_platform.graphql.fields_builder')]
final readonly class InvoiceOutputTypesDecorator implements FieldsBuilderEnumInterface
{
    public function __construct(
        private FieldsBuilderEnumInterface $decorated,
        private InvoiceLineType $invoiceLineType,
        private VatSummaryLineType $vatSummaryLineType,
        private BigIntType $bigIntType,
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
        $fields = $this->decorated->getResourceObjectTypeFields(
            $resourceClass,
            $operation,
            $input,
            $depth,
            $ioMetadata,
        );
        if ($resourceClass !== Invoice::class || $input) {
            return $fields;
        }

        // lines are no resources; a type converter would page items as a connection
        $fields['items'] = ['type' => Type::nonNull(Type::listOf(Type::nonNull($this->invoiceLineType)))];
        $fields['vatSummary'] = ['type' => Type::nonNull(Type::listOf(Type::nonNull($this->vatSummaryLineType)))];
        foreach (['totalNetAmount', 'totalVatAmount', 'totalGrossAmount'] as $totalFieldName) {
            $fields[$totalFieldName] = ['type' => Type::nonNull($this->bigIntType)];
        }

        return $fields;
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
        return $this->decorated->getCollectionQueryFields($resourceClass, $operation, $configuration);
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
