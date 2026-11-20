<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Interfaces;

/**
 * An SSR gateway that can check whether its SSR server is up.
 */
interface SsrHasHealthCheckInterface
{
    /**
     * Determine if the SSR server is healthy and responsive.
     */
    public function isHealthy(): bool;
}
