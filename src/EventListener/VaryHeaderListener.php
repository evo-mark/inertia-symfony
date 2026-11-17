<?php

namespace EvoMark\InertiaSymfonyBundle\EventListener;

use EvoMark\InertiaSymfonyBundle\Support\Header;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * The same URL returns HTML or Inertia JSON depending on the X-Inertia header,
 * so caches must vary on it.
 */
#[AsEventListener(event: ResponseEvent::class)]
class VaryHeaderListener
{
    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();

        // Keep existing values, drop empty ones and don't add X-Inertia twice.
        $vary = array_values(array_unique(array_filter([...$response->getVary(), Header::INERTIA])));

        $response->setVary($vary);
    }
}
