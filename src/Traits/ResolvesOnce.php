<?php

namespace EvoMark\InertiaSymfonyBundle\Traits;

use function Symfony\Component\Clock\now;

trait ResolvesOnce
{
    protected bool $once = false;
    protected bool $refresh = false;
    protected ?int $ttl = null;
    protected ?string $key = null;

    /**
     * Mark this prop to be resolved once and remembered by the client,
     * optionally under a custom key and with an expiry.
     */
    public function once(bool $value = true, ?string $as = null, \DateTimeInterface|\DateInterval|int|null $until = null): static
    {
        $this->once = $value;

        if (null !== $as) {
            $this->as($as);
        }

        if (null !== $until) {
            $this->until($until);
        }

        return $this;
    }

    public function shouldResolveOnce(): bool
    {
        return $this->once;
    }

    public function shouldBeRefreshed(): bool
    {
        return $this->refresh;
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    /**
     * Remember the prop under a custom key, so it can be shared across pages.
     */
    public function as(\BackedEnum|\UnitEnum|string $key): static
    {
        $this->key = match (true) {
            $key instanceof \BackedEnum => (string) $key->value,
            $key instanceof \UnitEnum => $key->name,
            default => $key,
        };

        return $this;
    }

    /**
     * Force the prop to be resolved again, even if the client already has it.
     */
    public function fresh(bool $value = true): static
    {
        $this->refresh = $value;

        return $this;
    }

    public function until(\DateTimeInterface|\DateInterval|int $delay): static
    {
        $this->ttl = $this->secondsUntil($delay);

        return $this;
    }

    /**
     * Get the expiration timestamp in milliseconds for the once prop.
     */
    public function expiresAt(): ?int
    {
        if (null === $this->ttl) {
            return null;
        }

        return (now()->getTimestamp() + $this->ttl) * 1000;
    }

    private function secondsUntil(\DateTimeInterface|\DateInterval|int $delay): int
    {
        $now = now();

        $target = match (true) {
            $delay instanceof \DateInterval => $now->add($delay),
            $delay instanceof \DateTimeInterface => $delay,
            default => $now->modify("+{$delay} seconds"),
        };

        return max(0, $target->getTimestamp() - $now->getTimestamp());
    }
}
