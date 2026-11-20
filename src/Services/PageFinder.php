<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Services;

use EvoMark\InertiaSymfonyBundle\Config\InertiaConfig;

/**
 * Locates the frontend page file for an Inertia component name, searching the
 * configured `pages.paths` for each of `pages.extensions`.
 *
 * `Users/Index` (or `Users.Index`) matches `<path>/Users/Index.<extension>`.
 */
class PageFinder
{
    /**
     * Found components and their files. Only hits are cached, so a page added
     * while a worker is running is still found.
     *
     * @var array<string, string>
     */
    private array $found = [];

    /**
     * @param InertiaConfig $config provides the page paths and extensions
     */
    public function __construct(private InertiaConfig $config)
    {
    }

    /**
     * The path of the page file for the given component.
     *
     * @throws \InvalidArgumentException when no page file exists
     */
    public function find(string $component): string
    {
        if (isset($this->found[$component])) {
            return $this->found[$component];
        }

        $name = str_replace('.', '/', $component);

        foreach ($this->config->getPagePaths() as $path) {
            foreach ($this->config->getPageExtensions() as $extension) {
                $file = rtrim($path, '/').'/'.$name.'.'.ltrim($extension, '.');

                if (is_file($file)) {
                    return $this->found[$component] = $file;
                }
            }
        }

        throw new \InvalidArgumentException(\sprintf('Page [%s] not found.', $component));
    }

    /**
     * Whether the given component has a page file.
     */
    public function exists(string $component): bool
    {
        try {
            $this->find($component);

            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }
}
