<?php

use EvoMark\InertiaSymfonyBundle\Command\MakeSubscriberCommand;
use EvoMark\InertiaSymfonyBundle\EventListener\AssetVersionListener;
use EvoMark\InertiaSymfonyBundle\EventListener\EmptyResponseListener;
use EvoMark\InertiaSymfonyBundle\EventListener\ExceptionListener;
use EvoMark\InertiaSymfonyBundle\EventListener\FragmentRedirectListener;
use EvoMark\InertiaSymfonyBundle\EventListener\InertiaResponseListener;
use EvoMark\InertiaSymfonyBundle\EventListener\RedirectStatusListener;
use EvoMark\InertiaSymfonyBundle\EventListener\SessionDataListener;
use EvoMark\InertiaSymfonyBundle\EventListener\ValidationErrorsListener;
use EvoMark\InertiaSymfonyBundle\EventListener\VaryHeaderListener;
use EvoMark\InertiaSymfonyBundle\Interfaces\InertiaInterface;
use EvoMark\InertiaSymfonyBundle\Interfaces\SsrGatewayInterface;
use EvoMark\InertiaSymfonyBundle\Services\Inertia;
use EvoMark\InertiaSymfonyBundle\Services\PageFinder;
use EvoMark\InertiaSymfonyBundle\Ssr\HttpGateway;
use EvoMark\InertiaSymfonyBundle\Ssr\SsrState;
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

    // Finds frontend page files for `pages.ensure_pages_exist`.
    $services->set(PageFinder::class);

    // Twig functions; Inertia and the gateway are autowired by interface.
    $services->set(InertiaExtension::class);

    // Event listeners, one per concern; #[AsEventListener] registers each one.
    $services->set(InertiaResponseListener::class);
    $services->set(RedirectStatusListener::class);
    $services->set(VaryHeaderListener::class);
    $services->set(AssetVersionListener::class);
    $services->set(SessionDataListener::class);
    $services->set(EmptyResponseListener::class);
    $services->set(FragmentRedirectListener::class);
    $services->set(ValidationErrorsListener::class);
    $services->set(ExceptionListener::class);

    // SSR gateway. Autowiring resolves HttpClientInterface, so no explicit
    // arguments are needed; add them here once HttpGateway has a constructor.
    $services->set(HttpGateway::class);
    $services->alias(SsrGatewayInterface::class, HttpGateway::class);

    // The rendered page for the current request, shared by inertiaHead() and inertiaApp().
    $services->set(SsrState::class);

    // Console commands; #[AsCommand] registers each one.
    $services->set(MakeSubscriberCommand::class);
};
