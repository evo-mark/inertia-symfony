<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\Command;

use EvoMark\InertiaSymfonyBundle\Command\StopSsrCommand;
use EvoMark\InertiaSymfonyBundle\Config\InertiaConfig;
use EvoMark\InertiaSymfonyBundle\Ssr\BundleDetector;
use EvoMark\InertiaSymfonyBundle\Ssr\HttpGateway;
use EvoMark\InertiaSymfonyBundle\Tests\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Process\Process;

/**
 * Runs against a real socket: a small PHP server stands in for the SSR server,
 * either closing the connection without a reply (how it shuts down) or replying.
 */
final class StopSsrCommandTest extends TestCase
{
    private ?Process $server = null;

    protected function tearDown(): void
    {
        $this->server?->stop(0);

        parent::tearDown();
    }

    /**
     * Start a one-connection server on a free port and return that port.
     */
    private function startServer(bool $reply): int
    {
        $respond = $reply ? 'fwrite($c, "HTTP/1.1 200 OK\r\nContent-Length: 2\r\nConnection: close\r\n\r\nok");' : '';
        $code = <<<PHP
            \$s = stream_socket_server('tcp://127.0.0.1:0', \$errno, \$error);
            echo parse_url('tcp://'.stream_socket_get_name(\$s, false), PHP_URL_PORT), "\\n";
            \$c = stream_socket_accept(\$s, 10);
            fread(\$c, 8192);
            {$respond}
            fclose(\$c);
            PHP;

        $this->server = new Process([\PHP_BINARY, '-r', $code]);
        $this->server->start();
        $this->server->waitUntil(static fn (string $type, string $output): bool => str_contains($output, "\n"));

        return (int) trim($this->server->getOutput());
    }

    /**
     * A port with nothing listening on it.
     */
    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) parse_url('tcp://'.stream_socket_get_name($socket, false), \PHP_URL_PORT);
        fclose($socket);

        return $port;
    }

    private function tester(int $port): CommandTester
    {
        $config = new InertiaConfig(['ssr' => ['url' => "http://127.0.0.1:{$port}"]]);
        $gateway = new HttpGateway(HttpClient::create(), $config, new RequestStack(), new EventDispatcher(), new BundleDetector($config, sys_get_temp_dir()));

        return new CommandTester(new StopSsrCommand($gateway));
    }

    public function testTheCommandIsRegistered(): void
    {
        $this->assertTrue(new Application(self::bootKernel())->has('inertia:stop-ssr'));
    }

    public function testItStopsTheSsrServer(): void
    {
        $tester = $this->tester($this->startServer(reply: false));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('Inertia SSR server stopped.', $tester->getDisplay());
    }

    public function testItFailsWhenTheSsrServerIsNotRunning(): void
    {
        $tester = $this->tester(self::freePort());

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringContainsString('Unable to connect to Inertia SSR server.', $tester->getDisplay());
    }

    public function testItSucceedsGracefullyWhenTheSsrServerIsNotRunning(): void
    {
        $tester = $this->tester(self::freePort());

        $this->assertSame(Command::SUCCESS, $tester->execute(['--graceful' => true]));
        $this->assertStringContainsString('Inertia SSR server is not running.', $tester->getDisplay());
    }

    public function testItFailsWhenTheServerRepliesInsteadOfShuttingDown(): void
    {
        $tester = $this->tester($this->startServer(reply: true));

        $this->assertSame(Command::FAILURE, $tester->execute(['--graceful' => true]));
        $this->assertStringContainsString('Unable to connect to Inertia SSR server.', $tester->getDisplay());
    }
}
