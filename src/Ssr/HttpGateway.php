<?php

namespace EvoMark\InertiaSymfonyBundle\Ssr;

use EvoMark\InertiaSymfonyBundle\Config\InertiaConfig;
use EvoMark\InertiaSymfonyBundle\Enums\SsrErrorType;
use EvoMark\InertiaSymfonyBundle\Events\SsrRenderFailed;
use EvoMark\InertiaSymfonyBundle\Interfaces\ConfiguresSsrRequestsInterface;
use EvoMark\InertiaSymfonyBundle\Interfaces\DisablesSsrInterface;
use EvoMark\InertiaSymfonyBundle\Interfaces\ExcludesSsrPathsInterface;
use EvoMark\InertiaSymfonyBundle\Interfaces\SsrGatewayInterface;
use EvoMark\InertiaSymfonyBundle\Interfaces\SsrHasHealthCheckInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Renders pages on the SSR server over HTTP.
 *
 * Excluded paths and the request configurator are app-wide: register them once.
 * The disable() condition is reset between requests.
 */
class HttpGateway implements SsrGatewayInterface, ExcludesSsrPathsInterface, DisablesSsrInterface, ConfiguresSsrRequestsInterface, SsrHasHealthCheckInterface, ResetInterface
{
    /**
     * Paths (or full URLs) that are never server-side rendered; `*` matches anything.
     *
     * @var list<string>
     */
    private array $excludedPaths = [];

    /**
     * When set, decides whether SSR is disabled instead of the `ssr.enabled` config.
     */
    private \Closure|bool|null $disabled = null;

    /**
     * Adjusts the HttpClient options of each SSR request.
     *
     * @var (\Closure(array<string, mixed>): (array<string, mixed>|null))|null
     */
    private ?\Closure $requestConfigurator = null;

    public function __construct(
        private HttpClientInterface $httpClient,
        private InertiaConfig $config,
        private RequestStack $requestStack,
        private EventDispatcherInterface $eventDispatcher,
        private BundleDetector $bundleDetector,
        private ?DevServerDetector $devServerDetector = null,
    ) {
    }

    public function dispatch(array $page): ?Response
    {
        if (!$this->ssrIsEnabled() || $this->isExcluded($this->requestStack->getCurrentRequest())) {
            return null;
        }

        // While the Vite dev server runs it renders pages itself, without a built bundle.
        $hotUrl = $this->getHotUrl('/__inertia_ssr');

        // Without a built bundle there is no SSR server to call, so skip the request.
        if (null === $hotUrl && $this->config->shouldEnsureSsrBundleExists() && !$this->bundleExists()) {
            return null;
        }

        try {
            $content = $this->httpClient->request('POST', $hotUrl ?? $this->getProductionUrl('/render'), $this->requestOptions([
                ...(null !== $this->config->getSsrTimeout() ? ['timeout' => $this->config->getSsrTimeout()] : []),
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'body' => json_encode($page, \JSON_THROW_ON_ERROR),
            ]))->toArray();
        } catch (TransportExceptionInterface $e) {
            $this->handleFailure(new SsrRenderFailed($page, $e->getMessage(), SsrErrorType::Connection), $e);

            return null;
        } catch (HttpExceptionInterface $e) {
            $this->handleFailure($this->eventFromResponse($page, $e), $e);

            return null;
        } catch (\Exception) {
            return null;
        }

        return new Response(
            implode("\n", $content['head'] ?? []),
            $content['body'] ?? '',
        );
    }

    /**
     * Whether the SSR server answers its health endpoint with a 2xx response.
     */
    public function isHealthy(): bool
    {
        try {
            $status = $this->httpClient->request('GET', $this->getProductionUrl('/health'), $this->requestOptions([]))->getStatusCode();
        } catch (\Exception) {
            return false;
        }

        return $status >= 200 && $status < 300;
    }

    /**
     * Exclude the given paths from server-side rendering. Each is matched against
     * the request path (without the leading slash) and the full URL.
     *
     * @param array<int, string>|string $paths
     */
    public function except(array|string $paths): void
    {
        $this->excludedPaths = array_values(array_unique([...$this->excludedPaths, ...(array) $paths]));
    }

    /**
     * @return list<string>
     */
    public function getExcludedPaths(): array
    {
        return $this->excludedPaths;
    }

    /**
     * Disable SSR for the current request, or (with false) enable it whatever the
     * `ssr.enabled` config says. A closure is evaluated when a page is rendered.
     */
    public function disable(\Closure|bool $condition): void
    {
        $this->disabled = $condition;
    }

    /**
     * Adjust the HttpClient options sent to the SSR server: return the new options,
     * or take them by reference and modify them. Null removes the callback.
     */
    public function configureRequestUsing(?\Closure $callback = null): void
    {
        $this->requestConfigurator = $callback;
    }

    public function reset(): void
    {
        $this->disabled = null;
    }

    /**
     * Dispatch the failure event, then throw when `ssr.throw_on_error` is enabled.
     *
     * @throws SsrException
     */
    protected function handleFailure(SsrRenderFailed $event, ?\Throwable $previous = null): void
    {
        $this->eventDispatcher->dispatch($event);

        if ($this->config->shouldThrowOnSsrError()) {
            throw SsrException::fromEvent($event, $previous);
        }
    }

    /**
     * Build the failure event from an error response of the SSR server.
     *
     * @param array<string, mixed> $page
     */
    protected function eventFromResponse(array $page, HttpExceptionInterface $exception): SsrRenderFailed
    {
        try {
            $body = $exception->getResponse()->toArray(false);
        } catch (\Exception) {
            $body = [];
        }

        return new SsrRenderFailed(
            $page,
            $body['error'] ?? 'Unknown SSR error',
            SsrErrorType::fromString($body['type'] ?? null),
            $body['hint'] ?? null,
            $body['browserApi'] ?? null,
            $body['stack'] ?? null,
            $body['sourceLocation'] ?? null,
        );
    }

    /**
     * Whether the built SSR bundle can be found.
     */
    protected function bundleExists(): bool
    {
        return null !== $this->bundleDetector->detect();
    }

    protected function ssrIsEnabled(): bool
    {
        if (null !== $this->disabled) {
            return !($this->disabled instanceof \Closure ? ($this->disabled)() : $this->disabled);
        }

        return $this->config->isSsr();
    }

    protected function isExcluded(?Request $request): bool
    {
        if (null === $request || [] === $this->excludedPaths) {
            return false;
        }

        $path = rawurldecode(ltrim($request->getPathInfo(), '/'));
        $url = $request->getUri();

        foreach ($this->excludedPaths as $pattern) {
            $regex = '#^'.str_replace('\*', '.*', preg_quote($pattern, '#')).'\z#u';

            if (preg_match($regex, $path) || preg_match($regex, $url)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The Vite dev server URL for the given endpoint while it is running: the
     * `ssr.hot_url` config if set, otherwise the origin Reprise reports. Null when
     * no dev server is running.
     */
    public function getHotUrl(string $path): ?string
    {
        $origin = $this->devServerDetector?->getOrigin();

        if (null === $origin) {
            return null;
        }

        return rtrim($this->config->getSsrHotUrl() ?? $origin, '/').$path;
    }

    /**
     * The SSR server URL for the given endpoint.
     */
    public function getProductionUrl(string $path): string
    {
        return rtrim($this->config->getSsrUrl(), '/').$path;
    }

    /**
     * Pass the request options through the configurator, if one is set.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    protected function requestOptions(array $options): array
    {
        if (null !== $this->requestConfigurator) {
            $result = ($this->requestConfigurator)($options);

            if (\is_array($result)) {
                $options = $result;
            }
        }

        return $options;
    }
}
