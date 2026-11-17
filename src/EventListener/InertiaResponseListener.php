<?php

namespace EvoMark\InertiaSymfonyBundle\EventListener;

use EvoMark\InertiaSymfonyBundle\Http\Response as InertiaResponse;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ViewEvent;

/**
 * Turns an Inertia response returned by a controller into a Symfony response.
 * The kernel.response listeners then run on the result as usual.
 */
#[AsEventListener(event: ViewEvent::class)]
final class InertiaResponseListener
{
    public function __invoke(ViewEvent $event): void
    {
        $result = $event->getControllerResult();

        if ($result instanceof InertiaResponse) {
            $event->setResponse($result->toResponse($event->getRequest()));
        }
    }
}
