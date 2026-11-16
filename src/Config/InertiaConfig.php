<?php

namespace EvoMark\InertiaSymfonyBundle\Config;

/**
 * The processed bundle configuration, registered as a single service so
 * consumers inject this one object instead of autowiring each setting.
 */
final readonly class InertiaConfig
{
    /**
     * @param array<string, mixed> $config the processed `inertia_symfony` config tree
     */
    public function __construct(private array $config)
    {
    }

    public function getRootView(): string
    {
        return $this->config['root_view'];
    }

    /**
     * The current asset version, or null to disable version checking.
     */
    public function getVersion(): ?string
    {
        return $this->config['version'];
    }

    public function isSsr(): bool
    {
        return $this->config['ssr']['enabled'];
    }

    public function getSsrUrl(): string
    {
        return $this->config['ssr']['url'];
    }

    public function getSsrTimeout(): ?int
    {
        return $this->config['ssr']['timeout'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->config;
    }
}
