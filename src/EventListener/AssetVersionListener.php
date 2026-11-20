<?php

namespace EvoMark\InertiaSymfonyBundle\EventListener;

use EvoMark\InertiaSymfonyBundle\Interfaces\InertiaInterface;
use EvoMark\InertiaSymfonyBundle\Support\Header;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * When the client's asset version is stale, answer a GET Inertia visit with a
 * 409 so the client performs a full page reload to the requested location.
 *
 * This runs before the controller, which skips work whose result would be
 * thrown away and leaves flash data in the session for the reloaded page.
 */
class AssetVersionListener
{
    /**
     * @param InertiaInterface $inertia provides the current asset version
     */
    public function __construct(private InertiaInterface $inertia)
    {
    }

    /**
     * Answer a stale GET Inertia visit with a 409 for a full page reload.
     */
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        $version = $this->inertia->getVersion();

        if (
            !$event->isMainRequest()
            || 'GET' !== $request->getMethod()
            || !$request->headers->has(Header::INERTIA)
            || $request->headers->get(Header::VERSION, '') === $version
        ) {
            return;
        }

        $event->setResponse(new Response('', Response::HTTP_CONFLICT, [
            Header::LOCATION => $request->getUri(),
            Header::VERSION => $version,
        ]));
    }
}
