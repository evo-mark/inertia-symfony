<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Contracts;

/**
 * Describes the pagination state of a scroll prop.
 *
 * Symfony has no built-in paginator, so implement this on an adapter around
 * whichever paginator you use (Doctrine, Pagerfanta, KnpPaginator, ...).
 */
interface ProvidesScrollMetadata
{
    /**
     * Get the name of the query parameter that holds the page number.
     */
    public function getPageName(): string;

    /**
     * Get the previous page identifier, if any.
     */
    public function getPreviousPage(): int|string|null;

    /**
     * Get the next page identifier, if any.
     */
    public function getNextPage(): int|string|null;

    /**
     * Get the current page identifier, if any.
     */
    public function getCurrentPage(): int|string|null;
}
