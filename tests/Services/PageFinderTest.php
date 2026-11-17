<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\Services;

use EvoMark\InertiaSymfonyBundle\Config\InertiaConfig;
use EvoMark\InertiaSymfonyBundle\Services\PageFinder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class PageFinderTest extends TestCase
{
    private string $dir;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->dir = sys_get_temp_dir().'/inertia-symfony-tests/pages-'.bin2hex(random_bytes(4));

        $this->filesystem->dumpFile($this->dir.'/first/Users/Index.vue', '');
        $this->filesystem->dumpFile($this->dir.'/second/Dashboard.tsx', '');
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->dir);
    }

    private function finder(array $extensions = ['vue', 'tsx']): PageFinder
    {
        return new PageFinder(new InertiaConfig(['pages' => [
            'paths' => [$this->dir.'/first', $this->dir.'/second/'],
            'extensions' => $extensions,
        ]]));
    }

    public function testItFindsAPageInAnyPathWithAnyExtension(): void
    {
        $finder = $this->finder();

        $this->assertSame($this->dir.'/first/Users/Index.vue', $finder->find('Users/Index'));
        $this->assertSame($this->dir.'/second/Dashboard.tsx', $finder->find('Dashboard'));
    }

    public function testDotsAreTreatedAsDirectorySeparators(): void
    {
        $this->assertSame($this->dir.'/first/Users/Index.vue', $this->finder()->find('Users.Index'));
    }

    public function testOnlyConfiguredExtensionsMatch(): void
    {
        $this->assertFalse($this->finder(['jsx'])->exists('Users/Index'));
    }

    public function testAMissingPageThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Page [Users/Missing] not found.');

        $this->finder()->find('Users/Missing');
    }

    public function testAPageAddedLaterIsFound(): void
    {
        $finder = $this->finder();
        $this->assertFalse($finder->exists('Settings'));

        $this->filesystem->dumpFile($this->dir.'/first/Settings.vue', '');

        $this->assertTrue($finder->exists('Settings'));
    }
}
