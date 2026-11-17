<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\PageProps;

use EvoMark\InertiaSymfonyBundle\Interfaces\DeferrableInterface;
use EvoMark\InertiaSymfonyBundle\Interfaces\IgnoreFirstLoadInterface;
use EvoMark\InertiaSymfonyBundle\Interfaces\MergeableInterface;
use EvoMark\InertiaSymfonyBundle\Interfaces\OnceableInterface;
use EvoMark\InertiaSymfonyBundle\Interfaces\RescuableInterface;
use EvoMark\InertiaSymfonyBundle\Traits\DefersProps;
use EvoMark\InertiaSymfonyBundle\Traits\MergesProps;
use EvoMark\InertiaSymfonyBundle\Traits\ResolvesCallables;
use EvoMark\InertiaSymfonyBundle\Traits\ResolvesOnce;

class DeferProp implements DeferrableInterface, IgnoreFirstLoadInterface, MergeableInterface, OnceableInterface, RescuableInterface
{
    use DefersProps;
    use MergesProps;
    use ResolvesCallables;
    use ResolvesOnce;

    protected mixed $callback;
    protected bool $rescue;

    public function __construct(callable $callback, ?string $group = null, bool $rescue = false)
    {
        $this->callback = $callback;
        $this->rescue = $rescue;
        $this->defer($group);
    }

    /**
     * Resolve the property value.
     */
    public function __invoke()
    {
        return $this->resolveCallable($this->callback);
    }

    /**
     * Determine if deferred resolution errors should be rescued.
     */
    public function shouldRescue(): bool
    {
        return $this->rescue;
    }
}
