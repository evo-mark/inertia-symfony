<?php

namespace EvoMark\InertiaSymfonyBundle\EventListener;

use EvoMark\InertiaSymfonyBundle\Interfaces\InertiaInterface;
use EvoMark\InertiaSymfonyBundle\Support\Header;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * An Inertia request answered with an empty 200 (a controller that returned
 * nothing) redirects back instead.
 *
 * Runs before {@see RedirectStatusListener}, so the redirect still becomes a
 * 303 after PUT, PATCH, DELETE or QUERY.
 */
final class EmptyResponseListener
{
    /**
     * @param InertiaInterface $inertia builds the redirect back
     */
    public function __construct(private InertiaInterface $inertia)
    {
    }

    /**
     * Replace an empty 200 answer to an Inertia request with a redirect back.
     */
    public function __invoke(ResponseEvent $event): void
    {
        $response = $event->getResponse();

        if (
            !$event->isMainRequest()
            || !$event->getRequest()->headers->has(Header::INERTIA)
            || !$response->isOk()
            // Streamed and file responses return false, not '', so they are kept.
            || '' !== $response->getContent()
        ) {
            return;
        }

        $event->setResponse($this->inertia->back());
    }
}
