<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Interfaces;

interface DisablesSsrInterface
{
    /**
     * Set the condition that determines if SSR should be disabled.
     */
    public function disable(\Closure|bool $condition): void;
}
