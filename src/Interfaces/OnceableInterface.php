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
    public function once(bool $value = true): static;

    public function shouldResolveOnce(): bool;

    public function shouldBeRefreshed(): bool;

    public function getKey(): ?string;

    public function as(\BackedEnum|\UnitEnum|string $key): static;

    public function until(\DateTimeInterface|\DateInterval|int $delay): static;

    /**
     * The expiry timestamp in milliseconds, or null if it never expires.
     */
    public function expiresAt(): ?int;
}
