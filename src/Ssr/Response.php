<?php

namespace EvoMark\InertiaSymfonyBundle\Ssr;

class Response
{
    public function __construct(public string $head, public string $body)
    {
    }
}
