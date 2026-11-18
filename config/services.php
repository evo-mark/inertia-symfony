<?php

use EvoMark\InertiaSymfonyBundle\Command\CheckSsrCommand;
use EvoMark\InertiaSymfonyBundle\Command\MakeSubscriberCommand;
use EvoMark\InertiaSymfonyBundle\Command\PublishViewCommand;
use EvoMark\InertiaSymfonyBundle\Command\StartSsrCommand;
use EvoMark\InertiaSymfonyBundle\Command\StopSsrCommand;
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
use Symfony\Component\Validator\Exception\ValidationFailedException;

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

    // Validation failures from #[MapRequestPayload] / #[MapQueryString] become an
    // Inertia redirect back with errors, when symfony/validator is installed.
    if (class_exists(ValidationFailedException::class)) {
        $services->set(ValidationFailedListener::class);
    }
    $services->set(ExceptionListener::class);
    $services->set(EncryptHistoryListener::class);

    // SSR gateway, and the detector it uses to check the SSR bundle has been built.
    $services->set(BundleDetector::class);
    $services->set(DevServerDetector::class);
    $services->set(HttpGateway::class);
    $services->alias(SsrGatewayInterface::class, HttpGateway::class);

    // The rendered page for the current request, shared by inertiaHead() and inertiaApp().
    $services->set(SsrState::class);

    // Renders a page straight from a route's defaults, like Symfony's TemplateController.
    // Public and tagged so routes can point at it and its arguments are resolved.
    $services->set(InertiaController::class)
        ->public()
        ->tag('controller.service_arguments');

    // Console commands; #[AsCommand] registers each one.
    $services->set(CheckSsrCommand::class);
    $services->set(MakeSubscriberCommand::class);
    $services->set(PublishViewCommand::class);
    $services->set(StartSsrCommand::class);
    $services->set(StopSsrCommand::class);
};
