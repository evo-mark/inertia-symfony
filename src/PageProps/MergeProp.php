<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\PageProps;

use EvoMark\InertiaSymfonyBundle\Interfaces\MergeableInterface;
use EvoMark\InertiaSymfonyBundle\Interfaces\OnceableInterface;
use EvoMark\InertiaSymfonyBundle\Traits\MergesProps;
use EvoMark\InertiaSymfonyBundle\Traits\ResolvesCallables;
use EvoMark\InertiaSymfonyBundle\Traits\ResolvesOnce;

/**
 * A prop merged with the value the client already has, rather than replacing it.
 */
class MergeProp implements MergeableInterface, OnceableInterface
{
    use MergesProps;
    use ResolvesCallables;
    use ResolvesOnce;

    /**
     * @param mixed $value the value, or a closure returning it
     */
    public function __construct(protected mixed $value)
    {
        $this->merge = true;
    }

    /**
     * Resolve the prop's value.
     */
    public function __invoke(): mixed
    {
        return $this->resolveCallable($this->value);
    }
}
