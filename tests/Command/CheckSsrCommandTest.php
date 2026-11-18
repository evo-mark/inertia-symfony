<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\Command;

use EvoMark\InertiaSymfonyBundle\Command\CheckSsrCommand;
use EvoMark\InertiaSymfonyBundle\Interfaces\SsrGatewayInterface;
use EvoMark\InertiaSymfonyBundle\Interfaces\SsrHasHealthCheckInterface;
use EvoMark\InertiaSymfonyBundle\Ssr\HttpGateway;
use EvoMark\InertiaSymfonyBundle\Ssr\Response;
use EvoMark\InertiaSymfonyBundle\Tests\Stubs\FakeGateway;
use EvoMark\InertiaSymfonyBundle\Tests\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\Response\MockResponse;

final class CheckSsrCommandTest extends TestCase
{
    private function tester(SsrGatewayInterface $gateway): CommandTester
    {
        return new CommandTester(new CheckSsrCommand($gateway));
    }

    private static function gatewayWithHealth(bool $healthy): SsrGatewayInterface
    {
        return new class($healthy) implements SsrGatewayInterface, SsrHasHealthCheckInterface {
            public function __construct(private bool $healthy)
            {
            }

            public function dispatch(array $page): ?Response
            {
                return null;
            }

            public function isHealthy(): bool
            {
                return $this->healthy;
            }
        };
    }

    public function testTheCommandIsRegistered(): void
    {
        $this->assertTrue(new Application(self::bootKernel())->has('inertia:check-ssr'));
    }

    public function testItSucceedsWhenTheSsrServerIsRunning(): void
    {
        $tester = $this->tester(self::gatewayWithHealth(true));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('Inertia SSR server is running.', $tester->getDisplay());
    }

    public function testItFailsWhenTheSsrServerIsNotRunning(): void
    {
        $tester = $this->tester(self::gatewayWithHealth(false));

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringContainsString('Inertia SSR server is not running.', $tester->getDisplay());
    }

    public function testItFailsWhenTheGatewayDoesNotSupportHealthChecks(): void
    {
        $tester = $this->tester(new FakeGateway());

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringContainsString('The SSR gateway does not support health checks.', $tester->getDisplay());
    }

    public function testItChecksTheHealthEndpointOfTheHttpGateway(): void
    {
        $requested = [];
        $this->fakeHttpClient(function (string $method, string $url) use (&$requested) {
            $requested[] = "{$method} {$url}";

            return new MockResponse('', ['http_code' => 200]);
        });

        $tester = $this->tester(static::getContainer()->get(HttpGateway::class));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertSame(['GET http://127.0.0.1:13714/health'], $requested);
    }
}
