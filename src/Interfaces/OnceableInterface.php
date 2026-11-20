<?php

namespace EvoMark\InertiaSymfonyBundle\Interfaces;

use BackedEnum;
use DateInterval;
use DateTimeInterface;
use UnitEnum;

/**
 * A prop the client loads once and remembers across navigations.
 */
interface OnceableInterface
{
    /**
     * Resolve the prop once and let the client remember it.
     */
    public function once(bool $value = true): static;

    /**
     * Whether the prop is resolved once.
     */
    public function shouldResolveOnce(): bool;

    /**
     * Whether the prop is resolved again even though the client has it.
     */
    public function shouldBeRefreshed(): bool;

    /**
     * The key the client remembers the prop under, if not its name.
     */
    public function getKey(): ?string;

    /**
     * Remember the prop under a custom key, so pages can share it.
     */
    public function as(\BackedEnum|\UnitEnum|string $key): static;

    /**
     * Let the client remember the prop until the given time, or for a number of seconds.
     */
    public function until(\DateTimeInterface|\DateInterval|int $delay): static;

    /**
     * The expiry timestamp in milliseconds, or null if it never expires.
     */
    public function expiresAt(): ?int;
}
