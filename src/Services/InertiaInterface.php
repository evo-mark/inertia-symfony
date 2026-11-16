<?php

namespace EvoMark\InertiaSymfonyBundle\Services;

use Symfony\Component\HttpFoundation\Response;

interface InertiaInterface
{
    public function setRootView(string $rootView): void;

    public function getRootView(): string;

    public function render(string $component, $props = [], $viewData = [], $context = [], $url = null): Response;
}
