<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Twig;

use EvoMark\InertiaSymfonyBundle\Config\InertiaConfig;
use EvoMark\InertiaSymfonyBundle\Ssr\GatewayInterface;
use EvoMark\InertiaSymfonyBundle\Ssr\Response;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFunction;

class InertiaExtension extends AbstractExtension
{
    public function __construct(
        private InertiaConfig $config,
        private GatewayInterface $gateway,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('inertiaHead', [$this, 'inertiaHeadFunction']),
            new TwigFunction('inertiaApp', [$this, 'inertiaAppFunction']),
        ];
    }

    public function inertiaHeadFunction($page)
    {
        if ($this->config->isSsr()) {
            $response = $this->gateway->dispatch($page);
            if ($response instanceof Response) {
                return new Markup($response->head, 'UTF-8');
            }
        }

        return new Markup('', 'UTF-8');
    }

    public function inertiaAppFunction($page, string $id = 'app')
    {
        if ($this->config->isSsr()) {
            $response = $this->gateway->dispatch($page);
            if ($response instanceof Response) {
                return new Markup($response->body, 'UTF-8');
            }
        }

        $formattedId = htmlspecialchars($id);

        return new Markup(sprintf(
            '<script data-page="%s" type="application/json">%s</script><div id="%s"></div>',
            $formattedId,
            json_encode($page, JSON_HEX_TAG),
            $formattedId
        ), 'UTF-8');
    }
}
