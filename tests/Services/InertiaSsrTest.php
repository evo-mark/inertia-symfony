<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\Services;

use EvoMark\InertiaSymfonyBundle\Interfaces\SsrGatewayInterface;
use EvoMark\InertiaSymfonyBundle\Ssr\HttpGateway;
use EvoMark\InertiaSymfonyBundle\Tests\Stubs\FakeGateway;
use EvoMark\InertiaSymfonyBundle\Tests\TestCase;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * The SSR methods on the Inertia service, which pass through to the gateway.
 */
final class InertiaSsrTest extends TestCase
{
    public function testDisableSsrDisablesTheGateway(): void
    {
        $this->configure(['ssr' => ['enabled' => true, 'ensure_bundle_exists' => false]]);
        $this->fakeHttpClient(new MockResponse(json_encode(['head' => [], 'body' => '<div id="app"></div>'])));

        $this->inertia()->disableSsr();

        $this->assertNull(static::getContainer()->get(HttpGateway::class)->dispatch(self::EXAMPLE_PAGE_OBJECT));
    }

    public function testDisableSsrAcceptsAClosure(): void
    {
        $this->configure(['ssr' => ['enabled' => true, 'ensure_bundle_exists' => false]]);
        $this->fakeHttpClient(new MockResponse(json_encode(['head' => [], 'body' => '<div id="app"></div>'])));

        $disabled = true;
        $this->inertia()->disableSsr(static function () use (&$disabled): bool {
            return $disabled;
        });
        $gateway = static::getContainer()->get(HttpGateway::class);

        $this->assertNull($gateway->dispatch(self::EXAMPLE_PAGE_OBJECT));

        $disabled = false;
        $this->assertNotNull($gateway->dispatch(self::EXAMPLE_PAGE_OBJECT));
    }

    public function testTheDisableConditionIsResetBetweenRequestsButExcludedPathsAreKept(): void
    {
        $this->inertia()->disableSsr();
        $this->inertia()->withoutSsr('admin/*');

        static::getContainer()->get('services_resetter')->reset();

        $gateway = static::getContainer()->get(HttpGateway::class);
        $this->assertSame(['admin/*'], $gateway->getExcludedPaths());
        $this->assertNull(new \ReflectionProperty($gateway, 'disabled')->getValue($gateway));
    }

    public function testExcludedPathsAreNotDuplicated(): void
    {
        $this->inertia()->withoutSsr(['admin/*', 'nova/*']);
        $this->inertia()->withoutSsr('admin/*');

        $this->assertSame(['admin/*', 'nova/*'], static::getContainer()->get(HttpGateway::class)->getExcludedPaths());
    }

    public function testDisableSsrThrowsWhenTheGatewayDoesNotSupportIt(): void
    {
        self::bootKernel();
        static::getContainer()->set(SsrGatewayInterface::class, new FakeGateway());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The configured SSR gateway does not support disabling server-side rendering conditionally.');

        $this->inertia()->disableSsr();
    }

    public function testWithoutSsrThrowsWhenTheGatewayDoesNotSupportIt(): void
    {
        self::bootKernel();
        static::getContainer()->set(SsrGatewayInterface::class, new FakeGateway());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The configured SSR gateway does not support excluding paths from server-side rendering.');

        $this->inertia()->withoutSsr('admin/*');
    }
}
