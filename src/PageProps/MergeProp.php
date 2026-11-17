<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\PageProps;

use EvoMark\InertiaSymfonyBundle\Interfaces\MergeableInterface;
use EvoMark\InertiaSymfonyBundle\Interfaces\OnceableInterface;
use EvoMark\InertiaSymfonyBundle\Traits\MergesProps;
use EvoMark\InertiaSymfonyBundle\Traits\ResolvesCallables;
use EvoMark\InertiaSymfonyBundle\Traits\ResolvesOnce;

class MergeProp implements MergeableInterface, OnceableInterface
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
