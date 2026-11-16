<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\PageProps;

use EvoMark\InertiaSymfonyBundle\Traits\DefersProps;
use EvoMark\InertiaSymfonyBundle\Traits\MergesProps;
use EvoMark\InertiaSymfonyBundle\Traits\ResolvesCallables;
use EvoMark\InertiaSymfonyBundle\Traits\ResolvesOnce;

class DeferProp
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
     *
     * @return mixed
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
