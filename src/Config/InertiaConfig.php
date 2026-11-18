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

    /**
     * The Vite dev server URL to render with while it is running, overriding the
     * origin it reports (e.g. when PHP runs in a container and Vite does not).
     */
    public function getSsrHotUrl(): ?string
    {
        $url = $this->config['ssr']['hot_url'] ?? null;

        // An empty value (e.g. an unset env var) means no override.
        return null === $url || '' === $url ? null : $url;
    }

    /**
     * The configured path of the built SSR bundle, if any.
     */
    public function getSsrBundle(): ?string
    {
        return $this->config['ssr']['bundle'];
    }

    /**
     * The runtime that runs the SSR bundle, e.g. `node` or `bun`.
     */
    public function getSsrRuntime(): string
    {
        return $this->config['ssr']['runtime'];
    }

    public function shouldEnsureSsrRuntimeExists(): bool
    {
        return $this->config['ssr']['ensure_runtime_exists'];
    }

    public function shouldEnsureSsrBundleExists(): bool
    {
        return $this->config['ssr']['ensure_bundle_exists'];
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

    /**
     * Whether the `errors` prop holds every message per field, rather than the first.
     */
    public function withAllErrors(): bool
    {
        return $this->config['with_all_errors'] ?? false;
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
