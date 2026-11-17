<?php

namespace EvoMark\InertiaSymfonyBundle\Interfaces;

/**
 * A prop whose resolution errors are caught and reported, rather than failing the response.
 */
interface RescuableInterface
{
    public function shouldRescue(): bool;
}
