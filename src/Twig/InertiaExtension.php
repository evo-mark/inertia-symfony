<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Twig;

use EvoMark\InertiaSymfonyBundle\Config\InertiaConfig;
use EvoMark\InertiaSymfonyBundle\Ssr\Response;
use EvoMark\InertiaSymfonyBundle\Ssr\SsrState;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFunction;

class InertiaExtension extends AbstractExtension
{
    public function __construct(
        private InertiaConfig $config,
        private SsrState $ssrState,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('inertiaHead', [$this, 'inertiaHeadFunction']),
            new TwigFunction('inertiaApp', [$this, 'inertiaAppFunction']),
        ];
    }

    /**
     * The server-side rendered head tags, or nothing when SSR is off or fails.
     *
     * @param array<string, mixed> $page
     */
    public function inertiaHeadFunction(array $page): Markup
    {
        return new Markup($this->render($page)?->head ?? '', 'UTF-8');
    }

    /**
     * The server-side rendered app, or the page data and the element the client
     * mounts on (`app` unless another id is given).
     *
     * @param array<string, mixed> $page
     */
    public function inertiaAppFunction(array $page, string $id = 'app'): Markup
    {
        if (null !== $response = $this->render($page)) {
            return new Markup($response->body, 'UTF-8');
        }

        $id = htmlspecialchars('' === $id ? 'app' : $id);

        return new Markup(\sprintf(
            '<script data-page="%s" type="application/json">%s</script><div id="%s"></div>',
            $id,
            json_encode($page, \JSON_HEX_TAG | \JSON_THROW_ON_ERROR),
            $id,
        ), 'UTF-8');
    }

    /**
     * Render the page on the SSR server, once per page however many functions ask.
     *
     * @param array<string, mixed> $page
     */
    private function render(array $page): ?Response
    {
        if (!$this->config->isSsr()) {
            return null;
        }

        return $this->ssrState->setPage($page)->dispatch();
    }
}
