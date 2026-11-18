<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\Ssr;

use EvoMark\InertiaSymfonyBundle\Config\InertiaConfig;
use EvoMark\InertiaSymfonyBundle\Ssr\DevServerDetector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class DevServerDetectorTest extends TestCase
{
    private string $buildDir;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->buildDir = sys_get_temp_dir().'/inertia-symfony-tests/dev-server-'.bin2hex(random_bytes(4));
        $this->filesystem->mkdir($this->buildDir);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->buildDir);
    }

    private function detector(): DevServerDetector
    {
        return new DevServerDetector(new InertiaConfig(['manifest_path' => $this->buildDir.'/manifest.json']));
    }

    private function entrypoints(string $json): void
    {
        $this->filesystem->dumpFile($this->buildDir.'/entrypoints.json', $json);
    }

    public function testItReportsTheOriginOfARunningDevServer(): void
    {
        $this->entrypoints('{"isProd": false, "devServer": {"origin": "http://127.0.0.1:5173"}}');

        $this->assertSame('http://127.0.0.1:5173', $this->detector()->getOrigin());
        $this->assertTrue($this->detector()->isRunning());
    }

    public function testAProductionBuildHasNoDevServer(): void
    {
        $this->entrypoints('{"isProd": true, "devServer": null}');

        $this->assertNull($this->detector()->getOrigin());
    }

    public function testNoEntrypointsFileMeansNoDevServer(): void
    {
        $this->assertNull($this->detector()->getOrigin());
        $this->assertFalse($this->detector()->isRunning());
    }

    public function testAnInvalidEntrypointsFileMeansNoDevServer(): void
    {
        $this->entrypoints('{not json');
        $this->assertNull($this->detector()->getOrigin());

        $this->entrypoints('{"isProd": false, "devServer": {"origin": ""}}');
        $this->assertNull($this->detector()->getOrigin());

        $this->entrypoints('{"devServer": {"origin": "http://127.0.0.1:5173"}}');
        $this->assertNull($this->detector()->getOrigin(), 'Only an explicit "isProd": false counts.');
    }
}
