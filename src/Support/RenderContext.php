<?php

namespace EvoMark\InertiaSymfonyBundle\Support;

use Symfony\Component\HttpFoundation\Request;

/**
 * What a {@see \EvoMark\InertiaSymfonyBundle\Interfaces\ProvidesInertiaPropertiesInterface}
 * knows about the page being rendered.
 */
final readonly class RenderContext
{
    public function __construct(
        public string $component,
        public Request $request,
    ) {
    }
}
