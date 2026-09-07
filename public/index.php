<?php

declare(strict_types=1);

use App\Kernel;

require_once dirname(__DIR__) . '/vendor/autoload_runtime.php';

return static fn (array $context): Kernel => new Kernel(
    $context['APP_ENV'],
    filter_var($context['APP_DEBUG'], FILTER_VALIDATE_BOOL),
);
