<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Exception;

use EvoMark\InertiaSymfonyBundle\Enums\SsrErrorType;
use EvoMark\InertiaSymfonyBundle\Events\SsrRenderFailed;

/**
 * Thrown when server-side rendering fails and `ssr.throw_on_error` is enabled.
 */
class SsrException extends \RuntimeException
{
    /**
     * @param SsrRenderFailed $event    the failure the SSR server reported
     * @param string          $message  the exception message
     * @param \Throwable|null $previous the error that caused the failure, if any
     */
    public function __construct(
        public readonly SsrRenderFailed $event,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Create an exception from a failed render event.
     */
    public static function fromEvent(SsrRenderFailed $event, ?\Throwable $previous = null): self
    {
        $message = \sprintf('SSR render failed for component [%s]: %s', $event->component(), $event->error);

        if (null !== $event->sourceLocation) {
            $message .= ' at '.$event->sourceLocation;
        }

        return new self($event, $message, $previous);
    }

    /**
     * The name of the component that failed to render.
     */
    public function component(): string
    {
        return $this->event->component();
    }

    /**
     * The kind of failure.
     */
    public function type(): SsrErrorType
    {
        return $this->event->type;
    }

    /**
     * A hint for fixing the failure, when the SSR server gave one.
     */
    public function hint(): ?string
    {
        return $this->event->hint;
    }

    /**
     * The browser API the page used during SSR, for browser-API failures.
     */
    public function browserApi(): ?string
    {
        return $this->event->browserApi;
    }

    /**
     * Where in the page's source the failure happened, when known.
     */
    public function sourceLocation(): ?string
    {
        return $this->event->sourceLocation;
    }
}
