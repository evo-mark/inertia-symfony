<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Interfaces;

interface ConfiguresSsrRequestsInterface
{
    /**
     * Configure the HTTP request that is sent to the SSR server.
     */
    public function configureRequestUsing(?\Closure $callback = null): void;
}
