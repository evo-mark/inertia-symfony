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
     * The configured asset version, or null to derive it from asset_url or the manifest.
     */
    public function getVersion(): ?string
    {
        return $this->config['version'];
    }

    public function getAssetUrl(): ?string
    {
        return $this->config['asset_url'];
    }

    public function getManifestPath(): string
    {
        return $this->config['manifest_path'];
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

    public function shouldThrowOnSsrError(): bool
    {
        return $this->config['ssr']['throw_on_error'];
    }

    public function shouldEnsurePagesExist(): bool
    {
        return $this->config['pages']['ensure_pages_exist'];
    }

    /**
     * @return list<string>
     */
    public function getPagePaths(): array
    {
        return $this->config['pages']['paths'];
    }

    /**
     * @return list<string>
     */
    public function getPageExtensions(): array
    {
        return $this->config['pages']['extensions'];
    }

    public function shouldEncryptHistory(): bool
    {
        return $this->config['history']['encrypt'];
    }

    public function shouldExposeSharedPropKeys(): bool
    {
        return $this->config['expose_shared_prop_keys'];
    }

    public function shouldStorePreviousUrl(): bool
    {
        return $this->config['store_previous_url'];
    }

    public function shouldPreserveBigIntegers(): bool
    {
        return $this->config['preserve_big_integers'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->config;
    }
}
