<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests;

use EvoMark\InertiaSymfonyBundle\EventListener\RedirectStatusListener;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

/**
 * The bundle's container wiring: Twig functions and global event listeners.
 */
final class EvoMarkInertiaSymfonyBundleTest extends TestCase
{
    public function testTwigFunctionsAreRegistered(): void
    {
        /** @var Environment $twig */
        $twig = static::getContainer()->get('twig');

        $this->assertNotNull($twig->getFunction('inertiaApp'));
        $this->assertNotNull($twig->getFunction('inertiaHead'));
    }

    public function testTheRedirectStatusListenerIsRegisteredGlobally(): void
    {
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = static::getContainer()->get('event_dispatcher');

        $listeners = array_map(
            static fn (mixed $listener) => \is_array($listener) ? $listener[0]::class : (\is_object($listener) ? $listener::class : null),
            $dispatcher->getListeners(KernelEvents::RESPONSE),
        );

        $this->assertContains(RedirectStatusListener::class, $listeners);
    }

    /**
     * A listener can answer with a redirect before the controller runs (a rate
     * limiter, say); that redirect must still become a 303.
     */
    public function testRedirectResponsesProducedBeforeTheControllerAreConvertedTo303(): void
    {
        self::bootKernel();

        static::getContainer()->get('event_dispatcher')->addListener(KernelEvents::REQUEST, static function (RequestEvent $event) {
            if ('limited' === $event->getRequest()->query->get('mode')) {
                $event->setResponse(new RedirectResponse('/bar'));
            }
        }, 64);

        $response = $this->makeMockRequest('ok', ['X-Inertia' => 'true', 'Referer' => '/bar'], 'DELETE');
        $this->assertSame(200, $response->getStatusCode());

        $response = $this->makeMockRequest('ok', ['X-Inertia' => 'true', 'Referer' => '/bar'], 'DELETE', '/example-url?mode=limited');
        $this->assertTrue($response->isRedirect('/bar'));
        $this->assertSame(303, $response->getStatusCode());
    }
}
