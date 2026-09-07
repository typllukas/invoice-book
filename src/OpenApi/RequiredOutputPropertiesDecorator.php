<?php

declare(strict_types=1);

namespace App\OpenApi;

use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use Override;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

use function array_filter;
use function array_values;
use function is_array;
use function str_starts_with;

/**
 * skip_null_values is off. The schema would otherwise list only what a validator enforces,
 * and the generated types would lie.
 */
#[AsDecorator('api_platform.metadata.property.metadata_factory')]
final readonly class RequiredOutputPropertiesDecorator implements PropertyMetadataFactoryInterface
{
    public function __construct(
        private PropertyMetadataFactoryInterface $decorated,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    #[Override]
    public function create(string $resourceClass, string $property, array $options = []): ApiProperty
    {
        $propertyMetadata = $this->decorated->create($resourceClass, $property, $options);

        // API Platform's own error resources skip their null properties
        if (
            !str_starts_with($resourceClass, 'App\\')
            || ($options['schema_type'] ?? null) !== Schema::TYPE_OUTPUT
            || $propertyMetadata->isReadable() !== true
        ) {
            return $propertyMetadata;
        }

        $propertyMetadata = $propertyMetadata->withRequired(true);
        $propertySchema = $propertyMetadata->getSchema() ?? [];
        if ($propertyMetadata->isIdentifier() !== true || !is_array($propertySchema['type'] ?? null)) {
            return $propertyMetadata;
        }

        // nullable only because the constructor extractor reads the ?Ulid every entity constructor takes
        $propertySchema['type'] = array_values(
            array_filter($propertySchema['type'], static fn (mixed $jsonType): bool => $jsonType !== 'null'),
        );

        return $propertyMetadata->withSchema($propertySchema);
    }
}
