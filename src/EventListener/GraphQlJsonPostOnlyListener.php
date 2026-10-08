<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * API Platform also executes a query string or a form body, both of which a page on another site can send.
 */
#[AsEventListener(KernelEvents::REQUEST)]
final readonly class GraphQlJsonPostOnlyListener
{
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->attributes->get('_route') !== 'api_graphql_entrypoint') {
            return;
        }

        if (!$request->isMethod('POST')) {
            // a GET with no query only opens GraphiQL
            if ($request->query->has('query')) {
                throw new MethodNotAllowedHttpException(['POST']);
            }

            return;
        }

        if ($request->getContentTypeFormat() !== 'json') {
            throw new UnsupportedMediaTypeHttpException('A GraphQL request must be sent as application/json.');
        }
    }
}
