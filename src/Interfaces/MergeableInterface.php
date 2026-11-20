<?php

namespace EvoMark\InertiaSymfonyBundle\Interfaces;

/**
 * A prop whose value the client merges with the data it already has, rather than replacing it.
 */
interface MergeableInterface
{
    /**
     * Merge the prop with the client's existing value.
     */
    public function merge(): static;

    /**
     * Whether the prop is merged with the client's existing value.
     */
    public function shouldMerge(): bool;

    /**
     * Whether the prop is deep merged.
     */
    public function shouldDeepMerge(): bool;

    /**
     * @return array<int, string>
     */
    public function matchesOn(): array;

    /**
     * Whether the prop is appended to the existing value as a whole.
     */
    public function appendsAtRoot(): bool;

    /**
     * Whether the prop is prepended to the existing value as a whole.
     */
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
