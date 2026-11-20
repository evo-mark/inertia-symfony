<?php

namespace EvoMark\InertiaSymfonyBundle\Command;

use EvoMark\InertiaSymfonyBundle\Config\InertiaConfig;
use EvoMark\InertiaSymfonyBundle\Ssr\BundleDetector;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Runs the built SSR bundle with the configured runtime and streams its output
 * until the server exits or the command is interrupted.
 */
#[AsCommand(name: 'inertia:start-ssr', description: 'Start the Inertia SSR server')]
final class StartSsrCommand
{
    /**
     * @param InertiaConfig        $config         the SSR settings
     * @param BundleDetector       $bundleDetector finds the built SSR bundle
     * @param LoggerInterface|null $logger         receives the server's error output
     */
    public function __construct(
        private InertiaConfig $config,
        private BundleDetector $bundleDetector,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Run the SSR bundle with the runtime and stream its output until it exits.
     *
     * @param Application|null $application       runs inertia:stop-ssr first
     * @param string|null      $runtime           the runtime, instead of `ssr.runtime`
     * @param bool             $noStopBeforeStart skip stopping an already running server
     */
    public function __invoke(
        SymfonyStyle $io,
        ?Application $application,
        #[Option('The runtime to use (e.g. `node`, `bun`, or an absolute path)')] ?string $runtime = null,
        #[Option('Do not send a shutdown request to the configured SSR URL before starting')] bool $noStopBeforeStart = false,
    ): int {
        if (!$this->config->isSsr()) {
            $io->error('Inertia SSR is not enabled. Enable it via the `evo_mark_inertia_symfony.ssr.enabled` config option.');

            return Command::FAILURE;
        }

        $bundle = $this->bundleDetector->detect();
        $configuredBundle = $this->config->getSsrBundle();

        if (null === $bundle) {
            $io->error(null !== $configuredBundle && '' !== $configuredBundle
                ? \sprintf('Inertia SSR bundle not found at the configured path: "%s"', $configuredBundle)
                : 'Inertia SSR bundle not found. Set the correct Inertia SSR bundle path in your `evo_mark_inertia_symfony.ssr.bundle` config.');

            return Command::FAILURE;
        }

        if (null !== $configuredBundle && '' !== $configuredBundle && $bundle !== $configuredBundle) {
            $io->warning([
                \sprintf('Inertia SSR bundle not found at the configured path: "%s"', $configuredBundle),
                \sprintf('Using a default bundle instead: "%s"', $bundle),
            ]);
        }

        $runtime ??= $this->config->getSsrRuntime();

        if ($this->config->shouldEnsureSsrRuntimeExists() && null === new ExecutableFinder()->find($runtime)) {
            $io->error(\sprintf('SSR runtime "%s" could not be found.', $runtime));

            return Command::FAILURE;
        }

        // Stop a server already running on the configured URL, so this one can take its port.
        if (!$noStopBeforeStart && $application?->has('inertia:stop-ssr')) {
            $application->find('inertia:stop-ssr')->run(new ArrayInput([]), new NullOutput());
        }

        $process = new Process([$runtime, $bundle]);
        $process->setTimeout(null);
        $process->start();

        if (\extension_loaded('pcntl')) {
            $stop = static function () use ($process): void {
                $process->stop();
            };

            pcntl_async_signals(true);
            pcntl_signal(\SIGINT, $stop);
            pcntl_signal(\SIGQUIT, $stop);
            pcntl_signal(\SIGTERM, $stop);
        }

        foreach ($process as $type => $data) {
            $data = trim($data);

            if ('' === $data) {
                continue;
            }

            if (Process::OUT === $type) {
                $io->writeln('<info>'.OutputFormatter::escape($data).'</info>');
            } else {
                $io->writeln('<error>'.OutputFormatter::escape($data).'</error>');
                $this->logger?->error('Inertia SSR server error: {output}', ['output' => $data]);
            }
        }

        return Command::SUCCESS;
    }
}
