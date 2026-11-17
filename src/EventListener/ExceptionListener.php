<?php

namespace EvoMark\InertiaSymfonyBundle\EventListener;

use EvoMark\InertiaSymfonyBundle\Http\ExceptionResponse;
use EvoMark\InertiaSymfonyBundle\Http\Response as InertiaResponse;
use EvoMark\InertiaSymfonyBundle\Interfaces\InertiaInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * Passes thrown exceptions to the callback registered with
 * Inertia::handleExceptionsUsing(), which can answer with an Inertia error page
 * or any other response instead of Symfony's error page.
 *
 * Runs after the exception is logged (priority 0) and before Symfony's error
 * page is rendered (priority -128). The kernel.response listeners still run on
 * the response it sets, so redirects become 303s and so on as usual.
 */
#[AsEventListener(event: ExceptionEvent::class, priority: -64)]
final class ExceptionListener
{
    public function __construct(private InertiaInterface $inertia)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $handler = $this->inertia->getExceptionHandler();

        if (null === $handler || !$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $exception = new ExceptionResponse($event->getThrowable(), $request, $this->inertia);

        $result = $handler($exception);

        if ($result instanceof ExceptionResponse) {
            if ($result->isRendered()) {
                $event->setResponse($result->toResponse());
            }

            return;
        }

        if ($result instanceof InertiaResponse) {
            $event->setResponse($result->toResponse($request)->setStatusCode($exception->statusCode()));

            return;
        }

        if ($result instanceof Response) {
            $event->setResponse($result);
        }
    }
}
