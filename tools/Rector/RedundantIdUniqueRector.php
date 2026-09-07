<?php

declare(strict_types=1);

namespace Tools\Rector;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Id;
use Override;
use PhpParser\Node;
use PhpParser\Node\Stmt\Property;
use Rector\Php80\NodeAnalyzer\PhpAttributeAnalyzer;
use Rector\PhpParser\Node\Value\ValueResolver;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

use function array_splice;

/**
 * A primary key is unique already, and Doctrine emits no index for unique: true on an Id column.
 */
final class RedundantIdUniqueRector extends AbstractRector
{
    public function __construct(
        private readonly PhpAttributeAnalyzer $phpAttributeAnalyzer,
        private readonly ValueResolver $valueResolver,
    ) {
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Remove unique: true from the column of an Id property', []);
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

        if (!$this->phpAttributeAnalyzer->hasPhpAttribute($node, Id::class)) {
            return null;
        }

        foreach ($node->attrGroups as $attributeGroup) {
            foreach ($attributeGroup->attrs as $attribute) {
                if (!$this->isName($attribute->name, Column::class)) {
                    continue;
                }

                foreach ($attribute->args as $argumentIndex => $argument) {
                    if ($argument->name?->toString() !== 'unique' || !$this->valueResolver->isTrue($argument->value)) {
                        continue;
                    }

                    array_splice($attribute->args, $argumentIndex, 1);

                    return $node;
                }
            }
        }

        return null;
    }
}
