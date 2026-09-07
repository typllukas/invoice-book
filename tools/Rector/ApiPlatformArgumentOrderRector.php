<?php

declare(strict_types=1);

namespace Tools\Rector;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Operation;
use Override;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Identifier;
use PHPStan\Type\ObjectType;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

use function array_flip;
use function count;
use function usort;

final class ApiPlatformArgumentOrderRector extends AbstractRector
{
    /**
     * What the resource is, then what can be done with it, then the defaults its operations inherit.
     */
    private const array RESOURCE_ORDER = [
        'shortName',
        'types',
        'uriTemplate',
        'routePrefix',
        'uriVariables',
        'operations',
        'security',
        'securityMessage',
        'provider',
        'processor',
        'input',
        'output',
        'denormalizationContext',
        'normalizationContext',
        'validationContext',
        'description',
        'openapi',
        'filters',
        'parameters',
        'order',
        'paginationEnabled',
        'paginationItemsPerPage',
        'paginationClientEnabled',
        'paginationClientItemsPerPage',
        'paginationMaximumItemsPerPage',
    ];

    private const array OPERATION_ORDER = [
        'uriTemplate',
        'uriVariables',
        'routeName',
        'requirements',
        'security',
        'securityPostDenormalize',
        'securityMessage',
        'provider',
        'processor',
        'input',
        'output',
        'denormalizationContext',
        'normalizationContext',
        'validationContext',
        'status',
        'description',
        'openapi',
        'filters',
        'parameters',
        'order',
        'paginationEnabled',
        'paginationItemsPerPage',
        'paginationClientEnabled',
        'paginationClientItemsPerPage',
        'paginationMaximumItemsPerPage',
        'read',
        'deserialize',
        'validate',
        'write',
    ];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Sort the named arguments of ApiResource and of every operation', []);
    }

    /**
     * @return array<class-string<Node>>
     */
    #[Override]
    public function getNodeTypes(): array
    {
        return [Attribute::class, New_::class];
    }

    #[Override]
    public function refactor(Node $node): ?Node
    {
        if ($node instanceof Attribute && $this->isName($node->name, ApiResource::class)) {
            return $this->sortArguments($node, self::RESOURCE_ORDER);
        }

        if ($node instanceof New_ && $this->isObjectType($node, new ObjectType(Operation::class))) {
            return $this->sortArguments($node, self::OPERATION_ORDER);
        }

        return null;
    }

    /**
     * @param list<string> $order
     */
    private function sortArguments(Attribute|New_ $node, array $order): ?Node
    {
        $arguments = $node instanceof Attribute ? $node->args : $node->getArgs();
        $positions = array_flip($order);
        $rankArgument = static fn (Arg $argument): int => $argument->name instanceof Identifier
            ? $positions[$argument->name->toString()] ?? count($order)
            : -1;

        $sortedArguments = $arguments;
        usort(
            $sortedArguments,
            static fn (Arg $first, Arg $second): int => $rankArgument($first) <=> $rankArgument($second),
        );
        if ($sortedArguments === $arguments) {
            return null;
        }

        // swapped into the existing slots, a reordered args list makes Rector print the call on one line
        $sortedParts = [];
        foreach ($sortedArguments as $sortedArgument) {
            $sortedParts[] = [$sortedArgument->name, $sortedArgument->value, $sortedArgument->getComments()];
        }

        foreach ($arguments as $argumentIndex => $argument) {
            [$argument->name, $argument->value, $comments] = $sortedParts[$argumentIndex];
            $argument->setAttribute('comments', $comments);
        }

        return $node;
    }
}
