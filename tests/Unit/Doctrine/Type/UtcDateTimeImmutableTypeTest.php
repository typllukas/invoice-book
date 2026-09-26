<?php

declare(strict_types=1);

namespace App\Tests\Unit\Doctrine\Type;

use App\Doctrine\Type\UtcDateTimeImmutableType;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Override;
use PHPUnit\Framework\TestCase;

use function date_default_timezone_get;
use function date_default_timezone_set;

/**
 * @see UtcDateTimeImmutableType
 */
final class UtcDateTimeImmutableTypeTest extends TestCase
{
    private string $originalTimeZoneName;

    #[Override]
    protected function setUp(): void
    {
        $this->originalTimeZoneName = date_default_timezone_get();
        date_default_timezone_set('Europe/Prague');
    }

    #[Override]
    protected function tearDown(): void
    {
        date_default_timezone_set($this->originalTimeZoneName);
    }

    public function testAMomentIsStoredAsUtcWhateverZoneItCarries(): void
    {
        self::assertSame(
            '2026-09-26 21:30:00',
            new UtcDateTimeImmutableType()->convertToDatabaseValue(
                new DateTimeImmutable('2026-09-26T23:30:00+02:00'),
                new MariaDBPlatform(),
            ),
        );
    }

    public function testAStoredValueIsReadAsUtcWhenPhpRunsInAnotherZone(): void
    {
        self::assertSame(
            '2026-09-26T21:30:00+00:00',
            new UtcDateTimeImmutableType()
                ->convertToPHPValue('2026-09-26 21:30:00', new MariaDBPlatform())
                ->format(DateTimeInterface::RFC3339),
        );
    }
}
