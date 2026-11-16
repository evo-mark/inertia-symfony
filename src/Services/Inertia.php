<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Services;

use EvoMark\InertiaSymfonyBundle\Config\InertiaConfig;
use EvoMark\InertiaSymfonyBundle\Services\InertiaInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Twig\Environment;
use Symfony\Component\HttpFoundation\JsonResponse;

class Inertia implements InertiaInterface
{
    protected string $rootView;
    protected array $sharedViewData = [];
    protected ?string $version = null;

    public function __construct(
        InertiaConfig $config,
        private Environment $engine,
        private RequestStack $requestStack,
        private ?SerializerInterface $serializer = null,
    ) {
        $this->rootView = $config->getRootView();
    }

    public function setRootView(string $rootView): void
    {
        $this->rootView = $rootView;
    }

    public function getRootView(): string
    {
        return $this->rootView;
    }

    public function viewData(string $key, mixed $value = null): void
    {
        $this->sharedViewData[$key] = $value;
    }

    public function getViewData(?string $key = null): ?array
    {
        if ($key) {
            return $this->sharedViewData[$key] ?? null;
        }

        return $this->sharedViewData;
    }

    public function render(string $component, $props = [], $viewData = [], $context = [], $url = null): Response
    {

        $viewData = array_merge($this->sharedViewData, $viewData);


        $version = $this->version;
        $page = $this->serialize(compact('component', 'props', 'url', 'version'), $context);

        $response = new Response();
        $response->setContent($this->engine->render($this->getRootView(), [
            'page' => $page,
            'viewData' => []
        ]));

        return $response;
    }

    /**
     * Serializes the given objects with the given context if the Symfony Serializer is available. If not, uses `json_encode`.
     *
     * @see https://github.com/OWASP/CheatSheetSeries/blob/master/cheatsheets/AJAX_Security_Cheat_Sheet.md#always-return-json-with-an-object-on-the-outside
     *
     * @param array $context
     *
     * @return array @return array returns a decoded array of the previously JSON-encoded data, so it can safely be given to {@see JsonResponse}
     */
    private function serialize(array $page, $context = []): array
    {
        if (null !== $this->serializer) {
            $json = $this->serializer->serialize($page, 'json', array_merge([
                'json_encode_options' => JsonResponse::DEFAULT_ENCODING_OPTIONS,
                AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => function () {
                    return null;
                },
                AbstractObjectNormalizer::PRESERVE_EMPTY_OBJECTS => true,
                AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
            ], $context));
        } else {
            $json = json_encode($page);
        }

        return (array) json_decode($json, false);
    }

    private static function array_only($array, $keys)
    {
        return array_intersect_key($array, array_flip((array) $keys));
    }
}
