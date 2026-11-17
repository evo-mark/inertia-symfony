<?php

namespace EvoMark\InertiaSymfonyBundle\Ssr;

use EvoMark\InertiaSymfonyBundle\Interfaces\SsrGatewayInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Holds the server-side rendered page for the current request, so the gateway
 * is dispatched once even though both inertiaHead() and inertiaApp() need it.
 */
class SsrState implements ResetInterface
{
    /**
     * The page data for the current request.
     *
     * @var array<string, mixed>
     */
    public array $page = [];

    /**
     * The SSR response for the current request.
     */
    public ?Response $response = null;

    /**
     * Whether the SSR gateway has been dispatched.
     */
    protected bool $dispatched = false;

    public function __construct(private SsrGatewayInterface $gateway)
    {
    }

    /**
     * Set the page data for the current request. A different page discards the
     * cached response, so it is rendered again.
     *
     * @param array<string, mixed> $page
     */
    public function setPage(array $page): static
    {
        if ($page !== $this->page) {
            $this->dispatched = false;
            $this->response = null;
        }

        $this->page = $page;

        return $this;
    }

    /**
     * Dispatch the page to the SSR gateway, or return the cached response.
     */
    public function dispatch(): ?Response
    {
        if (!$this->dispatched) {
            $this->dispatched = true;
            $this->response = $this->gateway->dispatch($this->page);
        }

        return $this->response;
    }

    /**
     * Forget the page and response between requests, so a long-running worker
     * never serves one request's rendered page to the next.
     */
    public function reset(): void
    {
        $this->page = [];
        $this->response = null;
        $this->dispatched = false;
    }
}
