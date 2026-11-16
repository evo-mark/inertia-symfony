<?php

use EvoMark\InertiaSymfonyBundle\EventListener\AssetVersionListener;
use EvoMark\InertiaSymfonyBundle\EventListener\RedirectStatusListener;
use EvoMark\InertiaSymfonyBundle\EventListener\VaryHeaderListener;
use EvoMark\InertiaSymfonyBundle\Services\Inertia;
use EvoMark\InertiaSymfonyBundle\Services\InertiaInterface;
use EvoMark\InertiaSymfonyBundle\Ssr\GatewayInterface;
use EvoMark\InertiaSymfonyBundle\Ssr\HttpGateway;
use EvoMark\InertiaSymfonyBundle\Twig\InertiaExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure();

    // Core Inertia service. Config values arrive via #[Autowire] on its constructor.
    $services->set(Inertia::class);
    $services->alias(InertiaInterface::class, Inertia::class);

    // Twig functions; Inertia and the gateway are autowired by interface.
    $services->set(InertiaExtension::class);

    // Event listeners, one per concern; #[AsEventListener] registers each one.
    $services->set(RedirectStatusListener::class);
    $services->set(VaryHeaderListener::class);
    $services->set(AssetVersionListener::class);

    // SSR gateway. Autowiring resolves HttpClientInterface, so no explicit
    // arguments are needed; add them here once HttpGateway has a constructor.
    $services->set(HttpGateway::class);
    $services->alias(GatewayInterface::class, HttpGateway::class);
};
