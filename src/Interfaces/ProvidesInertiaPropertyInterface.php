<?php

namespace EvoMark\InertiaSymfonyBundle\Interfaces;

use EvoMark\InertiaSymfonyBundle\Support\PropertyContext;

/**
 * Resolves the value of a single keyed prop.
 */
interface ProvidesInertiaPropertyInterface
{
    /**
     * The prop's value.
     *
     * @param PropertyContext $prop the prop's key, the page's props and the request
     */
    public function toInertiaProperty(PropertyContext $prop): mixed;
}
