<?php

namespace EvoMark\InertiaSymfonyBundle\Traits;

use function Symfony\Component\Clock\now;

trait ResolvesOnce
{
    protected bool $once = false;
    protected bool $refresh = false;
    protected ?int $ttl = null;
    protected ?string $key = null;

    // once(), shouldResolveOnce(), shouldBeRefreshed(), getKey(),
    // as() and fresh() are copied verbatim from the Laravel trait.

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
