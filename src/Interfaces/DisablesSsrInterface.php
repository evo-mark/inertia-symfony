<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Interfaces;

/**
 * An SSR gateway whose rendering can be disabled for the current request.
 */
interface DisablesSsrInterface
{
    /**
     * Set the condition that determines if SSR should be disabled.
     */
    public function disable(\Closure|bool $condition): void;
}
