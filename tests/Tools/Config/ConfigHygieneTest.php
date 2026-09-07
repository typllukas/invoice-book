<?php

declare(strict_types=1);

namespace App\Tests\Tools\Config;

use PHPUnit\Framework\TestCase;
use Tools\Config\ConfigHygiene;

use function in_array;

/**
 * @see ConfigHygiene
 */
final class ConfigHygieneTest extends TestCase
{
    private const string FIXTURE_DIR = __DIR__ . '/Fixture';

    public function testAFileOfEmptyKeysIsReportedUnlessOneOfThemSwitchesAFeatureOn(): void
    {
        $isFeatureToggle = static fn (string $keyPath): bool => in_array(
            $keyPath,
            ['framework.esi', 'framework.lock'],
            true,
        );

        self::assertSame(
            ['config/packages/cache.yaml'],
            ConfigHygiene::findConfigFilesSettingNothing(self::FIXTURE_DIR, $isFeatureToggle),
        );
    }

    public function testAnEnvVarNothingReadsIsReported(): void
    {
        self::assertSame(['DEFAULT_URI', 'LEFTOVER_TOKEN'], ConfigHygiene::findUnreadEnvVars(self::FIXTURE_DIR));
    }
}
