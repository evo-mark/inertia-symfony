<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\PageProps;

use EvoMark\InertiaSymfonyBundle\Interfaces\OnceableInterface;
use EvoMark\InertiaSymfonyBundle\Traits\ResolvesCallables;
use EvoMark\InertiaSymfonyBundle\Traits\ResolvesOnce;

class OnceProp implements OnceableInterface
{
    use ResolvesCallables;
    use ResolvesOnce;

    protected mixed $callback;

    public function __construct(callable $callback)
    {
        $this->callback = $callback;
        $this->once = true;
    }

    public function __invoke(): mixed
    {
        return $this->resolveCallable($this->callback);
    }
}
