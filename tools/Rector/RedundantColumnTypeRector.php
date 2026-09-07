<?php

declare(strict_types=1);

namespace Tools\Rector;

use BcMath\Number;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Override;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\UnionType;
use Rector\PhpParser\Node\Value\ValueResolver;
use Rector\Rector\AbstractRector;
use ReflectionEnum;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

use function array_filter;
use function array_values;
use function count;
use function enum_exists;
use function in_array;
use function is_string;

/**
 * Doctrine's DefaultTypedFieldMapper reads the property type, so a matching type or enumType argument
 * is a second copy that can drift.
 */
final class RedundantColumnTypeRector extends AbstractRector
{
    /**
     * DefaultTypedFieldMapper's map, reversed.
     */
    private const array INFERRED_TYPES = [
        Types::STRING => 'string',
        Types::INTEGER => 'int',
        Types::BOOLEAN => 'bool',
        Types::FLOAT => 'float',
        Types::JSON => 'array',
        Types::DATETIME_MUTABLE => 'DateTime',
        Types::DATETIME_IMMUTABLE => 'DateTimeImmutable',
        Types::DATEINTERVAL => 'DateInterval',
        Types::NUMBER => Number::class,
    ];

    public function __construct(private readonly ValueResolver $valueResolver)
    {
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Remove a Column type or enumType the property type already implies', []);
    }

    /**
     * @return array<class-string<Node>>
     */
    #[Override]
    public function getNodeTypes(): array
    {
        return [Property::class];
    }

    #[Override]
    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof Property) {
            return null;
        }

        $propertyType = $this->findPropertyType($node);
        if (!$propertyType instanceof Name) {
            return null;
        }

        foreach ($node->attrGroups as $attributeGroup) {
            foreach ($attributeGroup->attrs as $attribute) {
                if (!$this->isName($attribute->name, Column::class)) {
                    continue;
                }

                $redundantArgumentNames = $this->findRedundantArgumentNames($attribute->args, $propertyType);
                if ($redundantArgumentNames === []) {
                    return null;
                }

                $attribute->args = array_values(array_filter(
                    $attribute->args,
                    static fn (Arg $argument): bool => !in_array(
                        $argument->name?->toString(),
                        $redundantArgumentNames,
                        true,
                    ),
                ));

                return $node;
            }
        }

        return null;
    }

    /**
     * The type left once null is taken out, so ?string and string|null both yield string.
     */
    private function findPropertyType(Property $property): ?Name
    {
        $type = $property->type;
        if ($type instanceof NullableType) {
            $type = $type->type;
        }

        if ($type instanceof UnionType) {
            $nonNullTypes = array_values(array_filter(
                $type->types,
                fn (Node $unionMember): bool => !$this->isName($unionMember, 'null'),
            ));
            $type = count($nonNullTypes) === 1 ? $nonNullTypes[0] : null;
        }

        if ($type instanceof Identifier) {
            return new Name($type->toString());
        }

        return $type instanceof Name ? $type : null;
    }

    /**
     * @param array<Arg> $arguments
     *
     * @return list<string>
     */
    private function findRedundantArgumentNames(array $arguments, Name $propertyType): array
    {
        $doctrineType = null;
        $enumType = null;
        foreach ($arguments as $argument) {
            if ($argument->name?->toString() === 'type') {
                $typeValue = $this->valueResolver->getValue($argument->value);
                $doctrineType = is_string($typeValue) ? $typeValue : null;
            }

            if ($argument->name?->toString() === 'enumType' && $argument->value instanceof ClassConstFetch) {
                $enumType = $this->getName($argument->value->class);
            }
        }

        if ($enumType !== null) {
            if ($enumType !== $this->getName($propertyType)) {
                return [];
            }

            if ($doctrineType === null || $doctrineType === Types::ENUM) {
                return ['enumType'];
            }

            if (!enum_exists($enumType)) {
                return [];
            }

            $backingType = new ReflectionEnum($enumType)->getBackingType()?->getName();

            return (self::INFERRED_TYPES[$doctrineType] ?? null) === $backingType ? ['enumType', 'type'] : [];
        }

        $impliedPropertyType = self::INFERRED_TYPES[$doctrineType ?? ''] ?? null;
        if ($impliedPropertyType === null || !$this->isName($propertyType, $impliedPropertyType)) {
            return [];
        }

        return ['type'];
    }
}
