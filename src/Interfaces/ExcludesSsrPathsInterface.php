<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Interfaces;

/**
 * An SSR gateway that can skip rendering for some paths.
 */
interface ExcludesSsrPathsInterface
{
    /**
     * Exclude the given paths from server-side rendering.
     *
     * @param array<int, string>|string $paths
     */
    public function except(array|string $paths): void;
}
