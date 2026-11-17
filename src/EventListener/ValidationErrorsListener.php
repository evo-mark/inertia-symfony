<?php

namespace EvoMark\InertiaSymfonyBundle\EventListener;

use EvoMark\InertiaSymfonyBundle\Interfaces\InertiaInterface;
use EvoMark\InertiaSymfonyBundle\Support\Header;
use EvoMark\InertiaSymfonyBundle\Support\SessionKey;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Shares the `errors` prop on every request as an always prop, so partial
 * reloads include it too.
 *
 * Errors are read (and removed, like a flash) from the {@see SessionKey::ERRORS}
 * session attribute, shaped `array<bag, array<field, string|list<string>>>`.
 * Only the first message per field is sent.
 */
#[AsEventListener(event: RequestEvent::class)]
final class ValidationErrorsListener
{
    public function __construct(private InertiaInterface $inertia)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        $this->inertia->share('errors', $this->inertia->always(fn (): object => $this->resolveValidationErrors($request)));
    }

    /**
     * Resolve validation errors for client-side use.
     */
    public function resolveValidationErrors(Request $request): object
    {
        // Only read a session the client already has, so pages don't start one.
        if (!$request->hasPreviousSession()) {
            return (object) [];
        }

        $bags = $request->getSession()->remove(SessionKey::ERRORS);

        if (!\is_array($bags) || [] === $bags) {
            return (object) [];
        }

        $bags = array_map(
            static fn (array $bag): object => (object) array_map(
                static fn (string|array $messages): string => \is_array($messages) ? (string) reset($messages) : $messages,
                $bag,
            ),
            $bags,
        );

        if (isset($bags['default']) && $errorBag = $request->headers->get(Header::ERROR_BAG)) {
            return (object) [$errorBag => $bags['default']];
        }

        return $bags['default'] ?? (object) $bags;
    }
}
