<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\Command;

use EvoMark\InertiaSymfonyBundle\Command\PublishViewCommand;
use EvoMark\InertiaSymfonyBundle\Config\InertiaConfig;
use EvoMark\InertiaSymfonyBundle\Tests\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

final class PublishViewCommandTest extends TestCase
{
    private string $projectDir;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem();
        $this->projectDir = sys_get_temp_dir().'/inertia-symfony-tests/publish-view-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->projectDir);

        parent::tearDown();
    }

    private function tester(string $rootView = 'app.html.twig'): CommandTester
    {
        return new CommandTester(new PublishViewCommand(
            $this->projectDir,
            $this->filesystem,
            new InertiaConfig(['root_view' => $rootView]),
        ));
    }

    public function testTheCommandIsRegistered(): void
    {
        $application = new Application(self::bootKernel());

        $this->assertTrue($application->has('inertia:view'));
    }

    public function testItPublishesTheRootView(): void
    {
        $tester = $this->tester();

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('Created templates/app.html.twig', $tester->getDisplay());

        $contents = $this->filesystem->readFile($this->projectDir.'/templates/app.html.twig');
        $this->assertStringContainsString('{{ inertiaHead(page) }}', $contents);
        $this->assertStringContainsString('{{ inertiaApp(page) }}', $contents);
    }

    public function testItUsesTheConfiguredRootViewName(): void
    {
        $this->assertSame(Command::SUCCESS, $this->tester('layouts/root.html.twig')->execute([]));
        $this->assertFileExists($this->projectDir.'/templates/layouts/root.html.twig');
    }

    public function testItRefusesToOverwriteAnExistingView(): void
    {
        $target = $this->projectDir.'/templates/app.html.twig';
        $this->filesystem->dumpFile($target, 'original');

        $tester = $this->tester();

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringContainsString('already exists', $tester->getDisplay());
        $this->assertSame('original', $this->filesystem->readFile($target));
    }

    public function testItOverwritesWithForce(): void
    {
        $target = $this->projectDir.'/templates/app.html.twig';
        $this->filesystem->dumpFile($target, 'original');

        $this->assertSame(Command::SUCCESS, $this->tester()->execute(['--force' => true]));
        $this->assertStringContainsString('inertiaApp', $this->filesystem->readFile($target));
    }

    public function testItRejectsRootViewsOutsideTheTemplatesDirectory(): void
    {
        $this->assertSame(Command::INVALID, $this->tester('../../evil.twig')->execute([]));
        $this->assertSame(Command::INVALID, $this->tester('/etc/evil.twig')->execute([]));
        $this->assertDirectoryDoesNotExist($this->projectDir.'/templates');
    }
}
