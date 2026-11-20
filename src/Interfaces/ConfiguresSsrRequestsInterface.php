<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Interfaces;

/**
 * An SSR gateway whose HTTP requests to the SSR server can be adjusted.
 */
interface ConfiguresSsrRequestsInterface
{
    /**
     * Configure the HTTP request that is sent to the SSR server.
     */
    public function configureRequestUsing(?\Closure $callback = null): void;
}
