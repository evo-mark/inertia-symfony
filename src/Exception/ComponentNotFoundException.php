<?php

namespace EvoMark\InertiaSymfonyBundle\Exception;

use InvalidArgumentException;

/**
 * Thrown when `pages.ensure_pages_exist` is on and a rendered component has no
 * page file in the configured page paths.
 */
class ComponentNotFoundException extends \InvalidArgumentException
{
}
