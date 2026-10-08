<?php

declare(strict_types=1);

namespace App\GraphQl;

use ApiPlatform\GraphQl\Type\Definition\TypeInterface;
use GraphQL\Error\Error;
use GraphQL\Error\SerializationError;
use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\Utils;
use Override;

use function is_int;

final class BigIntType extends ScalarType implements TypeInterface
{
    public function __construct()
    {
        $this->name = 'BigInt';
        $this->description = 'A 64-bit integer as a JSON number, exact in JavaScript up to 2^53. Amounts in haléře.';

        parent::__construct();
    }

    #[Override]
    public function getName(): string
    {
        return $this->name;
    }

    #[Override]
    public function serialize(mixed $value): int
    {
        if (!is_int($value)) {
            throw new SerializationError('BigInt cannot represent a non-integer value: ' . Utils::printSafe($value));
        }

        return $value;
    }

    #[Override]
    public function parseValue(mixed $value): never
    {
        throw new Error('BigInt is an output type; no argument takes an amount.');
    }

    /**
     * @param array<string, mixed>|null $variables
     */
    #[Override]
    public function parseLiteral(Node $valueNode, ?array $variables = null): never
    {
        throw new Error('BigInt is an output type; no argument takes an amount.', $valueNode);
    }
}
