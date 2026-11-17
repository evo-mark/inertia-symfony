<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\Events;

use EvoMark\InertiaSymfonyBundle\Ssr\HttpGateway;
use EvoMark\InertiaSymfonyBundle\Enums\SsrErrorType;
use EvoMark\InertiaSymfonyBundle\Ssr\SsrException;
use EvoMark\InertiaSymfonyBundle\Events\SsrRenderFailed;
use EvoMark\InertiaSymfonyBundle\Tests\Fixtures\TestKernel;
use EvoMark\InertiaSymfonyBundle\Tests\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * The HTTP SSR gateway. Requests go to a {@see MockHttpClient}, events are
 * captured by a listener on the event dispatcher, and request configurators
 * receive the Symfony HttpClient options array (returned, or modified by reference).
 */
final class HttpGatewayTest extends TestCase
{
    private const BUNDLE = __DIR__.'/../Stubs/ssr-bundle.js';

    /**
     * @var list<array{method: string, url: string, options: array<string, mixed>}>
     */
    private array $sent = [];

    /**
     * Configure SSR, then fake the HTTP client with $responses (keyed by URL, or a
     * single response/list for every request) and return the gateway.
     *
     * @param array<string, mixed>                                             $ssr
     * @param array<string, MockResponse|list<MockResponse>>|MockResponse|null $responses
     */
    private function gateway(array $ssr = [], array|MockResponse|null $responses = null, ?MockHttpClient $client = null): HttpGateway
    {
        if ([] !== $ssr) {
            $this->configure(['ssr' => $ssr]);
        }

        $sequences = [];
        $client ??= $this->fakeHttpClient(function (string $method, string $url, array $options) use ($responses, &$sequences) {
            $this->sent[] = ['method' => $method, 'url' => $url, 'options' => $options];

            $response = \is_array($responses) ? ($responses[$url] ?? null) : $responses;

            if (\is_array($response)) {
                $sequences[$url] ??= 0;
                $response = $response[$sequences[$url]++] ?? null;
            }

            if (null === $response) {
                throw new \LogicException("Unexpected stray request to [{$url}].");
            }

            return $response;
        });

        return static::getContainer()->get(HttpGateway::class);
    }

    /**
     * The SSR server URL for $path, computed from config rather than the gateway so
     * the gateway is not instantiated before the HTTP client is faked.
     */
    private function productionUrl(string $path = '/render'): string
    {
        return rtrim(TestKernel::$inertiaConfig['ssr']['url'] ?? 'http://127.0.0.1:13714', '/').$path;
    }

    private static function json(array $body, int $status = 200): MockResponse
    {
        return new MockResponse(json_encode($body), ['http_code' => $status]);
    }

    private static function failedConnection(): MockResponse
    {
        return new MockResponse('', ['error' => 'Connection refused']);
    }

    /**
     * @return list<SsrRenderFailed>
     */
    private function captureSsrRenderFailedEvents(): \ArrayObject
    {
        $events = new \ArrayObject();
        static::getContainer()->get('event_dispatcher')->addListener(SsrRenderFailed::class, static function (SsrRenderFailed $event) use ($events) {
            $events[] = $event;
        });

        return $events;
    }

    /**
     * @param array<string, mixed> $headersOptions the options sent with a request
     */
    private static function hasHeader(array $headersOptions, string $name, ?string $value = null): bool
    {
        foreach ($headersOptions['headers'] ?? [] as $header) {
            [$headerName, $headerValue] = array_map('trim', explode(':', $header, 2)) + [1 => ''];

            if (0 === strcasecmp($headerName, $name) && (null === $value || $headerValue === $value)) {
                return true;
            }
        }

        return false;
    }

    public function testItReturnsNullWhenSsrIsDisabled(): void
    {
        $gateway = $this->gateway(['enabled' => false, 'bundle' => self::BUNDLE]);

        $this->assertNull($gateway->dispatch(['page' => self::EXAMPLE_PAGE_OBJECT]));
    }

    public function testItReturnsNullWhenNoBundleFileIsDetected(): void
    {
        $gateway = $this->gateway(['enabled' => true, 'bundle' => null]);

        $this->assertNull($gateway->dispatch(['page' => self::EXAMPLE_PAGE_OBJECT]));
    }

    public function testItUsesTheConfiguredHttpUrlWhenTheBundleFileIsDetected(): void
    {
        $this->configure(['ssr' => ['enabled' => true, 'bundle' => self::BUNDLE]]);
        $gateway = $this->gateway(responses: [
            $this->productionUrl() => self::json([
                'head' => ['<title>SSR Test</title>', '<style></style>'],
                'body' => '<div id="app">SSR Response</div>',
            ]),
        ]);

        $this->assertNotNull(
            $response = $gateway->dispatch(['page' => self::EXAMPLE_PAGE_OBJECT]),
        );

        $this->assertEquals("<title>SSR Test</title>\n<style></style>", $response->head);
        $this->assertEquals('<div id="app">SSR Response</div>', $response->body);
    }

    public function testItUsesTheConfiguredHttpUrlWhenBundleFileDetectionIsDisabled(): void
    {
        $this->configure(['ssr' => ['enabled' => true, 'ensure_bundle_exists' => false, 'bundle' => null]]);
        $gateway = $this->gateway(responses: [
            $this->productionUrl() => self::json([
                'head' => ['<title>SSR Test</title>', '<style></style>'],
                'body' => '<div id="app">SSR Response</div>',
            ]),
        ]);

        $this->assertNotNull(
            $response = $gateway->dispatch(['page' => self::EXAMPLE_PAGE_OBJECT]),
        );

        $this->assertEquals("<title>SSR Test</title>\n<style></style>", $response->head);
        $this->assertEquals('<div id="app">SSR Response</div>', $response->body);
    }

    public function testItReturnsNullWhenTheHttpRequestFails(): void
    {
        $this->configure(['ssr' => ['enabled' => true, 'bundle' => self::BUNDLE]]);
        $gateway = $this->gateway(responses: [
            $this->productionUrl() => new MockResponse('', ['http_code' => 500]),
        ]);

        $this->assertNull($gateway->dispatch(['page' => self::EXAMPLE_PAGE_OBJECT]));
    }

    public function testItReturnsNullWhenInvalidJsonIsReturned(): void
    {
        $this->configure(['ssr' => ['enabled' => true, 'bundle' => self::BUNDLE]]);
        $gateway = $this->gateway(responses: [
            $this->productionUrl() => new MockResponse('invalid json'),
        ]);

        $this->assertNull($gateway->dispatch(['page' => self::EXAMPLE_PAGE_OBJECT]));
    }

    public function testHealthCheckTheSsrServer(): void
    {
        $healthUrl = $this->productionUrl('/health');

        $gateway = $this->gateway(responses: [
            $healthUrl => [
                new MockResponse('', ['http_code' => 200]),
                new MockResponse('', ['http_code' => 500]),
                self::failedConnection(),
            ],
        ]);

        $this->assertTrue($gateway->isHealthy());
        $this->assertFalse($gateway->isHealthy());
        $this->assertFalse($gateway->isHealthy());
    }

    public function testItReturnsNullWhenPathIsExcludedFromSsr(): void
    {
        $gateway = $this->gateway(['enabled' => true, 'bundle' => self::BUNDLE]);

        $gateway->except(['admin/*']);

        $this->pushRequest($this->makeRequest('/admin/dashboard'));

        $this->assertNull($gateway->dispatch(['page' => self::EXAMPLE_PAGE_OBJECT]));
    }

    public function testItDispatchesWhenPathIsNotExcludedFromSsr(): void
    {
        $this->configure(['ssr' => ['enabled' => true, 'bundle' => self::BUNDLE]]);
        $gateway = $this->gateway(responses: [
            $this->productionUrl() => self::json([
                'head' => ['<title>SSR Test</title>'],
                'body' => '<div id="app">SSR Response</div>',
            ]),
        ]);

        $gateway->except(['admin/*']);

        $this->pushRequest($this->makeRequest('/users'));

        $this->assertNotNull($gateway->dispatch(['page' => self::EXAMPLE_PAGE_OBJECT]));
    }

    public function testItReturnsNullWhenFullUrlIsExcludedFromSsr(): void
    {
        $gateway = $this->gateway(['enabled' => true, 'bundle' => self::BUNDLE]);

        $gateway->except(['http://localhost/admin/*']);

        $this->pushRequest($this->makeRequest('/admin/dashboard'));

        $this->assertNull($gateway->dispatch(['page' => self::EXAMPLE_PAGE_OBJECT]));
    }

    public function testExceptAcceptsString(): void
    {
        $gateway = $this->gateway(['enabled' => true, 'bundle' => self::BUNDLE]);

        $gateway->except('admin/*');

        $this->pushRequest($this->makeRequest('/admin/dashboard'));

        $this->assertNull($gateway->dispatch(['page' => self::EXAMPLE_PAGE_OBJECT]));
    }

    public function testProductionUrlStripsTrailingSlash(): void
    {
        $gateway = $this->gateway(['url' => 'http://127.0.0.1:13714/']);

        $this->assertEquals('http://127.0.0.1:13714/render', $gateway->getProductionUrl('/render'));
    }

    public function testExceptCanBeCalledMultipleTimes(): void
    {
        $gateway = $this->gateway(['enabled' => true, 'bundle' => self::BUNDLE]);

        $gateway->except('admin/*');
        $gateway->except(['nova/*', 'filament/*']);

        $this->pushRequest($this->makeRequest('/nova/resources'));

        $this->assertNull($gateway->dispatch(['page' => self::EXAMPLE_PAGE_OBJECT]));
    }

    public function testItDispatchesEventWhenSsrFails(): void
    {
        $this->configure(['ssr' => ['enabled' => true, 'bundle' => self::BUNDLE]]);
        $gateway = $this->gateway(responses: [
            $this->productionUrl() => self::json([
                'error' => 'window is not defined',
                'type' => 'browser-api',
                'hint' => 'Wrap in lifecycle hook',
                'browserApi' => 'window',
            ], 500),
        ]);
        $events = $this->captureSsrRenderFailedEvents();

        $this->assertNull($gateway->dispatch(self::EXAMPLE_PAGE_OBJECT));

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertSame('window is not defined', $event->error);
        $this->assertSame(SsrErrorType::BrowserApi, $event->type);
        $this->assertSame('Wrap in lifecycle hook', $event->hint);
        $this->assertSame('window', $event->browserApi);
        $this->assertSame('Foo/Bar', $event->component());
    }

    public function testItHandlesConnectionErrorsGracefully(): void
    {
        $this->configure(['ssr' => ['enabled' => true, 'bundle' => self::BUNDLE]]);
        $gateway = $this->gateway(responses: [
            $this->productionUrl() => self::failedConnection(),
        ]);
        $events = $this->captureSsrRenderFailedEvents();

        $this->assertNull($gateway->dispatch(self::EXAMPLE_PAGE_OBJECT));

        $this->assertCount(1, $events);
        $this->assertSame(SsrErrorType::Connection, $events[0]->type);
        $this->assertStringContainsString('Connection refused', $events[0]->error);
    }

    public function testItThrowsExceptionWhenThrowOnErrorIsEnabled(): void
    {
        $this->configure(['ssr' => ['enabled' => true, 'bundle' => self::BUNDLE, 'throw_on_error' => true]]);
        $gateway = $this->gateway(responses: [
            $this->productionUrl() => self::json([
                'error' => 'window is not defined',
                'type' => 'browser-api',
                'hint' => 'Wrap in lifecycle hook',
                'browserApi' => 'window',
                'sourceLocation' => 'assets/js/Pages/Dashboard.vue:10:5',
            ], 500),
        ]);

        $this->expectException(SsrException::class);
        $this->expectExceptionMessage('SSR render failed for component [Foo/Bar]: window is not defined');

        $gateway->dispatch(self::EXAMPLE_PAGE_OBJECT);
    }

    public function testSsrExceptionContainsErrorDetails(): void
    {
        $this->configure(['ssr' => ['enabled' => true, 'bundle' => self::BUNDLE, 'throw_on_error' => true]]);
        $gateway = $this->gateway(responses: [
            $this->productionUrl() => self::json([
                'error' => 'window is not defined',
                'type' => 'browser-api',
                'hint' => 'Wrap in lifecycle hook',
                'browserApi' => 'window',
                'sourceLocation' => 'assets/js/Pages/Dashboard.vue:10:5',
            ], 500),
        ]);

        try {
            $gateway->dispatch(self::EXAMPLE_PAGE_OBJECT);
            $this->fail('Expected SsrException was not thrown');
        } catch (SsrException $e) {
            $this->assertEquals('Foo/Bar', $e->component());
            $this->assertSame(SsrErrorType::BrowserApi, $e->type());
            $this->assertEquals('Wrap in lifecycle hook', $e->hint());
            $this->assertEquals('assets/js/Pages/Dashboard.vue:10:5', $e->sourceLocation());
            $this->assertStringContainsString('at assets/js/Pages/Dashboard.vue:10:5', $e->getMessage());
        }
    }

    public function testItThrowsExceptionOnConnectionErrorWhenThrowOnErrorIsEnabled(): void
    {
        $this->configure(['ssr' => ['enabled' => true, 'bundle' => self::BUNDLE, 'throw_on_error' => true]]);
        $gateway = $this->gateway(responses: [
            $this->productionUrl() => self::failedConnection(),
        ]);

        $this->expectException(SsrException::class);
        $this->expectExceptionMessage('Connection refused');

        $gateway->dispatch(self::EXAMPLE_PAGE_OBJECT);
    }

    public function testItReturnsNullWhenDisabledWithBoolean(): void
    {
        $gateway = $this->gateway(['enabled' => true, 'bundle' => self::BUNDLE]);

        $gateway->disable(true);

        $this->assertNull($gateway->dispatch(['page' => self::EXAMPLE_PAGE_OBJECT]));
    }

    public function testItReturnsNullWhenDisabledWithClosure(): void
    {
        $gateway = $this->gateway(['enabled' => true, 'bundle' => self::BUNDLE]);

        $gateway->disable(fn () => true);

        $this->assertNull($gateway->dispatch(['page' => self::EXAMPLE_PAGE_OBJECT]));
    }

    public function testDisableWhenTakesPrecedenceOverConfig(): void
    {
        $this->configure(['ssr' => ['enabled' => true, 'bundle' => self::BUNDLE]]);
        $gateway = $this->gateway(responses: [
            $this->productionUrl() => self::json([
                'head' => ['<title>SSR Test</title>'],
                'body' => '<div id="app">SSR Response</div>',
            ]),
        ]);

        $gateway->disable(false);

        $this->assertNotNull($gateway->dispatch(['page' => self::EXAMPLE_PAGE_OBJECT]));
    }

    public function testItDoesNotThrowExceptionWhenThrowOnErrorIsDisabled(): void
    {
        $this->configure(['ssr' => ['enabled' => true, 'bundle' => self::BUNDLE, 'throw_on_error' => false]]);
        $gateway = $this->gateway(responses: [
            $this->productionUrl() => self::json([
                'error' => 'window is not defined',
                'type' => 'browser-api',
            ], 500),
        ]);

        $this->assertNull($gateway->dispatch(self::EXAMPLE_PAGE_OBJECT));
    }

    public function testItAppliesTheConfiguredTimeoutToTheSsrRequest(): void
    {
        $this->configure(['ssr' => ['enabled' => true, 'ensure_bundle_exists' => false, 'timeout' => 5]]);
        $gateway = $this->gateway(responses: self::json(['head' => [], 'body' => '']));

        $gateway->dispatch(self::EXAMPLE_PAGE_OBJECT);

        $this->assertCount(1, $this->sent);
        $this->assertEquals(5, $this->sent[0]['options']['timeout']);
    }

    public function testItDoesNotOverrideGloballyConfiguredHttpOptions(): void
    {
        $this->configure(['ssr' => ['enabled' => true, 'ensure_bundle_exists' => false]]);
        self::bootKernel();

        // Default options set on the HTTP client itself.
        $client = new MockHttpClient(function (string $method, string $url, array $options) {
            $this->sent[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return self::json(['head' => [], 'body' => '']);
        })->withOptions(['timeout' => 5]);
        static::getContainer()->set('http_client', $client);

        static::getContainer()->get(HttpGateway::class)->dispatch(self::EXAMPLE_PAGE_OBJECT);

        $this->assertCount(1, $this->sent);
        $this->assertEquals(5, $this->sent[0]['options']['timeout']);
    }

    public function testItConfiguresTheSsrRequestUsingTheGivenCallback(): void
    {
        $this->configure(['ssr' => ['enabled' => true, 'ensure_bundle_exists' => false]]);
        $gateway = $this->gateway(responses: [
            $this->productionUrl() => self::json([
                'head' => ['<title>SSR Test</title>'],
                'body' => '<div id="app">SSR Response</div>',
            ]),
        ]);

        $gateway->configureRequestUsing(fn (array $options) => array_merge_recursive($options, ['headers' => ['X-Tenant' => 'acme']]));

        $this->assertNotNull($gateway->dispatch(self::EXAMPLE_PAGE_OBJECT));

        $this->assertTrue(self::hasHeader($this->sent[0]['options'], 'X-Tenant', 'acme'));
    }

    public function testItConfiguresTheSsrRequestWhenTheCallbackReturnsNothing(): void
    {
        $this->configure(['ssr' => ['enabled' => true, 'ensure_bundle_exists' => false]]);
        $gateway = $this->gateway(responses: [
            $this->productionUrl() => self::json([
                'head' => ['<title>SSR Test</title>'],
                'body' => '<div id="app">SSR Response</div>',
            ]),
        ]);

        $gateway->configureRequestUsing(function (array &$options) {
            $options['headers']['X-Tenant'] = 'acme';
        });

        $this->assertNotNull($gateway->dispatch(self::EXAMPLE_PAGE_OBJECT));

        $this->assertTrue(self::hasHeader($this->sent[0]['options'], 'X-Tenant', 'acme'));
    }

    public function testItConfiguresTheHealthCheckRequest(): void
    {
        $healthUrl = $this->productionUrl('/health');

        $gateway = $this->gateway(responses: [
            $healthUrl => new MockResponse('', ['http_code' => 200]),
        ]);

        $gateway->configureRequestUsing(fn (array $options) => array_merge_recursive($options, ['headers' => ['X-Tenant' => 'acme']]));

        $this->assertTrue($gateway->isHealthy());

        $this->assertTrue(self::hasHeader($this->sent[0]['options'], 'X-Tenant', 'acme'));
    }

    public function testTheSsrRequestConfiguratorCanBeReset(): void
    {
        $this->configure(['ssr' => ['enabled' => true, 'ensure_bundle_exists' => false]]);
        $gateway = $this->gateway(responses: [
            $this->productionUrl() => self::json([
                'head' => ['<title>SSR Test</title>'],
                'body' => '<div id="app">SSR Response</div>',
            ]),
        ]);

        $gateway->configureRequestUsing(fn (array $options) => array_merge_recursive($options, ['headers' => ['X-Tenant' => 'acme']]));
        $gateway->configureRequestUsing();

        $this->assertNotNull($gateway->dispatch(self::EXAMPLE_PAGE_OBJECT));

        $this->assertFalse(self::hasHeader($this->sent[0]['options'], 'X-Tenant'));
    }
}
