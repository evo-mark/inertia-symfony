<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\PageProps;

use EvoMark\InertiaSymfonyBundle\Traits\ResolvesCallables;
use EvoMark\InertiaSymfonyBundle\Traits\ResolvesOnce;

class OptionalProp
{
    use ResolvesCallables;
    use ResolvesOnce;

    protected mixed $callback;

    /**
     * Create a new optional property instance. Optional properties are only
     * included when explicitly requested via partial reloads, reducing
     * initial payload size and improving performance.
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;

    }

    public function __invoke(): mixed
    {
        return $this->resolveCallable($this->callback);
    }
}
