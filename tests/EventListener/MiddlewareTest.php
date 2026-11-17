<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\EventListener;

use EvoMark\InertiaSymfonyBundle\Tests\Fixtures\TestKernel;
use EvoMark\InertiaSymfonyBundle\Tests\TestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The request/response handling done by the bundle's kernel event listeners:
 * empty responses, 303 redirects, asset versions, root views, flash data,
 * fragment redirects and the Vary header.
 */
final class MiddlewareTest extends TestCase
{
    private ?string $manifest = null;

    protected function tearDown(): void
    {
        if (null !== $this->manifest && is_file($this->manifest)) {
            unlink($this->manifest);
        }

        parent::tearDown();
    }

    /**
     * Make `/example-url` render User/Edit at the given version.
     */
    private function prepareMockEndpoint(int|string|null $version = null): void
    {
        $this->configure(['version' => $version]);

        TestKernel::$controller = fn () => $this->inertia()->render('User/Edit');
    }

    private function get(array $headers = [], string $uri = '/example-url'): Response
    {
        if (!self::$booted) {
            self::bootKernel();
        }

        $request = $this->makeRequest($uri, 'GET', $headers);
        $this->lastRequest = $request;

        return self::$kernel->handle($request);
    }

    public function testNoResponseValueByDefaultMeansAutomaticallyRedirectingBackForInertiaRequests(): void
    {
        $fooCalled = false;

        $response = $this->makeMockRequest(function () use (&$fooCalled) {
            $fooCalled = true;
        }, [
            'X-Inertia' => 'true',
            'Content-Type' => 'application/json',
            'Referer' => '/foo',
        ], 'PUT');

        $this->assertTrue($response->isRedirect('/foo'));
        $this->assertSame(303, $response->getStatusCode());
        $this->assertTrue($fooCalled);
    }

    public function testRedirectsFromQueryRequestsAreConvertedTo303(): void
    {
        $response = $this->makeMockRequest(fn () => new RedirectResponse('/foo'), [
            'X-Inertia' => 'true',
        ], 'QUERY');

        $this->assertTrue($response->isRedirect('/foo'));
        $this->assertSame(303, $response->getStatusCode());
    }

    public function testNoResponseMeansNoResponseForNonInertiaRequests(): void
    {
        $fooCalled = false;

        $response = $this->makeMockRequest(function () use (&$fooCalled) {
            $fooCalled = true;
        }, [
            'Content-Type' => 'application/json',
            'Referer' => '/foo',
        ], 'PUT');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertEmpty($response->getContent());
        $this->assertTrue($fooCalled);
    }

    public function testTheVersionIsOptional(): void
    {
        $this->prepareMockEndpoint();

        $response = $this->get(['X-Inertia' => 'true']);

        $this->assertTrue($response->isSuccessful());
        $this->assertArrayContains(['component' => 'User/Edit'], $this->getPage($response));
    }

    public function testTheVersionCanBeANumber(): void
    {
        $this->prepareMockEndpoint($version = 1597347897973);

        $response = $this->get([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) $version,
        ]);

        $this->assertTrue($response->isSuccessful());
        $this->assertArrayContains(['component' => 'User/Edit'], $this->getPage($response));
    }

    public function testTheVersionCanBeAString(): void
    {
        $this->prepareMockEndpoint($version = 'foo-version');

        $response = $this->get([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
        ]);

        $this->assertTrue($response->isSuccessful());
        $this->assertArrayContains(['component' => 'User/Edit'], $this->getPage($response));
    }

    public function testAResponseBodyOfZeroIsNotTreatedAsEmptyForInertiaRequests(): void
    {
        $response = $this->makeMockRequest(fn () => new Response('0'), [
            'X-Inertia' => 'true',
            'Referer' => '/foo',
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('0', $response->getContent());
    }

    public function testStreamedResponsesAreNotTreatedAsEmptyForInertiaRequests(): void
    {
        $response = $this->makeMockRequest(fn () => new StreamedResponse(function () {
            echo 'hello';
        }, 200, ['Content-Disposition' => 'attachment; filename=hello.txt']), [
            'X-Inertia' => 'true',
            'Referer' => '/foo',
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('hello.txt', (string) $response->headers->get('Content-Disposition'));
    }

    public function testFileDownloadsAreNotTreatedAsEmptyForInertiaRequests(): void
    {
        $response = $this->makeMockRequest(function () {
            $response = new BinaryFileResponse(\dirname(__DIR__).'/Stubs/ExamplePage.vue');
            $response->setContentDisposition('attachment', 'example.vue');

            return $response;
        }, [
            'X-Inertia' => 'true',
            'Referer' => '/foo',
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('example.vue', (string) $response->headers->get('Content-Disposition'));
    }

    public function testItWillInstructInertiaToReloadOnAVersionMismatch(): void
    {
        $this->prepareMockEndpoint('1234');

        $response = $this->get([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => '4321',
        ]);

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('http://localhost/example-url', $response->headers->get('X-Inertia-Location'));
        $this->assertSame('1234', $response->headers->get('X-Inertia-Version'));
        $this->assertEmpty($response->getContent());
    }

    public function testTheRootViewCanBeConfigured(): void
    {
        $this->configure(['root_view' => 'welcome.html.twig']);
        $this->prepareMockEndpoint();

        $response = $this->get();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('<title>welcome</title>', (string) $response->getContent());
    }

    public function testTheRootViewCanBeChangedPerRequest(): void
    {
        $response = $this->makeMockRequest(function () {
            $this->inertia()->setRootView('welcome.html.twig');

            return $this->inertia()->render('User/Edit');
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('<title>welcome</title>', (string) $response->getContent());
    }

    public function testDetermineTheVersionByAHashOfTheAssetUrl(): void
    {
        $this->configure(['asset_url' => $url = 'https://example.com/assets']);
        $this->prepareMockEndpoint();

        $response = $this->get();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(hash('xxh128', $url), $this->getPage($response)['version']);
    }

    public function testDetermineTheVersionByAHashOfTheViteManifest(): void
    {
        $this->manifest = sys_get_temp_dir().'/inertia-symfony-manifest-'.getmypid().'.json';
        file_put_contents($this->manifest, $contents = json_encode(['vite' => true]));

        $this->configure(['manifest_path' => $this->manifest]);
        $this->prepareMockEndpoint();

        $response = $this->get();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(hash('xxh128', $contents), $this->getPage($response)['version']);
    }

    public function testFlashDataIsPreservedOnNonInertiaRedirect(): void
    {
        TestKernel::$controller = function (Request $request) {
            if ('action' === $request->query->get('step')) {
                $this->inertia()->flash('message', 'Success!');

                return new RedirectResponse('/example-url?step=dashboard');
            }

            return $this->inertia()->render('Dashboard');
        };

        // A browser keeps the session cookie between the two requests.
        $client = new KernelBrowser(self::bootKernel());

        $client->request('GET', '/example-url?step=action');
        $this->assertTrue($client->getResponse()->isRedirect('/example-url?step=dashboard'));

        $client->request('GET', '/example-url?step=dashboard', server: ['HTTP_X_INERTIA' => 'true']);
        $this->assertArrayContains([
            'flash' => ['message' => 'Success!'],
        ], $this->getPage($client->getResponse()));
    }

    public function testRedirectWithHashFragmentReturns409ForInertiaRequests(): void
    {
        $response = $this->makeMockRequest(
            fn (Request $request) => new RedirectResponse($request->getSchemeAndHttpHost().'/article#section'),
            ['X-Inertia' => 'true'],
        );

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('http://localhost/article#section', $response->headers->get('X-Inertia-Redirect'));
        $this->assertEmpty($response->getContent());
    }

    public function testRedirectWithoutHashFragmentIsNotIntercepted(): void
    {
        $response = $this->makeMockRequest(fn () => new RedirectResponse('/article'), [
            'X-Inertia' => 'true',
        ], 'POST');

        $this->assertTrue($response->isRedirect('/article'));
        $this->assertSame(302, $response->getStatusCode());
    }

    public function testRedirectWithHashFragmentIsNotInterceptedForNonInertiaRequests(): void
    {
        $response = $this->makeMockRequest(
            fn (Request $request) => new RedirectResponse($request->getSchemeAndHttpHost().'/article#section'),
        );

        $this->assertTrue($response->isRedirect('http://localhost/article#section'));
        $this->assertSame(302, $response->getStatusCode());
    }

    public function testPostRedirectWithHashFragmentReturns409ForInertiaRequests(): void
    {
        $response = $this->makeMockRequest(
            fn (Request $request) => new RedirectResponse($request->getSchemeAndHttpHost().'/article#section'),
            ['X-Inertia' => 'true'],
            'POST',
        );

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('http://localhost/article#section', $response->headers->get('X-Inertia-Redirect'));
    }

    public function testRedirectWithHashFragmentIsNotInterceptedForPrefetchRequests(): void
    {
        $response = $this->makeMockRequest(
            fn (Request $request) => new RedirectResponse($request->getSchemeAndHttpHost().'/article#section'),
            ['X-Inertia' => 'true', 'Purpose' => 'prefetch'],
        );

        $this->assertTrue($response->isRedirect('http://localhost/article#section'));
    }

    public function testTheVaryHeaderIsSet(): void
    {
        $response = $this->makeMockRequest(fn () => $this->inertia()->render('User/Edit'));

        $this->assertSame(['X-Inertia'], $response->getVary());
    }

    public function testExistingVaryHeaderValuesArePreserved(): void
    {
        $response = $this->makeMockRequest(
            fn (Request $request) => $this->inertia()->render('User/Edit')->toResponse($request)->setVary('Accept-Language'),
        );

        $this->assertSame(['Accept-Language', 'X-Inertia'], $response->getVary());
    }

    public function testTheVaryHeaderIsNotDuplicated(): void
    {
        $response = $this->makeMockRequest(
            fn (Request $request) => $this->inertia()->render('User/Edit')->toResponse($request)->setVary('X-Inertia'),
        );

        $this->assertSame(['X-Inertia'], $response->getVary());
    }

    public function testEmptyVaryHeaderValuesAreDiscarded(): void
    {
        $response = $this->makeMockRequest(
            fn (Request $request) => $this->inertia()->render('User/Edit')->toResponse($request)->setVary(''),
        );

        $this->assertSame(['X-Inertia'], $response->getVary());
    }
}
