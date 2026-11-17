<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\Ssr;

use EvoMark\InertiaSymfonyBundle\Ssr\SsrState;
use EvoMark\InertiaSymfonyBundle\Tests\Stubs\FakeGateway;
use PHPUnit\Framework\TestCase;

final class SsrStateTest extends TestCase
{
    private const array PAGE = ['component' => 'Foo/Bar', 'props' => [], 'url' => '/', 'version' => ''];

    public function testTheSamePageIsDispatchedOnce(): void
    {
        $gateway = new FakeGateway(true);
        $state = new SsrState($gateway);

        $first = $state->setPage(self::PAGE)->dispatch();
        $second = $state->setPage(self::PAGE)->dispatch();

        $this->assertSame(1, $gateway->times);
        $this->assertSame($first, $second);
    }

    public function testADifferentPageIsDispatchedAgain(): void
    {
        $gateway = new FakeGateway(true);
        $state = new SsrState($gateway);

        $state->setPage(self::PAGE)->dispatch();
        $state->setPage([...self::PAGE, 'url' => '/other'])->dispatch();

        $this->assertSame(2, $gateway->times);
    }

    public function testAFailedRenderIsNotRetriedForTheSamePage(): void
    {
        $gateway = new FakeGateway(false);
        $state = new SsrState($gateway);

        $this->assertNull($state->setPage(self::PAGE)->dispatch());
        $this->assertNull($state->dispatch());
        $this->assertSame(1, $gateway->times);
    }

    public function testResetForgetsThePageAndResponse(): void
    {
        $gateway = new FakeGateway(true);
        $state = new SsrState($gateway);
        $state->setPage(self::PAGE)->dispatch();

        $state->reset();

        $this->assertSame([], $state->page);
        $this->assertNull($state->response);

        $state->setPage(self::PAGE)->dispatch();
        $this->assertSame(2, $gateway->times);
    }
}
