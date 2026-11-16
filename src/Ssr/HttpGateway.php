<?php

namespace EvoMark\InertiaSymfonyBundle\Ssr;

use EvoMark\InertiaSymfonyBundle\Config\InertiaConfig;
use EvoMark\InertiaSymfonyBundle\Ssr\GatewayInterface;
use EvoMark\InertiaSymfonyBundle\Ssr\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HttpGateway implements GatewayInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private InertiaConfig $config,
    ) {
    }

    public function dispatch(array $page): ?Response
    {
        try {
            $response = $this->httpClient->request(
                method: 'POST',
                url: $this->config->getSsrUrl(),
                options: [
                    ...(null !== $this->config->getSsrTimeout() ? ['timeout' => $this->config->getSsrTimeout()] : []),
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                    'body' => json_encode($page)
                ]
            );
            $content = $response->toArray();
        } catch (\Exception) {
            return null;
        }

        if (is_null($response)) {
            return null;
        }

        return new Response(
            head: implode("\n", $content['head']),
            body: $content['body']
        );
    }
}
