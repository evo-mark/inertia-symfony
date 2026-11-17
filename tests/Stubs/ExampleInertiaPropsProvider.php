<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\Stubs;

use EvoMark\InertiaSymfonyBundle\Interfaces\ProvidesInertiaPropertiesInterface;
use EvoMark\InertiaSymfonyBundle\Support\RenderContext;

final class ExampleInertiaPropsProvider implements ProvidesInertiaPropertiesInterface
{
    /**
     * @param array<string, mixed> $properties
     */
    public function __construct(
        private array $properties,
    ) {
    }

    /**
     * @return iterable<string, mixed>
     */
    public function toInertiaProperties(RenderContext $context): iterable
    {
        return $this->properties;
    }
}
