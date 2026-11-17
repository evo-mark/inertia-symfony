<?php

namespace EvoMark\InertiaSymfonyBundle\Command;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Publishes the Inertia event listener stub into the application, as a
 * starting point for sharing props with every page.
 */
#[AsCommand(name: 'inertia:subscriber', description: 'Create an event listener that shares Inertia props on every request')]
final class MakeSubscriberCommand
{
    private const string NAMESPACE = 'App\\EventSubscriber';

    private const string STUB = __DIR__.'/../../stubs/InertiaSubscriber.php.stub';

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
        private Filesystem $filesystem,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The class name to generate')] string $name = 'InertiaSubscriber',
        #[Option('Overwrite the class if it already exists', shortcut: 'f')] bool $force = false,
    ): int {
        if (!preg_match('/^[A-Z][A-Za-z0-9_]*$/', $name)) {
            $io->error(\sprintf('"%s" is not a valid class name.', $name));

            return Command::INVALID;
        }

        $target = Path::join($this->projectDir, 'src/EventSubscriber', $name.'.php');
        $relative = Path::makeRelative($target, $this->projectDir);

        if (!$force && $this->filesystem->exists($target)) {
            $io->error(\sprintf('%s already exists. Use --force to overwrite it.', $relative));

            return Command::FAILURE;
        }

        $this->filesystem->dumpFile($target, strtr($this->filesystem->readFile(self::STUB), [
            '{{ namespace }}' => self::NAMESPACE,
            '{{ class }}' => $name,
        ]));

        $io->success(\sprintf('Created %s', $relative));

        return Command::SUCCESS;
    }
}
