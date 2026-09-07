<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\FuncCall\SortCallLikeNamedArgsRector;
use Rector\Config\RectorConfig;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\YieldDataProviderRector;
use Tools\Rector\ApiPlatformArgumentOrderRector;
use Tools\Rector\RedundantColumnTypeRector;
use Tools\Rector\RedundantIdUniqueRector;
use Tools\Rector\RedundantJoinColumnRector;
use Tools\Rector\StaticReturnInFinalClassRector;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/tools', __DIR__ . '/config', __DIR__ . '/public'])
    ->withRootFiles()
    ->withSymfonyContainerXml(__DIR__ . '/var/cache/dev/App_KernelDevDebugContainer.xml')
    ->withPhpSets()
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        phpunitCodeQuality: true,
        doctrineCodeQuality: true,
        symfonyCodeQuality: true,
    )
    ->withRules([
        ApiPlatformArgumentOrderRector::class,
        RedundantColumnTypeRector::class,
        RedundantIdUniqueRector::class,
        RedundantJoinColumnRector::class,
        StaticReturnInFinalClassRector::class,
    ])
    ->withImportNames(removeUnusedImports: true)
    ->withSkip([
        // fights ApiPlatformArgumentOrderRector over the order of operation arguments
        SortCallLikeNamedArgsRector::class,
        // phpstan-strict-rules reports $this->assert*() as a dynamic call to a static method
        PreferPHPUnitThisCallRector::class,
        // a provider is a finite table, nothing about it is lazy
        YieldDataProviderRector::class,
        __DIR__ . '/config/bundles.php',
        __DIR__ . '/tests/Tools/Config/Fixture',
        __DIR__ . '/tests/Tools/Sniffs/Fixture',
        __DIR__ . '/config/reference.php',
    ]);
