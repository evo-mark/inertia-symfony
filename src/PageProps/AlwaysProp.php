<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\PageProps;

use EvoMark\InertiaSymfonyBundle\Traits\ResolvesCallables;

/**
 * A prop that is always included, even when a partial reload doesn't ask for it.
 */
class AlwaysProp
{
    use ResolvesCallables;

    /**
     * @param mixed $value the value, or a closure returning it
     */
    public function __construct(protected mixed $value)
    {
    }

    /**
     * Resolve the prop's value.
     */
    public function __invoke()
    {
        return $this->resolveCallable($this->value);
    }
}
