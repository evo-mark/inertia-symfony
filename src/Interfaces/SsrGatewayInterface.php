<?php

namespace EvoMark\InertiaSymfonyBundle\Interfaces;

use EvoMark\InertiaSymfonyBundle\Ssr\Response;

interface SsrGatewayInterface
{
    /**
     * Dispatch the Inertia page to the Server Side Rendering engine.
     */
    public function dispatch(array $page): ?Response;
}
