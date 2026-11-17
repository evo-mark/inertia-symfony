<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\Stubs;

use EvoMark\InertiaSymfonyBundle\Interfaces\InertiaInterface;
use EvoMark\InertiaSymfonyBundle\Interfaces\ProvidesInertiaPropertyInterface;
use EvoMark\InertiaSymfonyBundle\Support\PropertyContext;

/**
 * Merges its items with the shared prop of the same key.
 */
final class MergeWithSharedProp implements ProvidesInertiaPropertyInterface
{
    /**
     * @param array<int, mixed> $items
     */
    public function __construct(
        private InertiaInterface $inertia,
        private array $items = [],
    ) {
    }

    public function toInertiaProperty(PropertyContext $prop): mixed
    {
        return array_merge($this->inertia->getShared($prop->key, []), $this->items);
    }
}
