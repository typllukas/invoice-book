<?php

declare(strict_types=1);

namespace App\GraphQl;

use ApiPlatform\GraphQl\Type\Definition\TypeInterface;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Override;

final class VatSummaryLineType extends ObjectType implements TypeInterface
{
    public function __construct(BigIntType $bigIntType)
    {
        parent::__construct([
            'name' => 'VatSummaryLine',
            'fields' => [
                'vatRate' => Type::nonNull(Type::string()),
                'netAmount' => Type::nonNull($bigIntType),
                'vatAmount' => Type::nonNull($bigIntType),
                'grossAmount' => Type::nonNull($bigIntType),
            ],
        ]);
    }

    #[Override]
    public function getName(): string
    {
        return $this->name;
    }
}
