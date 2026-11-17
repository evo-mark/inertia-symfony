<?php

namespace EvoMark\InertiaSymfonyBundle\Support;

/**
 * Session attributes the bundle reads and writes.
 */
final class SessionKey
{
    /**
     * Session key for clearing the Inertia history.
     */
    public const string CLEAR_HISTORY = 'inertia.clear_history';

    /**
     * Session key for flash data, removed once a page includes it.
     */
    public const string FLASH_DATA = 'inertia.flash_data';

    /**
     * Session key for preserving the URL fragment.
     */
    public const string PRESERVE_FRAGMENT = 'inertia.preserve_fragment';

    /**
     * Session key for validation errors, shaped
     * `array<bag, array<field, string|list<string>>>`.
     */
    public const string ERRORS = 'inertia.errors';

    /**
     * Session key for the last visited URL.
     */
    public const string PREVIOUS_URL = 'inertia.previous_url';

    /**
     * Session key for the last visited route name.
     */
    public const string PREVIOUS_ROUTE = 'inertia.previous_route';
}
