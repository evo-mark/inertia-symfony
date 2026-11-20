<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\PageProps;

use EvoMark\InertiaSymfonyBundle\Interfaces\IgnoreFirstLoadInterface;
use EvoMark\InertiaSymfonyBundle\Interfaces\OnceableInterface;
use EvoMark\InertiaSymfonyBundle\Traits\ResolvesCallables;
use EvoMark\InertiaSymfonyBundle\Traits\ResolvesOnce;

/**
 * A prop only sent when a partial reload asks for it.
 */
class OptionalProp implements IgnoreFirstLoadInterface, OnceableInterface
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

    /**
     * Resolve the prop's value.
     */
    public function __invoke(): mixed
    {
        return $this->resolveCallable($this->callback);
    }
}
