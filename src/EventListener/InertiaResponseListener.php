<?php

namespace EvoMark\InertiaSymfonyBundle\EventListener;

use EvoMark\InertiaSymfonyBundle\Http\Response as InertiaResponse;
use Symfony\Component\HttpKernel\Event\ViewEvent;

/**
 * Turns an Inertia response returned by a controller into a Symfony response.
 * The kernel.response listeners then run on the result as usual.
 */
final class InertiaResponseListener
{
    /**
     * Build the Symfony response for an Inertia response returned by a controller.
     */
    public function __invoke(ViewEvent $event): void
    {
        $result = $event->getControllerResult();

        if ($result instanceof InertiaResponse) {
            $event->setResponse($result->toResponse($event->getRequest()));
        }
    }
}
