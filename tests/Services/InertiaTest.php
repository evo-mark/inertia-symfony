<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\Services;

use EvoMark\InertiaSymfonyBundle\Config\InertiaConfig;
use EvoMark\InertiaSymfonyBundle\Services\Inertia;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class InertiaTest extends TestCase
{
    public function testVersionDefaultsToTheAssetUrlHash(): void
    {
        $inertia = new Inertia(
            new InertiaConfig([
                'root_view' => 'app.html.twig',
                'version' => null,
                'asset_url' => 'https://cdn.example.com',
                'manifest_path' => '/non/existent/manifest.json',
            ]),
            new Environment(new ArrayLoader()),
            new RequestStack(),
        );

        $this->assertSame(hash('xxh128', 'https://cdn.example.com'), $inertia->getVersion());
    }

    public function testViewDataCanBeReadBackWholeOrByKey(): void
    {
        $inertia = new Inertia(
            new InertiaConfig(['root_view' => 'app.html.twig', 'version' => '1', 'asset_url' => null, 'manifest_path' => '/non/existent/manifest.json']),
            new Environment(new ArrayLoader()),
            new RequestStack(),
        );

        $inertia->viewData('title', 'Dashboard');
        $inertia->viewData('meta', ['robots' => 'noindex']);

        $this->assertSame('Dashboard', $inertia->getViewData('title'));
        $this->assertSame(['robots' => 'noindex'], $inertia->getViewData('meta'));
        $this->assertNull($inertia->getViewData('missing'));
        $this->assertSame(['title' => 'Dashboard', 'meta' => ['robots' => 'noindex']], $inertia->getViewData());
    }
}
