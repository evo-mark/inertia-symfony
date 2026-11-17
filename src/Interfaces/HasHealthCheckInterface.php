<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Interfaces;

interface HasHealthCheckInterface
{
    /**
     * Whether the Server Side Rendering engine is up and responding.
     */
    public function isHealthy(): bool;
}
