<?php

namespace EvoMark\InertiaSymfonyBundle\Interfaces;

/**
 * A prop that can be left out of the initial page load and fetched afterwards
 * in a group of deferred props.
 */
interface DeferrableInterface
{
    public function shouldDefer(): bool;

    public function group(): string;
}
