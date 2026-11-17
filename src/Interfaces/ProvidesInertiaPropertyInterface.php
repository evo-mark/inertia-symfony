<?php

namespace EvoMark\InertiaSymfonyBundle\Interfaces;

use EvoMark\InertiaSymfonyBundle\Support\PropertyContext;

/**
 * Resolves the value of a single keyed prop.
 */
interface ProvidesInertiaPropertyInterface
{
    public function toInertiaProperty(PropertyContext $prop): mixed;
}
