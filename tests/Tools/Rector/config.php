<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Tools\Rector\ApiPlatformArgumentOrderRector;
use Tools\Rector\RedundantColumnTypeRector;
use Tools\Rector\RedundantIdUniqueRector;
use Tools\Rector\RedundantJoinColumnRector;
use Tools\Rector\StaticReturnInFinalClassRector;

return RectorConfig::configure()
    ->withRules([
        ApiPlatformArgumentOrderRector::class,
        RedundantColumnTypeRector::class,
        RedundantIdUniqueRector::class,
        RedundantJoinColumnRector::class,
        StaticReturnInFinalClassRector::class,
    ]);
