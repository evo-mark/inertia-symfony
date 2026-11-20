<?php

namespace EvoMark\InertiaSymfonyBundle\Interfaces;

/**
 * A prop that can be left out of the initial page load and fetched afterwards
 * in a group of deferred props.
 */
interface DeferrableInterface
{
    /**
     * Whether the prop is left out of the initial page load.
     */
    public function shouldDefer(): bool;

    /**
     * The group of deferred props the prop is loaded with.
     */
    public function group(): string;
}
