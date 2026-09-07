<?php

declare(strict_types=1);

namespace App\Tests\Tools\Config\Fixture;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class Mailer
{
    public function __construct(
        #[Autowire(env: 'default::MAILER_DSN')]
        private string $mailerDsn,
    ) {
    }
}
