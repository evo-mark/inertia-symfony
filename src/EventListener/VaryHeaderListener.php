<?php

namespace EvoMark\InertiaSymfonyBundle\EventListener;

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

        $event->getResponse()->setVary('X-Inertia', false);
    }
}
