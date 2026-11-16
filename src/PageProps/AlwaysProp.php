<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\PageProps;

use EvoMark\InertiaSymfonyBundle\Traits\ResolvesCallables;

class AlwaysProp
{
    use ResolvesCallables;

    public function __construct(protected mixed $value)
    {
    }

    public function __invoke()
    {
        return $this->resolveCallable($this->value);
    }
}
