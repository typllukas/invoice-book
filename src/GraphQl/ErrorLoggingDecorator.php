<?php

declare(strict_types=1);

namespace App\GraphQl;

use ApiPlatform\GraphQl\Error\ErrorHandlerInterface;
use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use GraphQL\Error\Error;
use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

use function implode;

/**
 * A resolver exception ends up in the errors of a 200 response, so the kernel exception listener never logs it.
 */
#[AsDecorator('api_platform.graphql.error_handler')]
final readonly class ErrorLoggingDecorator implements ErrorHandlerInterface
{
    public function __construct(
        private ErrorHandlerInterface $decorated,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<Error> $errors
     *
     * @return array<mixed>
     */
    #[Override]
    public function __invoke(array $errors, callable $formatter): array
    {
        foreach ($errors as $error) {
            $exception = $error->getPrevious();
            if (
                $exception === null
                || $error->isClientSafe()
                || ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() < 500)
                // a malformed id from the client fails IRI conversion
                || $exception instanceof InvalidArgumentException
            ) {
                continue;
            }

            $this->logger->critical(
                'GraphQL field {graphql_path} failed: {exception_class}: {exception_message}'
                . ' at {exception_file}:{exception_line}',
                [
                    'graphql_path' => implode('.', $error->getPath() ?? []),
                    'exception_class' => $exception::class,
                    'exception_message' => $exception->getMessage(),
                    'exception_file' => $exception->getFile(),
                    'exception_line' => $exception->getLine(),
                ],
            );
        }

        return ($this->decorated)($errors, $formatter);
    }
}
