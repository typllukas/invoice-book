<?php

declare(strict_types=1);

namespace App\Tests\Unit\GraphQl;

use ApiPlatform\GraphQl\Error\ErrorHandler;
use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use App\GraphQl\ErrorLoggingDecorator;
use GraphQL\Error\Error;
use GraphQL\Error\FormattedError;
use GraphQL\Error\UserError;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\Log\Logger;

use function fopen;
use function rewind;
use function stream_get_contents;
use function substr_count;

/**
 * @see ErrorLoggingDecorator
 */
final class ErrorLoggingDecoratorTest extends TestCase
{
    public function testOnlyAnInternalFailureIsLogged(): void
    {
        $logStream = fopen('php://memory', 'w+');
        self::assertIsResource($logStream);
        $logger = new Logger(LogLevel::DEBUG, $logStream);
        $invalidCursorError = new Error('Invalid cursor.', previous: new UserError('Invalid cursor.'));
        $rejectedMutationError = new Error(
            'Already paid.',
            previous: new UnprocessableEntityHttpException('Already paid.'),
        );
        $malformedIdError = new Error(
            'No route matches "garbage".',
            previous: new InvalidArgumentException('No route matches "garbage".'),
        );
        $internalError = new Error(
            'Connection refused.',
            path: ['unpaid', 'invoice'],
            previous: new RuntimeException('Connection refused.'),
        );

        $formattedErrors = new ErrorLoggingDecorator(new ErrorHandler(), $logger)(
            [$invalidCursorError, $rejectedMutationError, $malformedIdError, $internalError],
            FormattedError::createFromException(...),
        );

        self::assertCount(4, $formattedErrors);
        rewind($logStream);
        $writtenLog = stream_get_contents($logStream);
        self::assertIsString($writtenLog);
        self::assertSame(1, substr_count($writtenLog, "\n"));
        self::assertStringContainsString(
            'GraphQL field unpaid.invoice failed: RuntimeException: Connection refused.',
            $writtenLog,
        );
    }
}
