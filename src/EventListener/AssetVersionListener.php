<?php

namespace EvoMark\InertiaSymfonyBundle\EventListener;

use EvoMark\InertiaSymfonyBundle\Config\InertiaConfig;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * When the client's asset version is stale, answer a GET Inertia visit with a
 * 409 so the client performs a full page reload to the requested location.
 */
#[AsEventListener(event: RequestEvent::class)]
class AssetVersionListener
{
    public function __construct(private InertiaConfig $config)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $version = $this->config->getVersion();

        if (
            !$event->isMainRequest()
            || null === $version
            || 'GET' !== $request->getMethod()
            || !$request->headers->has('X-Inertia')
            || $request->headers->get('X-Inertia-Version') === $version
        ) {
            return;
        }

        $event->setResponse(new Response('', Response::HTTP_CONFLICT, [
            'X-Inertia-Location' => $request->getUri(),
        ]));
    }
}
