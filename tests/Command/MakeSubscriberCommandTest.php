<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\Command;

use EvoMark\InertiaSymfonyBundle\Command\MakeSubscriberCommand;
use EvoMark\InertiaSymfonyBundle\Tests\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

final class MakeSubscriberCommandTest extends TestCase
{
    private string $projectDir;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem();
        $this->projectDir = sys_get_temp_dir().'/inertia-symfony-tests/make-subscriber-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->projectDir);

        parent::tearDown();
    }

    private function tester(): CommandTester
    {
        return new CommandTester(new MakeSubscriberCommand($this->projectDir, $this->filesystem));
    }

    public function testTheCommandIsRegistered(): void
    {
        $application = new Application(self::bootKernel());

        $this->assertTrue($application->has('inertia:subscriber'));
    }

    public function testItPublishesTheSubscriber(): void
    {
        $tester = $this->tester();

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('Created src/EventSubscriber/InertiaSubscriber.php', $tester->getDisplay());

        $contents = $this->filesystem->readFile($this->projectDir.'/src/EventSubscriber/InertiaSubscriber.php');
        $this->assertStringContainsString('namespace App\EventSubscriber;', $contents);
        $this->assertStringContainsString('final class InertiaSubscriber', $contents);
        $this->assertStringContainsString('use EvoMark\InertiaSymfonyBundle\Interfaces\InertiaInterface;', $contents);
        $this->assertStringNotContainsString('{{', $contents);
    }

    public function testItAcceptsACustomClassName(): void
    {
        $this->assertSame(Command::SUCCESS, $this->tester()->execute(['name' => 'ShareInertiaProps']));

        $contents = $this->filesystem->readFile($this->projectDir.'/src/EventSubscriber/ShareInertiaProps.php');
        $this->assertStringContainsString('final class ShareInertiaProps', $contents);
    }

    public function testThePublishedClassIsValidPhp(): void
    {
        $this->tester()->execute([]);

        exec(\sprintf('%s -l %s 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($this->projectDir.'/src/EventSubscriber/InertiaSubscriber.php')), $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));
    }

    public function testItRefusesToOverwriteAnExistingClass(): void
    {
        $target = $this->projectDir.'/src/EventSubscriber/InertiaSubscriber.php';
        $this->filesystem->dumpFile($target, 'original');

        $tester = $this->tester();

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringContainsString('already exists', $tester->getDisplay());
        $this->assertSame('original', $this->filesystem->readFile($target));
    }

    public function testItOverwritesWithForce(): void
    {
        $target = $this->projectDir.'/src/EventSubscriber/InertiaSubscriber.php';
        $this->filesystem->dumpFile($target, 'original');

        $this->assertSame(Command::SUCCESS, $this->tester()->execute(['--force' => true]));
        $this->assertStringContainsString('final class InertiaSubscriber', $this->filesystem->readFile($target));
    }

    public function testItRejectsInvalidClassNames(): void
    {
        $tester = $this->tester();

        $this->assertSame(Command::INVALID, $tester->execute(['name' => '../Evil']));
        $this->assertDirectoryDoesNotExist($this->projectDir.'/src');
    }
}
