<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Ssr;

use EvoMark\InertiaSymfonyBundle\Config\InertiaConfig;

/**
 * Detects a running Vite dev server from the `entrypoints.json` Reprise writes
 * next to the build manifest: in dev it is marked `"isProd": false` and names
 * the dev server's origin.
 *
 * Reprise does not rewrite the file when the dev server stops, so a stale file
 * still reports one until the next build. Requests to it then fail like any
 * unreachable SSR server.
 */
class DevServerDetector
{
    /**
     * @param InertiaConfig $config provides the build manifest path
     */
    public function __construct(private InertiaConfig $config)
    {
    }

    /**
     * The origin of the running dev server, e.g. `http://127.0.0.1:5173`, or null.
     */
    public function getOrigin(): ?string
    {
        $file = \dirname($this->config->getManifestPath()).'/entrypoints.json';

        if (!is_file($file)) {
            return null;
        }

        try {
            $entrypoints = json_decode((string) file_get_contents($file), true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!\is_array($entrypoints) || false !== ($entrypoints['isProd'] ?? null)) {
            return null;
        }

        $origin = $entrypoints['devServer']['origin'] ?? null;

        return \is_string($origin) && '' !== $origin ? $origin : null;
    }

    /**
     * Whether a Vite dev server is running.
     */
    public function isRunning(): bool
    {
        return null !== $this->getOrigin();
    }
}
