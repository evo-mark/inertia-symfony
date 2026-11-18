<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\Fixtures;

use EvoMark\InertiaSymfonyBundle\Controller\InertiaController;
use EvoMark\InertiaSymfonyBundle\EvoMarkInertiaSymfonyBundle;
use EvoMark\InertiaSymfonyBundle\Http\Response as InertiaResponse;
use EvoMark\InertiaSymfonyBundle\Tests\Fixtures\Controller\AccountController;
use EvoMark\InertiaSymfonyBundle\Tests\Fixtures\Controller\EncryptedController;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Minimal application used by the functional tests.
 *
 * It has a single `/example-url` route (any method) whose controller is whatever
 * closure the test assigns to {@see TestKernel::$controller}. Bundle
 * configuration comes from {@see TestKernel::$inertiaConfig}.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @var (\Closure(Request): mixed)|null
     */
    public static ?\Closure $controller = null;

    /**
     * @var array<string, mixed>
     */
    public static array $inertiaConfig = [];

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new EvoMarkInertiaSymfonyBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        // One container per configuration, so tests with different config never share a stale cache.
        return sys_get_temp_dir().'/inertia-symfony-tests/'.md5(serialize(self::$inertiaConfig)).'/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/inertia-symfony-tests/log';
    }

    /**
     * The controller behind `/example-url`.
     */
    public function exampleAction(Request $request): Response|InertiaResponse
    {
        $result = null !== self::$controller ? (self::$controller)($request) : null;

        return $result instanceof Response || $result instanceof InertiaResponse
            ? $result
            : new Response((string) $result);
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'test' => true,
            'secret' => 'test',
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'session' => [
                'storage_factory_id' => 'session.storage.factory.mock_file',
            ],
        ]);

        // Without a logger service the framework logs every request to stderr.
        $container->services()->set('logger', NullLogger::class)->public();

        $container->extension('twig', [
            'default_path' => __DIR__.'/templates',
        ]);

        $container->extension('inertia_symfony', self::$inertiaConfig);

        $container->services()
            ->defaults()->autowire()->autoconfigure()
            ->set(EncryptedController::class)->public()->tag('controller.service_arguments')
            ->set(AccountController::class)->public()->tag('controller.service_arguments');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('example', '/example-url')->controller('kernel::exampleAction');

        // Pages rendered straight from the route by InertiaController.
        $routes->add('inertia_page', '/inertia-page')
            ->controller(InertiaController::class)
            ->defaults(['component' => 'About', 'props' => ['team' => 'Platform', 'members' => ['Ada', 'Grace']]]);
        $routes->add('inertia_page_cached', '/inertia-page/cached')
            ->controller(InertiaController::class)
            ->defaults(['component' => 'About', 'maxAge' => 600, 'sharedAge' => 3600]);
        $routes->add('inertia_page_private', '/inertia-page/private')
            ->controller(InertiaController::class)
            ->defaults(['component' => 'About', 'maxAge' => 600, 'private' => true]);
        // History encryption per route.
        $routes->add('encrypted_show', '/encrypted/show')->controller([EncryptedController::class, 'show']);
        $routes->add('encrypted_public', '/encrypted/public')->controller([EncryptedController::class, 'public']);
        $routes->add('account_billing', '/account/billing')->controller([AccountController::class, 'billing']);
        $routes->add('account_dashboard', '/account/dashboard')->controller([AccountController::class, 'dashboard']);
        $routes->add('account_dashboard_encrypted', '/account/dashboard/encrypted')
            ->controller([AccountController::class, 'dashboard'])
            ->defaults(['_encrypt_history' => true]);
        $routes->add('encrypted_public_by_route', '/encrypted/public/by-route')
            ->controller([EncryptedController::class, 'show'])
            ->defaults(['_encrypt_history' => false]);
        $routes->add('example_encrypted', '/example-url/encrypted')
            ->controller('kernel::exampleAction')
            ->defaults(['_encrypt_history' => true]);
        $routes->add('inertia_page_encrypted', '/inertia-page/encrypted')
            ->controller(InertiaController::class)
            ->defaults(['component' => 'About', '_encrypt_history' => true]);

        $routes->add('inertia_page_gone', '/inertia-page/gone')
            ->controller(InertiaController::class)
            ->defaults(['component' => 'Gone', 'statusCode' => 410, 'headers' => ['X-Robots-Tag' => 'noindex'], 'viewData' => ['title' => 'Gone']]);
    }
}
