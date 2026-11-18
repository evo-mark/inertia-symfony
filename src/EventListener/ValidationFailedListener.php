<?php

namespace EvoMark\InertiaSymfonyBundle\EventListener;

use EvoMark\InertiaSymfonyBundle\Interfaces\InertiaInterface;
use EvoMark\InertiaSymfonyBundle\Support\Header;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Sends an Inertia form back with its validation errors.
 *
 * When `#[MapRequestPayload]` or `#[MapQueryString]` can't build a valid object,
 * Symfony throws an HTTP exception (422 by default) wrapping a
 * ValidationFailedException. An Inertia form expects a redirect back instead,
 * with the errors in the `errors` prop of the next page: this stores the
 * violations for that page and redirects back. Other requests, a JSON API's
 * say, keep Symfony's own response.
 *
 * Runs before the exception is logged (priority 0): a rejected form submission
 * is not an application error.
 */
#[AsEventListener(event: ExceptionEvent::class, priority: 16)]
final class ValidationFailedListener
{
    public function __construct(private InertiaInterface $inertia)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest() || !$event->getRequest()->headers->has(Header::INERTIA)) {
            return;
        }

        if (null === $failure = self::validationFailure($event->getThrowable())) {
            return;
        }

        $event->setResponse($this->inertia->withErrors($failure->getViolations())->back());
    }

    /**
     * The ValidationFailedException the throwable is, or wraps.
     */
    private static function validationFailure(\Throwable $throwable): ?ValidationFailedException
    {
        for ($current = $throwable; null !== $current; $current = $current->getPrevious()) {
            if ($current instanceof ValidationFailedException) {
                return $current;
            }
        }

        return null;
    }
}
