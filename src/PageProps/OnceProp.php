<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\PageProps;

use EvoMark\InertiaSymfonyBundle\Interfaces\OnceableInterface;
use EvoMark\InertiaSymfonyBundle\Traits\ResolvesCallables;
use EvoMark\InertiaSymfonyBundle\Traits\ResolvesOnce;

/**
 * A prop resolved once and remembered by the client across visits.
 */
class OnceProp implements OnceableInterface
{
    use ResolvesCallables;
    use ResolvesOnce;

    protected mixed $callback;

    /**
     * @param callable $callback resolves the value
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
        $this->once = true;
    }

    /**
     * Resolve the prop's value.
     */
    public function __invoke(): mixed
    {
        return $this->resolveCallable($this->callback);
    }
}
