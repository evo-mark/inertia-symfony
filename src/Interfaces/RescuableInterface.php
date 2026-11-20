<?php

namespace EvoMark\InertiaSymfonyBundle\Interfaces;

/**
 * A prop whose resolution errors are caught and reported, rather than failing the response.
 */
interface RescuableInterface
{
    /**
     * Whether errors while resolving the prop are reported instead of thrown.
     */
    public function shouldRescue(): bool;
}
