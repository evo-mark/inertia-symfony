<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Ssr;

use EvoMark\InertiaSymfonyBundle\Enums\SsrErrorType;
use EvoMark\InertiaSymfonyBundle\Events\SsrRenderFailed;

/**
 * Thrown when server-side rendering fails and `ssr.throw_on_error` is enabled.
 */
class SsrException extends \RuntimeException
{
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

    public function type(): SsrErrorType
    {
        return $this->event->type;
    }

    public function hint(): ?string
    {
        return $this->event->hint;
    }

    public function browserApi(): ?string
    {
        return $this->event->browserApi;
    }

    public function sourceLocation(): ?string
    {
        return $this->event->sourceLocation;
    }
}
