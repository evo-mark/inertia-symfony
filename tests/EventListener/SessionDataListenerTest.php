<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\EventListener;

use EvoMark\InertiaSymfonyBundle\Support\SessionKey;
use EvoMark\InertiaSymfonyBundle\Tests\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Storing an Inertia visit as the previous URL and route ({@see SessionKey}).
 */
final class SessionDataListenerTest extends TestCase
{
    private const array INERTIA_VISIT = ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'];

    private function visit(array $headers = self::INERTIA_VISIT, string $method = 'GET', string $uri = '/example-url?page=2'): Response
    {
        return $this->makeMockRequest(fn () => $this->inertia()->render('User/Edit'), $headers, $method, $uri);
    }

    private function previous(string $key): mixed
    {
        return $this->lastRequest->getSession()->get($key);
    }

    public function testInertiaVisitsAreStoredAsThePreviousUrlAndRoute(): void
    {
        $this->configure(['store_previous_url' => true]);

        $this->assertTrue($this->visit()->isSuccessful());
        $this->assertSame('http://localhost/example-url?page=2', $this->previous(SessionKey::PREVIOUS_URL));
        $this->assertSame('example', $this->previous(SessionKey::PREVIOUS_ROUTE));
    }

    public function testStoringThePreviousUrlAndRouteCanBeDisabled(): void
    {
        $this->configure(['store_previous_url' => false]);

        $this->visit();

        $this->assertNull($this->previous(SessionKey::PREVIOUS_URL));
        $this->assertNull($this->previous(SessionKey::PREVIOUS_ROUTE));
    }

    public function testNonInertiaVisitsAreNotStored(): void
    {
        $this->configure(['store_previous_url' => true]);

        $this->visit(['X-Requested-With' => 'XMLHttpRequest']);

        $this->assertNull($this->previous(SessionKey::PREVIOUS_URL));
    }

    public function testNonGetVisitsAreNotStored(): void
    {
        $this->configure(['store_previous_url' => true]);

        $this->visit(method: 'POST');

        $this->assertNull($this->previous(SessionKey::PREVIOUS_URL));
    }

    public function testNonAjaxVisitsAreNotStored(): void
    {
        $this->configure(['store_previous_url' => true]);

        $this->visit(['X-Inertia' => 'true']);

        $this->assertNull($this->previous(SessionKey::PREVIOUS_URL));
    }

    public function testPrefetchVisitsAreNotStored(): void
    {
        $this->configure(['store_previous_url' => true]);

        $this->visit([...self::INERTIA_VISIT, 'Purpose' => 'prefetch']);

        $this->assertNull($this->previous(SessionKey::PREVIOUS_URL));
    }

    public function testPartialReloadsOfTheRenderedComponentAreNotStored(): void
    {
        $this->configure(['store_previous_url' => true]);

        $this->visit([...self::INERTIA_VISIT, 'X-Inertia-Partial-Component' => 'User/Edit', 'X-Inertia-Partial-Data' => 'user']);

        $this->assertNull($this->previous(SessionKey::PREVIOUS_URL));
    }

    public function testPartialReloadsOfADifferentComponentAreStored(): void
    {
        $this->configure(['store_previous_url' => true]);

        $this->visit([...self::INERTIA_VISIT, 'X-Inertia-Partial-Component' => 'Other/Page', 'X-Inertia-Partial-Data' => 'user']);

        $this->assertSame('http://localhost/example-url?page=2', $this->previous(SessionKey::PREVIOUS_URL));
    }

    public function testUnmatchedRoutesAreNotStored(): void
    {
        $this->configure(['store_previous_url' => true]);

        $this->visit(uri: '/non-existent-page');

        $this->assertNull($this->previous(SessionKey::PREVIOUS_URL));
    }
}
