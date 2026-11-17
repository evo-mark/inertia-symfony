<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\Stubs;

use EvoMark\InertiaSymfonyBundle\Interfaces\ProvidesScrollMetadataInterface;

/**
 * A fixed scroll metadata provider.
 */
final class ScrollMetadata implements ProvidesScrollMetadataInterface
{
    public function __construct(
        private string $pageName = 'page',
        private int|string|null $previousPage = null,
        private int|string|null $nextPage = 2,
        private int|string|null $currentPage = 1,
    ) {
    }

    public function getPageName(): string
    {
        return $this->pageName;
    }

    public function getPreviousPage(): int|string|null
    {
        return $this->previousPage;
    }

    public function getNextPage(): int|string|null
    {
        return $this->nextPage;
    }

    public function getCurrentPage(): int|string|null
    {
        return $this->currentPage;
    }
}
