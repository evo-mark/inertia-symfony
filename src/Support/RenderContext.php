<?php

namespace EvoMark\InertiaSymfonyBundle\Support;

use Symfony\Component\HttpFoundation\Request;

/**
 * What a {@see \EvoMark\InertiaSymfonyBundle\Interfaces\ProvidesInertiaPropertiesInterface}
 * knows about the page being rendered.
 */
final readonly class RenderContext
{
    /**
     * @param string  $component the page component being rendered
     * @param Request $request   the current request
     */
    public function __construct(
        public string $component,
        public Request $request,
    ) {
    }
}
