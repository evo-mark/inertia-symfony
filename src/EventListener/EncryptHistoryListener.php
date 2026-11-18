<?php

namespace EvoMark\InertiaSymfonyBundle\EventListener;

use EvoMark\InertiaSymfonyBundle\Attribute\EncryptHistory;
use EvoMark\InertiaSymfonyBundle\Interfaces\InertiaInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

/**
 * Applies #[EncryptHistory] and the `_encrypt_history` route default to the
 * request, overriding the `history.encrypt` config for it.
 *
 * The route default wins over the attribute, since one controller can serve
 * several routes; an action's attribute wins over its class's.
 */
#[AsEventListener(event: ControllerEvent::class)]
final class EncryptHistoryListener
{
    /**
     * The route default (or request attribute) that sets encryption for a route.
     */
    public const string ROUTE_DEFAULT = '_encrypt_history';

    public function __construct(private InertiaInterface $inertia)
    {
    }

    public function __invoke(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $routeDefault = $event->getRequest()->attributes->get(self::ROUTE_DEFAULT);

        if (null !== $routeDefault) {
            $this->inertia->encryptHistory(filter_var($routeDefault, \FILTER_VALIDATE_BOOL));

            return;
        }

        // Class attributes come first, so an action's attribute is applied last.
        $attributes = $event->getAttributes(EncryptHistory::class);

        if ([] !== $attributes) {
            $this->inertia->encryptHistory(end($attributes)->encrypt);
        }
    }
}
