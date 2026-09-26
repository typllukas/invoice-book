<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeImmutableType;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Override;

use function is_string;

/**
 * A DATETIME column has no offset, so a moment goes in and comes back as UTC whatever zone PHP runs in.
 */
final class UtcDateTimeImmutableType extends DateTimeImmutableType
{
    #[Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value instanceof DateTimeImmutable) {
            return $value->setTimezone(new DateTimeZone('UTC'))->format($platform->getDateTimeFormatString());
        }

        return parent::convertToDatabaseValue($value, $platform);
    }

    #[Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?DateTimeImmutable
    {
        if ($value === null || $value instanceof DateTimeImmutable) {
            return $value;
        }

        if (!is_string($value)) {
            throw InvalidType::new($value, self::class, ['null', 'string', DateTimeImmutable::class]);
        }

        $dateTimeFormat = $platform->getDateTimeFormatString();

        $dateTime = DateTimeImmutable::createFromFormat($dateTimeFormat, $value, new DateTimeZone('UTC'));
        if ($dateTime === false) {
            throw InvalidFormat::new($value, self::class, $dateTimeFormat);
        }

        return $dateTime;
    }
}
