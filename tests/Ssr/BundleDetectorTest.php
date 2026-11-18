<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\Ssr;

use EvoMark\InertiaSymfonyBundle\Config\InertiaConfig;
use EvoMark\InertiaSymfonyBundle\Ssr\BundleDetector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class BundleDetectorTest extends TestCase
{
    private string $projectDir;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->projectDir = sys_get_temp_dir().'/inertia-symfony-tests/bundle-'.bin2hex(random_bytes(4));
        $this->filesystem->mkdir($this->projectDir);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->projectDir);
    }

    private function detector(?string $bundle = null): BundleDetector
    {
        return new BundleDetector(new InertiaConfig(['ssr' => ['bundle' => $bundle]]), $this->projectDir);
    }

    public function testItFindsTheConfiguredBundle(): void
    {
        $this->filesystem->dumpFile($bundle = $this->projectDir.'/build/server.js', '');

        $this->assertSame($bundle, $this->detector($bundle)->detect());
    }

    public function testItReturnsNullWhenNothingIsBuilt(): void
    {
        $this->assertNull($this->detector($this->projectDir.'/var/ssr/ssr.js')->detect());
    }

    public function testItFallsBackToCommonOutputNamesWhenTheConfiguredBundleIsMissing(): void
    {
        $this->filesystem->dumpFile($this->projectDir.'/var/ssr/app.mjs', '');

        $this->assertSame($this->projectDir.'/var/ssr/app.mjs', $this->detector($this->projectDir.'/missing.js')->detect());
    }

    public function testTheConfiguredBundleWinsOverTheFallbacks(): void
    {
        $this->filesystem->dumpFile($this->projectDir.'/var/ssr/ssr.js', '');
        $this->filesystem->dumpFile($bundle = $this->projectDir.'/build/server.js', '');

        $this->assertSame($bundle, $this->detector($bundle)->detect());
    }

    public function testItFallsBackWhenNoBundleIsConfigured(): void
    {
        $this->filesystem->dumpFile($this->projectDir.'/var/ssr/ssr.js', '');

        $this->assertSame($this->projectDir.'/var/ssr/ssr.js', $this->detector()->detect());
    }

    public function testABundleBuiltLaterIsFound(): void
    {
        $detector = $this->detector();
        $this->assertNull($detector->detect());

        $this->filesystem->dumpFile($this->projectDir.'/var/ssr/ssr.js', '');

        $this->assertNotNull($detector->detect());
    }
}
