<?php

declare(strict_types=1);

namespace App\Tests\Tools\Rector;

use Override;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;
use Tools\Rector\ApiPlatformArgumentOrderRector;
use Tools\Rector\RedundantColumnTypeRector;
use Tools\Rector\RedundantIdUniqueRector;
use Tools\Rector\RedundantJoinColumnRector;
use Tools\Rector\StaticReturnInFinalClassRector;

/**
 * In its own process: Symfony's PHPUnit extension enables DebugClassLoader, which rejects the API Platform
 * classes Rector's reflection reads without loading them.
 *
 * @see ApiPlatformArgumentOrderRector
 * @see RedundantColumnTypeRector
 * @see RedundantIdUniqueRector
 * @see RedundantJoinColumnRector
 * @see StaticReturnInFinalClassRector
 */
#[RunTestsInSeparateProcesses]
final class RectorRulesTest extends AbstractRectorTestCase
{
    #[Override]
    public function provideConfigFilePath(): string
    {
        return __DIR__ . '/config.php';
    }

    public function testResourceAndOperationArgumentsAreSortedAndNothingElseIs(): void
    {
        $this->doTestFile(__DIR__ . '/Fixture/api_platform_arguments.php.inc');
    }

    public function testEachRedundantMappingIsRemovedAndTheOverridesAreKept(): void
    {
        $this->doTestFile(__DIR__ . '/Fixture/doctrine_mappings.php.inc');
    }

    public function testAFinalClassReturnsSelfAndAnOpenOneKeepsStatic(): void
    {
        $this->doTestFile(__DIR__ . '/Fixture/static_return_in_final_class.php.inc');
    }
}
