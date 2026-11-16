<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\PageProps;

use EvoMark\InertiaSymfonyBundle\Traits\MergesProps;
use EvoMark\InertiaSymfonyBundle\Traits\ResolvesCallables;
use EvoMark\InertiaSymfonyBundle\Traits\ResolvesOnce;

class MergeProp
{
    use MergesProps;
    use ResolvesCallables;
    use ResolvesOnce;

    public function __construct(protected mixed $value)
    {
        $this->merge = true;
    }

    public function __invoke(): mixed
    {
        return $this->resolveCallable($this->value);
    }
}
