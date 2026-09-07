<?php

declare(strict_types=1);

namespace App\Validator;

use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class CompanyId extends Constraint
{
    public string $formatMessage = 'IČO musí mít osm číslic.';

    public string $checkDigitMessage = 'IČO nemá platnou kontrolní číslici.';
}
