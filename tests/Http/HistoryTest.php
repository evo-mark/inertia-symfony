<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\Http;

use EvoMark\InertiaSymfonyBundle\Tests\Fixtures\TestKernel;
use EvoMark\InertiaSymfonyBundle\Tests\TestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * History encryption, clearing and fragment preservation. The bundle's config is
 * fixed at boot, so the "globally" tests configure it up front.
 */
final class HistoryTest extends TestCase
{
    /**
     * Two-step flow on `/example-url` (?step=first, then ?step=second) through a
     * browser that keeps the session cookie between requests.
     *
     * @param \Closure(): mixed $first
     * @param \Closure(): mixed $second
     */
    private function browser(\Closure $first, \Closure $second): KernelBrowser
    {
        TestKernel::$controller = static fn (Request $request) => 'first' === $request->query->get('step') ? $first() : $second();

        return new KernelBrowser(self::bootKernel());
    }

    public function testTheHistoryIsNotEncryptedOrClearedByDefault(): void
    {
        $response = $this->makeMockRequest(fn () => $this->inertia()->render('User/Edit'), ['X-Inertia' => 'true']);

        $this->assertTrue($response->isSuccessful());
        $page = $this->getPage($response);
        $this->assertNotTrue($page['encryptHistory'] ?? null);
        $this->assertNotTrue($page['clearHistory'] ?? null);
    }

    public function testTheHistoryCanBeEncrypted(): void
    {
        $response = $this->makeMockRequest(function () {
            $this->inertia()->encryptHistory();

            return $this->inertia()->render('User/Edit');
        }, ['X-Inertia' => 'true']);

        $this->assertTrue($response->isSuccessful());
        $this->assertArrayContains([
            'component' => 'User/Edit',
            'encryptHistory' => true,
        ], $this->getPage($response));
    }

    public function testTheHistoryCanBeEncryptedGlobally(): void
    {
        $this->configure(['history' => ['encrypt' => true]]);

        $response = $this->makeMockRequest(fn () => $this->inertia()->render('User/Edit'), ['X-Inertia' => 'true']);

        $this->assertTrue($response->isSuccessful());
        $this->assertArrayContains([
            'component' => 'User/Edit',
            'encryptHistory' => true,
        ], $this->getPage($response));
    }

    public function testTheHistoryCanBeEncryptedGloballyAndOverridden(): void
    {
        $this->configure(['history' => ['encrypt' => true]]);

        $response = $this->makeMockRequest(function () {
            $this->inertia()->encryptHistory(false);

            return $this->inertia()->render('User/Edit');
        }, ['X-Inertia' => 'true']);

        $this->assertTrue($response->isSuccessful());
        $this->assertNotTrue($this->getPage($response)['encryptHistory'] ?? null);
    }

    public function testTheHistoryCanBeCleared(): void
    {
        $response = $this->makeMockRequest(function () {
            $this->inertia()->clearHistory();

            return $this->inertia()->render('User/Edit');
        }, ['X-Inertia' => 'true']);

        $this->assertTrue($response->isSuccessful());
        $this->assertArrayContains([
            'component' => 'User/Edit',
            'clearHistory' => true,
        ], $this->getPage($response));
    }

    public function testTheHistoryCanBeClearedWhenRedirecting(): void
    {
        $client = $this->browser(
            function () {
                $this->inertia()->clearHistory();

                return new RedirectResponse('/example-url?step=second');
            },
            fn () => $this->inertia()->render('User/Edit'),
        );
        $client->followRedirects();

        $client->request('GET', '/example-url?step=first', server: ['HTTP_X_INERTIA' => 'true']);

        $response = $client->getResponse();
        $this->assertTrue($response->isSuccessful());
        $this->assertArrayContains([
            'component' => 'User/Edit',
            'url' => '/example-url?step=second',
            'clearHistory' => true,
        ], $this->getPage($response));
    }

    public function testTheFragmentIsNotPreservedByDefault(): void
    {
        $response = $this->makeMockRequest(fn () => $this->inertia()->render('User/Edit'), ['X-Inertia' => 'true']);

        $this->assertTrue($response->isSuccessful());
        $this->assertNotTrue($this->getPage($response)['preserveFragment'] ?? null);
    }

    public function testTheFragmentCanBePreservedViaTheInertiaService(): void
    {
        $client = $this->browser(
            function () {
                $this->inertia()->preserveFragment();

                return new RedirectResponse('/example-url?step=second');
            },
            fn () => $this->inertia()->render('User/Edit'),
        );

        $client->request('GET', '/example-url?step=first');
        $client->request('GET', '/example-url?step=second', server: ['HTTP_X_INERTIA' => 'true']);

        $response = $client->getResponse();
        $this->assertTrue($response->isSuccessful());
        $this->assertArrayContains([
            'preserveFragment' => true,
        ], $this->getPage($response));
    }
}
