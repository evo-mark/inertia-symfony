<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\Command;

use EvoMark\InertiaSymfonyBundle\Command\StartSsrCommand;
use EvoMark\InertiaSymfonyBundle\Config\InertiaConfig;
use EvoMark\InertiaSymfonyBundle\Ssr\BundleDetector;
use EvoMark\InertiaSymfonyBundle\Tests\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The SSR "runtime" is the PHP binary and the "bundle" a PHP script, so the
 * command runs and streams a real process without needing Node.
 */
final class StartSsrCommandTest extends TestCase
{
    private string $projectDir;

    private Filesystem $filesystem;

    /**
     * @var list<string>
     */
    private array $requests = [];

    /**
     * @var list<string>
     */
    private array $logged = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem();
        $this->projectDir = sys_get_temp_dir().'/inertia-symfony-tests/start-ssr-'.bin2hex(random_bytes(4));
        $this->filesystem->mkdir($this->projectDir);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->projectDir);

        parent::tearDown();
    }

    /**
     * Write a PHP script standing in for the SSR bundle.
     */
    private function bundle(string $code, string $path = 'var/ssr/ssr.js'): string
    {
        $this->filesystem->dumpFile($file = $this->projectDir.'/'.$path, "<?php\n".$code);

        return $file;
    }

    /**
     * @param array<string, mixed> $ssr
     */
    private function tester(array $ssr = []): CommandTester
    {
        $config = new InertiaConfig(['ssr' => [
            'enabled' => true,
            'url' => 'http://127.0.0.1:13714',
            'runtime' => \PHP_BINARY,
            'ensure_runtime_exists' => false,
            'bundle' => $this->projectDir.'/var/ssr/ssr.js',
            ...$ssr,
        ]]);

        $logger = new class($this->logged) extends AbstractLogger {
            /**
             * @param list<string> $logged
             */
            public function __construct(private array &$logged)
            {
            }

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->logged[] = $level.': '.strtr((string) $message, ['{output}' => $context['output'] ?? '']);
            }
        };

        // A stand-in for inertia:stop-ssr that records each time it is run.
        $application = new ConsoleApplication();
        $application->setAutoExit(false);
        $application->addCommand(new StartSsrCommand($config, new BundleDetector($config, $this->projectDir), $logger));
        $application->addCommand(new Command('inertia:stop-ssr')->setCode(function (): int {
            $this->requests[] = 'inertia:stop-ssr';

            return Command::SUCCESS;
        }));

        return new CommandTester($application->find('inertia:start-ssr'));
    }

    public function testTheCommandIsRegistered(): void
    {
        $this->assertTrue(new Application(self::bootKernel())->has('inertia:start-ssr'));
    }

    public function testItRunsTheBundleAndStreamsItsOutput(): void
    {
        $this->bundle('echo "Starting SSR server on port 13714...\n"; fwrite(STDERR, "Render failed\n");');

        $tester = $this->tester();

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('Starting SSR server on port 13714...', $tester->getDisplay());
        $this->assertStringContainsString('Render failed', $tester->getDisplay());
        $this->assertSame(['error: Inertia SSR server error: Render failed'], $this->logged);
    }

    public function testItStopsARunningServerFirst(): void
    {
        $this->bundle('echo "started";');

        $this->tester()->execute([]);

        $this->assertSame(['inertia:stop-ssr'], $this->requests);
    }

    public function testTheShutdownRequestCanBeSkipped(): void
    {
        $this->bundle('echo "started";');

        $this->tester()->execute(['--no-stop-before-start' => true]);

        $this->assertSame([], $this->requests);
    }

    public function testTheRuntimeCanBeChosen(): void
    {
        $this->bundle('echo "started";');

        $tester = $this->tester(['runtime' => 'not-a-runtime']);

        $this->assertSame(Command::SUCCESS, $tester->execute(['--runtime' => \PHP_BINARY]));
        $this->assertStringContainsString('started', $tester->getDisplay());
    }

    public function testItFailsWhenSsrIsDisabled(): void
    {
        $tester = $this->tester(['enabled' => false]);

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringContainsString('Inertia SSR is not enabled.', $tester->getDisplay());
    }

    public function testItFailsWhenTheConfiguredBundleIsMissing(): void
    {
        $tester = $this->tester(['bundle' => $this->projectDir.'/build/missing.js']);

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringContainsString('Inertia SSR bundle not found at the configured path', $tester->getDisplay());
        $this->assertStringContainsString('missing.js', $tester->getDisplay());
    }

    public function testItFailsWhenNoBundleIsConfiguredOrFound(): void
    {
        $tester = $this->tester(['bundle' => null]);

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringContainsString('Set the correct Inertia SSR bundle path', $tester->getDisplay());
    }

    public function testItWarnsWhenFallingBackToADefaultBundle(): void
    {
        $this->bundle('echo "started";', 'var/ssr/app.js');

        $tester = $this->tester(['bundle' => $this->projectDir.'/build/missing.js']);

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('Using a default bundle instead', $tester->getDisplay());
        $this->assertStringContainsString('started', $tester->getDisplay());
    }

    public function testItFailsWhenTheRuntimeCannotBeFound(): void
    {
        $this->bundle('echo "started";');

        $tester = $this->tester(['runtime' => 'definitely-not-a-runtime', 'ensure_runtime_exists' => true]);

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringContainsString('SSR runtime "definitely-not-a-runtime" could not be found.', $tester->getDisplay());
    }
}
