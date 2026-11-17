<?php

namespace EvoMark\InertiaSymfonyBundle\Interfaces;

/**
 * A prop whose value the client merges with the data it already has, rather than replacing it.
 */
interface MergeableInterface
{
    public function merge(): static;

    public function shouldMerge(): bool;

    public function shouldDeepMerge(): bool;

    /**
     * @return array<int, string>
     */
    public function matchesOn(): array;

    public function appendsAtRoot(): bool;

    public function prependsAtRoot(): bool;

    /**
     * @return array<int, string>
     */
    public function appendsAtPaths(): array;

    /**
     * @return array<int, string>
     */
    public function prependsAtPaths(): array;
}
