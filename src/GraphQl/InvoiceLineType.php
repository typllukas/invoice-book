<?php

declare(strict_types=1);

namespace App\GraphQl;

use ApiPlatform\GraphQl\Type\Definition\TypeInterface;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Override;

final class InvoiceLineType extends ObjectType implements TypeInterface
{
    public function __construct(BigIntType $bigIntType)
    {
        parent::__construct([
            'name' => 'InvoiceLine',
            'fields' => [
                'id' => Type::nonNull(Type::id()),
                'description' => Type::nonNull(Type::string()),
                'quantity' => Type::nonNull(Type::string()),
                'unit' => Type::nonNull(Type::string()),
                'vatRate' => Type::nonNull(Type::string()),
                'unitPriceNet' => Type::nonNull($bigIntType),
                'netAmount' => Type::nonNull($bigIntType),
            ],
        ]);
    }

    #[Override]
    public function getName(): string
    {
        return $this->name;
    }
}
