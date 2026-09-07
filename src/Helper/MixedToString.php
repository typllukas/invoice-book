<?php

declare(strict_types=1);

namespace App\Helper;

use LogicException;
use Stringable;

use function get_debug_type;
use function is_string;
use function sprintf;
use function strval;

/**
 * (string) on an array gives 'Array' plus a notice. This throws instead.
 */
final class MixedToString
{
    public static function transformStrict(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if ($value instanceof Stringable) {
            return strval($value);
        }

        throw new LogicException(sprintf('Expected a string, got %s.', get_debug_type($value)));
    }
}
