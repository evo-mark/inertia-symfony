<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\EventListener;

use EvoMark\InertiaSymfonyBundle\Tests\Fixtures\TestKernel;
use EvoMark\InertiaSymfonyBundle\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * #[EncryptHistory] and the `_encrypt_history` route default, on the routes and
 * fixture controllers in TestKernel.
 */
final class EncryptHistoryListenerTest extends TestCase
{
    /**
     * Whether the page at $uri is sent with `encryptHistory: true`.
     */
    private function encrypts(string $uri): bool
    {
        $client = new KernelBrowser(self::bootKernel());
        $client->request('GET', $uri, server: ['HTTP_X_INERTIA' => 'true']);

        $this->assertTrue($client->getResponse()->isSuccessful(), (string) $client->getResponse()->getContent());

        return true === ($this->getPage($client->getResponse())['encryptHistory'] ?? null);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function routes(): iterable
    {
        yield 'attribute on the class' => ['/encrypted/show', true];
        yield 'action opting out of its class' => ['/encrypted/public', false];
        yield 'attribute on the action' => ['/account/billing', true];
        yield 'action without the attribute' => ['/account/dashboard', false];
        yield 'route default on a plain action' => ['/account/dashboard/encrypted', true];
        yield 'route default overriding the class' => ['/encrypted/public/by-route', false];
        yield 'route default for InertiaController' => ['/inertia-page/encrypted', true];
        yield 'InertiaController without it' => ['/inertia-page', false];
    }

    #[DataProvider('routes')]
    public function testHistoryEncryptionFollowsTheRoute(string $uri, bool $encrypted): void
    {
        $this->assertSame($encrypted, $this->encrypts($uri));
    }

    public function testAnActionCanOptOutOfGlobalEncryption(): void
    {
        $this->configure(['history' => ['encrypt' => true]]);

        $this->assertFalse($this->encrypts('/encrypted/public'));
        $this->assertTrue($this->encrypts('/account/dashboard'), 'Everything else follows the config.');
    }

    public function testTheControllerHasTheLastWord(): void
    {
        TestKernel::$controller = function () {
            $this->inertia()->encryptHistory(false);

            return $this->inertia()->render('Dashboard');
        };

        $this->assertFalse($this->encrypts('/example-url/encrypted'));
    }

    public function testEncryptionDoesNotLeakIntoTheNextRequest(): void
    {
        $client = new KernelBrowser(self::bootKernel());
        $client->request('GET', '/account/billing', server: ['HTTP_X_INERTIA' => 'true']);
        $client->request('GET', '/account/dashboard', server: ['HTTP_X_INERTIA' => 'true']);

        $this->assertArrayNotHasKey('encryptHistory', $this->getPage($client->getResponse()));
    }
}
