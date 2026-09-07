<?php

declare(strict_types=1);

namespace Tools\Rector;

use Doctrine\ORM\Mapping\JoinColumn;
use Override;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Stmt\Property;
use Rector\PhpParser\Node\Value\ValueResolver;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

use function array_splice;
use function count;

/**
 * The association alone gives Doctrine the foreign key, and nullable: true is already the default.
 */
final class RedundantJoinColumnRector extends AbstractRector
{
    public function __construct(private readonly ValueResolver $valueResolver)
    {
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Remove a JoinColumn that repeats the Doctrine default', []);
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

        foreach ($node->attrGroups as $groupIndex => $attributeGroup) {
            foreach ($attributeGroup->attrs as $attributeIndex => $attribute) {
                if (
                    !$this->isName($attribute->name, JoinColumn::class)
                    || !$this->hasOnlyDefaultArguments($attribute->args)
                ) {
                    continue;
                }

                array_splice($attributeGroup->attrs, $attributeIndex, 1);
                if ($attributeGroup->attrs === []) {
                    array_splice($node->attrGroups, $groupIndex, 1);
                }

                return $node;
            }
        }

        return null;
    }

    /**
     * @param array<Arg> $arguments
     */
    private function hasOnlyDefaultArguments(array $arguments): bool
    {
        if ($arguments === []) {
            return true;
        }

        return count($arguments) === 1
            && $arguments[0]->name?->toString() === 'nullable'
            && $this->valueResolver->isTrue($arguments[0]->value);
    }
}
