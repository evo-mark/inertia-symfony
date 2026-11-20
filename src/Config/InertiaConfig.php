<?php

namespace EvoMark\InertiaSymfonyBundle\Config;

/**
 * The processed bundle configuration, registered as a single service so
 * consumers inject this one object instead of autowiring each setting.
 */
final readonly class InertiaConfig
{
    /**
     * @param array<string, mixed> $config the processed `evo_mark_inertia_symfony` config tree
     */
    public function __construct(private array $config)
    {
    }

    /**
     * The Twig template pages are rendered into on a full page load.
     */
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

    /**
     * The base URL assets are served from, whose hash is the asset version.
     */
    public function getAssetUrl(): ?string
    {
        return $this->config['asset_url'];
    }

    /**
     * The build manifest hashed to derive the asset version.
     */
    public function getManifestPath(): string
    {
        return $this->config['manifest_path'];
    }

    /**
     * Whether server-side rendering is enabled.
     */
    public function isSsr(): bool
    {
        return $this->config['ssr']['enabled'];
    }

    /**
     * The URL of the SSR server.
     */
    public function getSsrUrl(): string
    {
        return $this->config['ssr']['url'];
    }

    /**
     * The SSR request timeout in seconds, or null for the HTTP client's default.
     */
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

    /**
     * Whether inertia:start-ssr checks the runtime exists before starting.
     */
    public function shouldEnsureSsrRuntimeExists(): bool
    {
        return $this->config['ssr']['ensure_runtime_exists'];
    }

    /**
     * Whether SSR is skipped while no built SSR bundle can be found.
     */
    public function shouldEnsureSsrBundleExists(): bool
    {
        return $this->config['ssr']['ensure_bundle_exists'];
    }

    /**
     * Whether a failed render throws an SsrException instead of falling back to client-side rendering.
     */
    public function shouldThrowOnSsrError(): bool
    {
        return $this->config['ssr']['throw_on_error'];
    }

    /**
     * Whether rendering a component without a page file throws a ComponentNotFoundException.
     */
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

    /**
     * Whether every page's browser history is encrypted.
     */
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

    /**
     * Whether pages list their shared prop keys as `sharedProps` metadata.
     */
    public function shouldExposeSharedPropKeys(): bool
    {
        return $this->config['expose_shared_prop_keys'];
    }

    /**
     * Whether Inertia visits are stored as the previous URL for Inertia::back().
     */
    public function shouldStorePreviousUrl(): bool
    {
        return $this->config['store_previous_url'];
    }

    /**
     * Whether integers outside JavaScript's safe range are sent as BigInt markers.
     */
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
