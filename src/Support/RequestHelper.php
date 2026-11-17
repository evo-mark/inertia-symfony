<?php

namespace EvoMark\InertiaSymfonyBundle\Support;

use Symfony\Component\HttpFoundation\Request;

/**
 * Request checks shared by the bundle's listeners.
 */
final class RequestHelper
{
    /**
     * Whether the request is a browser or Inertia prefetch, not a visit.
     */
    public static function isPrefetch(Request $request): bool
    {
        return 0 === strcasecmp((string) $request->server->get('HTTP_X_MOZ'), 'prefetch')
            || 0 === strcasecmp((string) $request->headers->get('Purpose'), 'prefetch')
            || 0 === strcasecmp((string) $request->headers->get('Sec-Purpose'), 'prefetch');
    }
}
