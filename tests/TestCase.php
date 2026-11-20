<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests;

use EvoMark\InertiaSymfonyBundle\Http\Response as InertiaResponse;
use EvoMark\InertiaSymfonyBundle\Interfaces\InertiaInterface;
use EvoMark\InertiaSymfonyBundle\Tests\Fixtures\TestKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Base class for the bundle's tests: {@see self::inertia()} is the container's
 * {@see InertiaInterface} service, {@see self::configure()} sets bundle config,
 * and {@see self::makeMockRequest()} sends a real request through {@see TestKernel}.
 */
abstract class TestCase extends KernelTestCase
{
    /**
     * Example page object.
     */
    protected const EXAMPLE_PAGE_OBJECT = ['component' => 'Foo/Bar', 'props' => ['foo' => 'bar'], 'url' => '/test', 'version' => ''];

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function setUp(): void
    {
        parent::setUp();

        TestKernel::$controller = null;
        TestKernel::$inertiaConfig = [
            'root_view' => 'app.html.twig',
            'testing' => ['ensure_pages_exist' => false],
            'pages' => ['paths' => [realpath(__DIR__)]],
        ];
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        TestKernel::$controller = null;
        TestKernel::$inertiaConfig = [];
    }

    /**
     * Merge bundle configuration into the test kernel's `evo_mark_inertia_symfony` config.
     * Must be called before the kernel boots (i.e. before the first inertia()/request).
     *
     * @param array<string, mixed> $config
     */
    protected function configure(array $config): void
    {
        self::ensureKernelShutdown();

        TestKernel::$inertiaConfig = array_replace_recursive(TestKernel::$inertiaConfig, $config);
    }

    /**
     * The Inertia service from the test container.
     */
    protected function inertia(): InertiaInterface
    {
        return static::getContainer()->get(InertiaInterface::class);
    }

    /**
     * Build a request, optionally with headers, the equivalent of `Request::create()`.
     *
     * @param array<string, string> $headers
     */
    protected function makeRequest(string $uri = '/', string $method = 'GET', array $headers = []): Request
    {
        $request = Request::create($uri, $method);
        $request->headers->add($headers);

        return $request;
    }

    /**
     * Make the given request the current one, so services reading the RequestStack see it.
     */
    protected function pushRequest(Request $request): Request
    {
        if (!self::$booted) {
            self::bootKernel();
        }

        static::getContainer()->get('request_stack')->push($request);

        return $request;
    }

    /**
     * Render a component for the given request and convert it to a Symfony response,
     * the equivalent of `(new Response(...))->toResponse($request)`.
     *
     * @param array<array-key, mixed> $props
     */
    protected function renderFor(Request $request, string $component, array $props = []): Response
    {
        $this->pushRequest($request);

        return $this->inertia()->render($component, $props)->toResponse($request);
    }

    /**
     * Send a request through the kernel to `/example-url`, whose controller is
     * $view (or a closure returning it), the equivalent of `makeMockRequest()`.
     *
     * @param array<string, string> $headers
     */
    protected function makeMockRequest(mixed $view, array $headers = [], string $method = 'GET', string $uri = '/example-url'): Response
    {
        TestKernel::$controller = static fn (Request $request) => $view instanceof \Closure ? $view($request) : $view;

        if (!self::$booted) {
            self::bootKernel();
        }

        $this->lastRequest = $this->makeRequest($uri, $method, $headers);

        return self::$kernel->handle($this->lastRequest);
    }

    /**
     * The request most recently sent by {@see self::makeMockRequest()}, e.g. to
     * inspect its session.
     */
    protected ?Request $lastRequest = null;

    /**
     * Replace the HTTP client used by the SSR gateway with a mock.
     *
     * @param callable|iterable<ResponseInterface>|ResponseInterface|null $responseFactory
     */
    protected function fakeHttpClient(mixed $responseFactory = null): MockHttpClient
    {
        if (!self::$booted) {
            self::bootKernel();
        }

        $client = new MockHttpClient($responseFactory);
        static::getContainer()->set('http_client', $client);

        return $client;
    }

    /**
     * Extract the page object from a response: the JSON body of an Inertia
     * response, or the `data-page` script of a full page load.
     *
     * @return array<string, mixed>
     */
    protected function getPage(Response $response): array
    {
        $content = (string) $response->getContent();

        if ($response->headers->has('X-Inertia')) {
            return json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        }

        if (!preg_match('#<script data-page="[^"]*" type="application/json">(.*?)</script>#s', $content, $matches)) {
            $this->fail('The response does not contain an Inertia page object.');
        }

        return json_decode($matches[1], true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * Assert the response is a full page load (HTML) carrying an Inertia page.
     */
    protected function assertIsInertiaView(Response $response): void
    {
        $this->assertFalse($response->headers->has('X-Inertia'), 'Expected a full page response, got an Inertia JSON response.');
        $this->assertStringContainsString('data-page=', (string) $response->getContent());
    }

    /**
     * Assert $actual contains $expected, recursing into associative arrays.
     * Lists are compared exactly.
     *
     * @param array<array-key, mixed> $expected
     * @param array<array-key, mixed> $actual
     */
    protected function assertArrayContains(array $expected, array $actual, string $path = ''): void
    {
        foreach ($expected as $key => $value) {
            $this->assertArrayHasKey($key, $actual, "Missing key [{$path}{$key}].");

            if (\is_array($value) && !array_is_list($value) && \is_array($actual[$key])) {
                $this->assertArrayContains($value, $actual[$key], "{$path}{$key}.");
            } else {
                $this->assertSame($value, $actual[$key], "Unexpected value at [{$path}{$key}].");
            }
        }
    }

    protected function assertIsInertiaJson(Response $response): void
    {
        $this->assertSame('true', $response->headers->get('X-Inertia'));
        $this->assertJson((string) $response->getContent());
    }

    /**
     * Converts an Inertia response object to a Symfony one if needed.
     */
    protected function toSymfony(Response|InertiaResponse $response, Request $request): Response
    {
        return $response instanceof InertiaResponse ? $response->toResponse($request) : $response;
    }
}
