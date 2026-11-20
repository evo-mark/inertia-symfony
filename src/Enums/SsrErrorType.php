<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Enums;

/**
 * The kind of failure the SSR server reported for a render.
 */
enum SsrErrorType: string
{
    case BrowserApi = 'browser-api';
    case ComponentResolution = 'component-resolution';
    case Render = 'render';
    case Connection = 'connection';
    case Unknown = 'unknown';

    /**
     * Create an instance from a string value, defaulting to Unknown.
     */
    public static function fromString(?string $value): self
    {
        if (null === $value) {
            return self::Unknown;
        }

        return self::tryFrom($value) ?? self::Unknown;
    }
}
