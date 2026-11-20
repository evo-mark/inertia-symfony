<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Ssr;

use EvoMark\InertiaSymfonyBundle\Config\InertiaConfig;

/**
 * Finds the built SSR bundle: the configured `ssr.bundle`, otherwise the first
 * of the common Vite output names in `var/ssr/` that exists.
 */
class BundleDetector
{
    /**
     * Where an SSR build is looked for when `ssr.bundle` does not exist,
     * relative to the project directory.
     */
    private const array FALLBACKS = [
        'var/ssr/ssr.js',
        'var/ssr/ssr.mjs',
        'var/ssr/app.js',
        'var/ssr/app.mjs',
    ];

    /**
     * @param InertiaConfig $config     provides the configured bundle path
     * @param string        $projectDir the directory the fallback paths are relative to
     */
    public function __construct(
        private InertiaConfig $config,
        private string $projectDir,
    ) {
    }

    /**
     * Detect and return the path to the SSR bundle file.
     */
    public function detect(): ?string
    {
        $candidates = [
            $this->config->getSsrBundle(),
            ...array_map(fn (string $path): string => $this->projectDir.'/'.$path, self::FALLBACKS),
        ];

        foreach ($candidates as $path) {
            if (null !== $path && '' !== $path && is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
