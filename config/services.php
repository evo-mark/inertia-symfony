<?php

use EvoMark\InertiaSymfonyBundle\Command\CheckSsrCommand;
use EvoMark\InertiaSymfonyBundle\Command\MakeSubscriberCommand;
use EvoMark\InertiaSymfonyBundle\Command\PublishViewCommand;
use EvoMark\InertiaSymfonyBundle\Command\StartSsrCommand;
use EvoMark\InertiaSymfonyBundle\Command\StopSsrCommand;
use EvoMark\InertiaSymfonyBundle\Config\InertiaConfig;
use EvoMark\InertiaSymfonyBundle\Controller\InertiaController;
use EvoMark\InertiaSymfonyBundle\EventListener\AssetVersionListener;
use EvoMark\InertiaSymfonyBundle\EventListener\EmptyResponseListener;
use EvoMark\InertiaSymfonyBundle\EventListener\EncryptHistoryListener;
use EvoMark\InertiaSymfonyBundle\EventListener\ExceptionListener;
use EvoMark\InertiaSymfonyBundle\EventListener\FragmentRedirectListener;
use EvoMark\InertiaSymfonyBundle\EventListener\InertiaResponseListener;
use EvoMark\InertiaSymfonyBundle\EventListener\RedirectStatusListener;
use EvoMark\InertiaSymfonyBundle\EventListener\SessionDataListener;
use EvoMark\InertiaSymfonyBundle\EventListener\ValidationErrorsListener;
use EvoMark\InertiaSymfonyBundle\EventListener\ValidationFailedListener;
use EvoMark\InertiaSymfonyBundle\EventListener\VaryHeaderListener;
use EvoMark\InertiaSymfonyBundle\Interfaces\InertiaInterface;
use EvoMark\InertiaSymfonyBundle\Interfaces\SsrGatewayInterface;
use EvoMark\InertiaSymfonyBundle\Services\Inertia;
use EvoMark\InertiaSymfonyBundle\Services\PageFinder;
use EvoMark\InertiaSymfonyBundle\Ssr\BundleDetector;
use EvoMark\InertiaSymfonyBundle\Ssr\DevServerDetector;
use EvoMark\InertiaSymfonyBundle\Ssr\HttpGateway;
use EvoMark\InertiaSymfonyBundle\Ssr\SsrState;
use EvoMark\InertiaSymfonyBundle\Twig\InertiaExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/*
 * Every service is defined explicitly, without autowiring or autoconfiguration,
 * and named after the bundle's alias, as Symfony's bundle best practices ask.
 * Class and interface aliases let applications type-hint them as usual.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // The Inertia service: renders pages and holds shared props for the request.
    $services->set('evo_mark_inertia_symfony.inertia', Inertia::class)
        ->args([
            service('evo_mark_inertia_symfony.config'),
            service('twig'),
            service('request_stack'),
            service('serializer')->nullOnInvalid(),
            service('logger')->nullOnInvalid(),
            service('evo_mark_inertia_symfony.page_finder'),
            service('evo_mark_inertia_symfony.ssr.gateway'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);
    $services->alias(InertiaInterface::class, 'evo_mark_inertia_symfony.inertia');
    $services->alias(Inertia::class, 'evo_mark_inertia_symfony.inertia');

    // Finds frontend page files for `pages.ensure_pages_exist`.
    $services->set('evo_mark_inertia_symfony.page_finder', PageFinder::class)
        ->args([service('evo_mark_inertia_symfony.config')]);
    $services->alias(PageFinder::class, 'evo_mark_inertia_symfony.page_finder');

    // The inertiaHead() and inertiaApp() Twig functions.
    $services->set('evo_mark_inertia_symfony.twig_extension', InertiaExtension::class)
        ->args([
            service('evo_mark_inertia_symfony.config'),
            service('evo_mark_inertia_symfony.ssr.state'),
        ])
        ->tag('twig.extension');

    // Event listeners, one per concern. Listeners on the same event and priority run
    // in the order they are defined here.
    $inertia = service('evo_mark_inertia_symfony.inertia');
    $config = service('evo_mark_inertia_symfony.config');
    $listeners = [
        ['inertia_response', InertiaResponseListener::class, [], KernelEvents::VIEW, 0],
        ['redirect_status', RedirectStatusListener::class, [], KernelEvents::RESPONSE, 0],
        ['vary_header', VaryHeaderListener::class, [], KernelEvents::RESPONSE, 0],
        ['asset_version', AssetVersionListener::class, [$inertia], KernelEvents::REQUEST, 0],
        ['session_data', SessionDataListener::class, [$config], KernelEvents::RESPONSE, 0],
        ['empty_response', EmptyResponseListener::class, [$inertia], KernelEvents::RESPONSE, 8],
        ['fragment_redirect', FragmentRedirectListener::class, [], KernelEvents::RESPONSE, -8],
        ['validation_errors', ValidationErrorsListener::class, [$inertia, $config], KernelEvents::REQUEST, 0],
    ];

    // Validation failures from #[MapRequestPayload] / #[MapQueryString] become an
    // Inertia redirect back with errors, when symfony/validator is installed. Runs
    // before the exception is logged (priority 0).
    if (class_exists(ValidationFailedException::class)) {
        $listeners[] = ['validation_failed', ValidationFailedListener::class, [$inertia], KernelEvents::EXCEPTION, 16];
    }

    // Runs after the exception is logged and before Symfony's error page (-128).
    $listeners[] = ['exception', ExceptionListener::class, [$inertia], KernelEvents::EXCEPTION, -64];
    $listeners[] = ['encrypt_history', EncryptHistoryListener::class, [$inertia], KernelEvents::CONTROLLER, 0];

    foreach ($listeners as [$name, $class, $arguments, $event, $priority]) {
        $services->set('evo_mark_inertia_symfony.listener.'.$name, $class)
            ->args($arguments)
            ->tag('kernel.event_listener', ['event' => $event, 'method' => '__invoke', 'priority' => $priority]);
    }

    // SSR: the detectors the gateway uses, the gateway, and the rendered page for the
    // current request (shared by inertiaHead() and inertiaApp()).
    $services->set('evo_mark_inertia_symfony.ssr.bundle_detector', BundleDetector::class)
        ->args([service('evo_mark_inertia_symfony.config'), param('kernel.project_dir')]);
    $services->alias(BundleDetector::class, 'evo_mark_inertia_symfony.ssr.bundle_detector');

    $services->set('evo_mark_inertia_symfony.ssr.dev_server_detector', DevServerDetector::class)
        ->args([service('evo_mark_inertia_symfony.config')]);
    $services->alias(DevServerDetector::class, 'evo_mark_inertia_symfony.ssr.dev_server_detector');

    $services->set('evo_mark_inertia_symfony.ssr.gateway', HttpGateway::class)
        ->args([
            service('http_client'),
            service('evo_mark_inertia_symfony.config'),
            service('request_stack'),
            service('event_dispatcher'),
            service('evo_mark_inertia_symfony.ssr.bundle_detector'),
            service('evo_mark_inertia_symfony.ssr.dev_server_detector'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);
    $services->alias(SsrGatewayInterface::class, 'evo_mark_inertia_symfony.ssr.gateway');
    $services->alias(HttpGateway::class, 'evo_mark_inertia_symfony.ssr.gateway');

    $services->set('evo_mark_inertia_symfony.ssr.state', SsrState::class)
        ->args([service('evo_mark_inertia_symfony.ssr.gateway')])
        ->tag('kernel.reset', ['method' => 'reset']);
    $services->alias(SsrState::class, 'evo_mark_inertia_symfony.ssr.state');

    // Renders a page straight from a route's defaults, like Symfony's TemplateController.
    // Routes name it by class, so the controller and its class alias are public.
    $services->set('evo_mark_inertia_symfony.controller', InertiaController::class)
        ->public()
        ->args([service('evo_mark_inertia_symfony.inertia')])
        ->tag('controller.service_arguments');
    $services->alias(InertiaController::class, 'evo_mark_inertia_symfony.controller')->public();

    // Console commands.
    $services->set('evo_mark_inertia_symfony.command.check_ssr', CheckSsrCommand::class)
        ->args([service('evo_mark_inertia_symfony.ssr.gateway')])
        ->tag('console.command', ['command' => 'inertia:check-ssr', 'description' => 'Check the Inertia SSR server health status']);

    $services->set('evo_mark_inertia_symfony.command.subscriber', MakeSubscriberCommand::class)
        ->args([param('kernel.project_dir'), service('filesystem')])
        ->tag('console.command', ['command' => 'inertia:subscriber', 'description' => 'Create an event listener that shares Inertia props on every request']);

    $services->set('evo_mark_inertia_symfony.command.view', PublishViewCommand::class)
        ->args([param('kernel.project_dir'), service('filesystem'), service('evo_mark_inertia_symfony.config')])
        ->tag('console.command', ['command' => 'inertia:view', 'description' => 'Create the root Twig template for Inertia pages']);

    $services->set('evo_mark_inertia_symfony.command.start_ssr', StartSsrCommand::class)
        ->args([
            service('evo_mark_inertia_symfony.config'),
            service('evo_mark_inertia_symfony.ssr.bundle_detector'),
            service('logger')->nullOnInvalid(),
        ])
        ->tag('console.command', ['command' => 'inertia:start-ssr', 'description' => 'Start the Inertia SSR server']);

    $services->set('evo_mark_inertia_symfony.command.stop_ssr', StopSsrCommand::class)
        ->args([service('evo_mark_inertia_symfony.ssr.gateway')])
        ->tag('console.command', ['command' => 'inertia:stop-ssr', 'description' => 'Stop the Inertia SSR server']);
};
