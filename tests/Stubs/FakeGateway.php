<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\Stubs;

use EvoMark\InertiaSymfonyBundle\Interfaces\SsrGatewayInterface;
use EvoMark\InertiaSymfonyBundle\Ssr\Response;

final class FakeGateway implements SsrGatewayInterface
{
    /**
     * Tracks the number of times the 'dispatch' method was called.
     */
    public int $times = 0;

    public function __construct(private bool $enabled = false)
    {
    }

    public function dispatch(array $page): ?Response
    {
        ++$this->times;

        if (!$this->enabled) {
            return null;
        }

        return new Response(
            "<meta charset=\"UTF-8\" />\n<title inertia>Example SSR Title</title>\n",
            '<p>This is some example SSR content</p>',
        );
    }
}
