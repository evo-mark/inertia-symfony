<?php

namespace EvoMark\InertiaSymfonyBundle\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Inertia requires a 303 (not 302) when redirecting after PUT, PATCH, DELETE or QUERY,
 * otherwise the browser would repeat the redirect using the original method.
 */
#[AsEventListener(event: ResponseEvent::class)]
class RedirectStatusListener
{
    public function __invoke(ResponseEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || !$request->headers->has('X-Inertia')) {
            return;
        }

        $response = $event->getResponse();

        if (
            Response::HTTP_FOUND === $response->getStatusCode()
            && \in_array($request->getMethod(), ['PUT', 'PATCH', 'DELETE', 'QUERY'], true)
        ) {
            $response->setStatusCode(Response::HTTP_SEE_OTHER);
        }
    }
}
