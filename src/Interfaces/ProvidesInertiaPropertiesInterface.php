<?php

namespace EvoMark\InertiaSymfonyBundle\Interfaces;

use EvoMark\InertiaSymfonyBundle\Support\RenderContext;

/**
 * Contributes several props to a page at once.
 *
 * Pass an instance without a key in the props array (or as the props argument
 * itself) and its properties are merged in at that position.
 */
interface ProvidesInertiaPropertiesInterface
{
    /**
     * @return iterable<string, mixed>
     */
    public function toInertiaProperties(RenderContext $context): iterable;
}
