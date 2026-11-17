<?php

namespace EvoMark\InertiaSymfonyBundle\Command;

use EvoMark\InertiaSymfonyBundle\Config\InertiaConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Publishes the root Twig template into the application, named after the
 * configured `root_view`.
 */
#[AsCommand(name: 'inertia:view', description: 'Create the root Twig template for Inertia pages')]
final class PublishViewCommand
{
    private const string STUB = __DIR__.'/../../stubs/app.html.twig.stub';

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
        private Filesystem $filesystem,
        private InertiaConfig $config,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Overwrite the template if it already exists', shortcut: 'f')] bool $force = false,
    ): int {
        $view = $this->config->getRootView();

        if (Path::isAbsolute($view) || \in_array('..', explode('/', $view), true)) {
            $io->error(\sprintf('"%s" is not a valid root view name.', $view));

            return Command::INVALID;
        }

        $target = Path::join($this->projectDir, 'templates', $view);
        $relative = Path::makeRelative($target, $this->projectDir);

        if (!$force && $this->filesystem->exists($target)) {
            $io->error(\sprintf('%s already exists. Use --force to overwrite it.', $relative));

            return Command::FAILURE;
        }

        $this->filesystem->dumpFile($target, $this->filesystem->readFile(self::STUB));

        $io->success(\sprintf('Created %s', $relative));

        return Command::SUCCESS;
    }
}
