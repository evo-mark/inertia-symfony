<?php

namespace EvoMark\InertiaSymfonyBundle\Ssr;

/**
 * A page rendered by the SSR server.
 */
class Response
{
    /**
     * @param string $head the head tags, one per line
     * @param string $body the rendered app
     */
    public function __construct(public string $head, public string $body)
    {
    }
}
