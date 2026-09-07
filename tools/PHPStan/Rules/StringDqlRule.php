<?php

declare(strict_types=1);

namespace Tools\PHPStan\Rules;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * phpstan-doctrine parses a DQL string only while it stays constant, so a built query keeps the check.
 *
 * @implements Rule<MethodCall>
 */
final class StringDqlRule implements Rule
{
    #[Override]
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Identifier || $node->name->toString() !== 'createQuery') {
            return [];
        }

        if (!new ObjectType(EntityManagerInterface::class)->isSuperTypeOf($scope->getType($node->var))->yes()) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'createQuery() takes a DQL string; build the query with createQueryBuilder() instead.',
            )->identifier('invoiceBook.stringDql')->build(),
        ];
    }
}
