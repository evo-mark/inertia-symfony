<?php

namespace EvoMark\InertiaSymfonyBundle\Support;

use Symfony\Component\HttpFoundation\Request;

/**
 * What a {@see \EvoMark\InertiaSymfonyBundle\Interfaces\ProvidesInertiaPropertyInterface}
 * knows about the prop it is resolving.
 */
final readonly class PropertyContext
{
    /**
     * @param string               $key     the prop's key
     * @param array<string, mixed> $props   all props on the page
     * @param Request              $request the current request
     */
    public function __construct(
        public string $key,
        public array $props,
        public Request $request,
    ) {
    }
}
