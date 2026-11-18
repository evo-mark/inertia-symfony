<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\Controller;

use EvoMark\InertiaSymfonyBundle\Tests\TestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pages rendered straight from routes (see TestKernel::configureRoutes()).
 */
final class InertiaControllerTest extends TestCase
{
    /**
     * @param array<string, string> $server
     */
    private function get(string $uri, array $server = []): Response
    {
        // Reuse a kernel a test already booted, so its shared props survive.
        $client = new KernelBrowser(self::$booted ? self::$kernel : self::bootKernel());
        $client->request('GET', $uri, server: $server);

        return $client->getResponse();
    }

    public function testItRendersTheComponentWithItsPropsOnAFullPageLoad(): void
    {
        $response = $this->get('/inertia-page');

        $this->assertIsInertiaView($response);
        $this->assertArrayContains([
            'component' => 'About',
            'props' => ['team' => 'Platform', 'members' => ['Ada', 'Grace']],
            'url' => '/inertia-page',
        ], $this->getPage($response));
    }

    public function testItRendersJsonForAnInertiaVisit(): void
    {
        $response = $this->get('/inertia-page', ['HTTP_X_INERTIA' => 'true']);

        $this->assertIsInertiaJson($response);
        $this->assertArrayContains(['component' => 'About', 'props' => ['team' => 'Platform']], $this->getPage($response));
    }

    public function testSharedPropsAreIncluded(): void
    {
        $this->inertia()->share('app_name', 'My App');

        $response = $this->get('/inertia-page', ['HTTP_X_INERTIA' => 'true']);

        $this->assertArrayContains(['props' => ['app_name' => 'My App']], $this->getPage($response));
    }

    public function testPartialReloadsWork(): void
    {
        $response = $this->get('/inertia-page', [
            'HTTP_X_INERTIA' => 'true',
            'HTTP_X_INERTIA_PARTIAL_COMPONENT' => 'About',
            'HTTP_X_INERTIA_PARTIAL_DATA' => 'team',
        ]);

        $props = $this->getPage($response)['props'];
        $this->assertSame('Platform', $props['team']);
        $this->assertArrayNotHasKey('members', $props);
    }

    public function testTheResponseCanBeCachedPublicly(): void
    {
        $response = $this->get('/inertia-page/cached');

        $this->assertSame('600', (string) $response->headers->getCacheControlDirective('max-age'));
        $this->assertSame('3600', (string) $response->headers->getCacheControlDirective('s-maxage'));
        $this->assertTrue($response->headers->hasCacheControlDirective('public'));
    }

    public function testTheResponseCanBeCachedPrivately(): void
    {
        $response = $this->get('/inertia-page/private');

        $this->assertSame('600', (string) $response->headers->getCacheControlDirective('max-age'));
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertFalse($response->headers->hasCacheControlDirective('public'));
    }

    public function testCachedResponsesVaryOnTheInertiaHeader(): void
    {
        $this->assertContains('X-Inertia', $this->get('/inertia-page/cached')->getVary());
    }

    public function testTheStatusCodeHeadersAndViewDataCanBeSet(): void
    {
        $response = $this->get('/inertia-page/gone');

        $this->assertSame(410, $response->getStatusCode());
        $this->assertSame('noindex', $response->headers->get('X-Robots-Tag'));
        $this->assertStringContainsString('<title>Gone</title>', (string) $response->getContent());
        $this->assertArrayContains(['component' => 'Gone'], $this->getPage($response));
    }

    public function testTheStatusCodeAppliesToInertiaVisitsToo(): void
    {
        $response = $this->get('/inertia-page/gone', ['HTTP_X_INERTIA' => 'true']);

        $this->assertSame(410, $response->getStatusCode());
        $this->assertIsInertiaJson($response);
    }
}
