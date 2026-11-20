<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Controller;

use EvoMark\InertiaSymfonyBundle\Interfaces\InertiaInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders an Inertia page straight from a route, for pages that need no
 * controller of their own: the Inertia counterpart of Symfony's
 * TemplateController.
 *
 *     $routes->add('about', '/about')
 *         ->controller(InertiaController::class)
 *         ->defaults(['component' => 'About', 'props' => ['team' => 'Platform']]);
 *
 * Each argument comes from the route's defaults. Route defaults are compiled
 * into the routing cache, so props must be scalars or arrays.
 */
final class InertiaController
{
    /**
     * @param InertiaInterface $inertia renders the page
     */
    public function __construct(private InertiaInterface $inertia)
    {
    }

    /**
     * @param string                  $component  the page component to render
     * @param array<array-key, mixed> $props      the page's props
     * @param array<string, mixed>    $viewData   variables for the root Twig template
     * @param int|null                $maxAge     max age for client caching
     * @param int|null                $sharedAge  max age for shared (proxy) caching
     * @param bool|null               $private    whether caching applies to client caches only
     * @param int                     $statusCode the HTTP status code (200 "OK" by default)
     * @param array<string, string>   $headers    HTTP headers to add to the response
     */
    public function __invoke(
        Request $request,
        string $component,
        array $props = [],
        array $viewData = [],
        ?int $maxAge = null,
        ?int $sharedAge = null,
        ?bool $private = null,
        int $statusCode = Response::HTTP_OK,
        array $headers = [],
    ): Response {
        $response = $this->inertia->render($component, $props, $viewData)
            ->toResponse($request)
            ->setStatusCode($statusCode);

        // The same caching rules as TemplateController.
        if ($maxAge) {
            $response->setMaxAge($maxAge);
        }

        if (null !== $sharedAge) {
            $response->setSharedMaxAge($sharedAge);
        }

        if ($private) {
            $response->setPrivate();
        } elseif (false === $private || (null === $private && (null !== $maxAge || null !== $sharedAge))) {
            $response->setPublic();
        }

        foreach ($headers as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }
}
