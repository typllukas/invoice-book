<?php

declare(strict_types=1);

namespace App\Helper;

use LogicException;

use function get_debug_type;
use function is_int;
use function sprintf;

/**
 * (int) on 'abc' gives 0 and on an array gives 1. This throws instead.
 */
final class MixedToInteger
{
    public static function transformStrict(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        throw new LogicException(sprintf('Expected an integer, got %s.', get_debug_type($value)));
    }
}
