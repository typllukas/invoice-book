<?php

declare(strict_types=1);

namespace App\Validator;

use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class VatId extends Constraint
{
    public string $formatMessage = 'DIČ musí mít tvar CZ a osm až deset číslic.';

    public string $checkDigitMessage = 'DIČ nemá platnou kontrolní číslici.';
}
