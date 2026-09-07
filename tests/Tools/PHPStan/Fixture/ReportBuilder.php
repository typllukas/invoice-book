<?php

declare(strict_types=1);

namespace App\Tests\Tools\PHPStan\Fixture;

final class ReportBuilder
{
    public function createQuery(string $question): string
    {
        return $question;
    }
}
