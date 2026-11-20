<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Http;

use EvoMark\InertiaSymfonyBundle\Interfaces\InertiaInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Handed to the callback registered with Inertia::handleExceptionsUsing() for a
 * thrown exception. Call render() to answer with an Inertia error page; leave it
 * unrendered (or return null) to fall back to Symfony's error page.
 */
class ExceptionResponse
{
    protected ?string $component = null;

    /**
     * @var array<array-key, mixed>
     */
    protected array $props = [];

    protected ?string $rootView = null;

    /**
     * @param \Throwable       $exception the exception being handled
     * @param Request          $request   the request that threw it
     * @param InertiaInterface $inertia   renders the error page
     */
    public function __construct(
        public readonly \Throwable $exception,
        public readonly Request $request,
        protected readonly InertiaInterface $inertia,
    ) {
    }

    /**
     * Render the given Inertia page for this exception.
     *
     * @param array<array-key, mixed> $props
     */
    public function render(string $component, array $props = []): static
    {
        $this->component = $component;
        $this->props = $props;

        return $this;
    }

    /**
     * Render the error page with a different root view.
     */
    public function rootView(string $rootView): static
    {
        $this->rootView = $rootView;

        return $this;
    }

    /**
     * The HTTP status code for the exception: an HTTP exception's own status, otherwise 500.
     */
    public function statusCode(): int
    {
        return $this->exception instanceof HttpExceptionInterface
            ? $this->exception->getStatusCode()
            : SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR;
    }

    /**
     * Whether render() was called, so this replaces Symfony's error page.
     */
    public function isRendered(): bool
    {
        return null !== $this->component;
    }

    /**
     * Build the Inertia error page, with the exception's status code and headers.
     *
     * @throws \LogicException when render() was not called
     */
    public function toResponse(): SymfonyResponse
    {
        if (null === $this->component) {
            throw new \LogicException('Call render() before building an exception response.');
        }

        $page = $this->inertia->render($this->component, $this->props);

        if (null !== $this->rootView) {
            $page->rootView($this->rootView);
        }

        $response = $page->toResponse($this->request)->setStatusCode($this->statusCode());

        if ($this->exception instanceof HttpExceptionInterface) {
            $response->headers->add($this->exception->getHeaders());
        }

        return $response;
    }
}
