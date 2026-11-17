<?php

namespace EvoMark\InertiaSymfonyBundle\EventListener;

use EvoMark\InertiaSymfonyBundle\Support\Header;
use EvoMark\InertiaSymfonyBundle\Support\RequestHelper;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * An XHR redirect loses its URL fragment, so an Inertia redirect to a URL with
 * a `#fragment` becomes a 409 carrying the target in `X-Inertia-Redirect`, for
 * the client to visit itself.
 *
 * Runs after {@see RedirectStatusListener}.
 */
#[AsEventListener(event: ResponseEvent::class, priority: -8)]
final class FragmentRedirectListener
{
    public function __invoke(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        if (
            !$event->isMainRequest()
            || !$request->headers->has(Header::INERTIA)
            || !$response->isRedirect()
            || !str_contains((string) $response->headers->get('Location'), '#')
            || RequestHelper::isPrefetch($request)
        ) {
            return;
        }

        $event->setResponse(new Response('', Response::HTTP_CONFLICT, [
            Header::REDIRECT => $response->headers->get('Location'),
        ]));
    }
}
