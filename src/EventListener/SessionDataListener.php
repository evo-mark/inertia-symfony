<?php

namespace EvoMark\InertiaSymfonyBundle\EventListener;

use EvoMark\InertiaSymfonyBundle\Config\InertiaConfig;
use EvoMark\InertiaSymfonyBundle\Support\Header;
use EvoMark\InertiaSymfonyBundle\Support\RequestHelper;
use EvoMark\InertiaSymfonyBundle\Support\SessionKey;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Records an Inertia visit's URL and route as the previous location, for
 * Inertia::back() (with `store_previous_url` enabled).
 *
 * Flash data needs no handling here: it stays in the session until a page
 * includes it, so it survives redirects and 409s on its own.
 *
 * Runs before Symfony's own session listener (priority -1000) saves the session.
 */
final class SessionDataListener
{
    /**
     * @param InertiaConfig $config decides whether previous URLs are stored
     */
    public function __construct(private InertiaConfig $config)
    {
    }

    /**
     * Store an Inertia visit's URL and route as the previous location, when it should be.
     */
    public function __invoke(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        if (!$event->isMainRequest() || !$this->shouldStoreCurrentUrl($request, $response)) {
            return;
        }

        $session = $request->getSession();
        $session->set(SessionKey::PREVIOUS_URL, $request->getUri());
        $session->set(SessionKey::PREVIOUS_ROUTE, $request->attributes->get('_route'));
    }

    /**
     * Determine if the visit should be stored as the previous location. Partial
     * reloads are excluded, since deferred props, polling, and infinite scroll
     * requests aren't navigations the user came from.
     */
    private function shouldStoreCurrentUrl(Request $request, Response $response): bool
    {
        if (
            !$this->config->shouldStorePreviousUrl()
            || !$request->headers->has(Header::INERTIA)
            || !$request->hasSession()
            || !$request->isMethod('GET')
            || !$request->attributes->has('_route')
            || !$request->isXmlHttpRequest()
            || RequestHelper::isPrefetch($request)
        ) {
            return false;
        }

        return !self::isPartialReload($request, $response);
    }

    /**
     * Determine if the request is a partial reload of the component that was rendered.
     */
    private static function isPartialReload(Request $request, Response $response): bool
    {
        if (!$component = $request->headers->get(Header::PARTIAL_COMPONENT)) {
            return false;
        }

        if (!$response instanceof JsonResponse) {
            return false;
        }

        $page = json_decode((string) $response->getContent(), true);

        return $component === ($page['component'] ?? null);
    }
}
