<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\Http;

use EvoMark\InertiaSymfonyBundle\Http\Response;
use EvoMark\InertiaSymfonyBundle\Support\SessionKey;
use EvoMark\InertiaSymfonyBundle\Tests\Enums\IntBackedEnum;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * The session side of {@see Response}: pulling the clear history, preserve
 * fragment and flash data from the session into the page.
 */
final class ResponseSessionTest extends TestCase
{
    private Session $session;

    protected function setUp(): void
    {
        $this->session = new Session(new MockArraySessionStorage());
        $this->session->start();
    }

    private function request(?Session $session = null): Request
    {
        $request = Request::create('/user/123');
        $request->setSession($session ?? $this->session);

        return $request;
    }

    private function response(): Response
    {
        return new Response('User/Edit', [], ['user' => 'Jonathan']);
    }

    public function testNoSessionDataMeansNoSessionKeysOnThePage(): void
    {
        $page = $this->response()->toPage($this->request());

        $this->assertArrayNotHasKey('clearHistory', $page);
        $this->assertArrayNotHasKey('preserveFragment', $page);
        $this->assertArrayNotHasKey('flash', $page);
    }

    public function testTheClearHistoryFlagIsPulledFromTheSession(): void
    {
        $this->session->set(SessionKey::CLEAR_HISTORY, true);

        $page = $this->response()->toPage($this->request());

        $this->assertTrue($page['clearHistory']);
        $this->assertFalse($this->session->has(SessionKey::CLEAR_HISTORY));
    }

    public function testThePreserveFragmentFlagIsPulledFromTheSession(): void
    {
        $this->session->set(SessionKey::PRESERVE_FRAGMENT, true);

        $page = $this->response()->toPage($this->request());

        $this->assertTrue($page['preserveFragment']);
        $this->assertFalse($this->session->has(SessionKey::PRESERVE_FRAGMENT));
    }

    public function testFlashDataIsPulledFromTheSession(): void
    {
        $this->session->set(SessionKey::FLASH_DATA, ['message' => 'Saved!']);

        $page = $this->response()->toPage($this->request());

        $this->assertSame(['message' => 'Saved!'], $page['flash']);
        $this->assertFalse($this->session->has(SessionKey::FLASH_DATA));
    }

    public function testResponseFlashDataIsMergedOverSessionFlashData(): void
    {
        $this->session->set(SessionKey::FLASH_DATA, ['type' => 'info', 'message' => 'Old']);

        $page = $this->response()
            ->flash('type', 'success')
            ->flash(['message' => 'User updated!'])
            ->toPage($this->request());

        $this->assertSame(['type' => 'success', 'message' => 'User updated!'], $page['flash']);
    }

    public function testFlashKeysCanBeEnums(): void
    {
        $page = $this->response()->flash(IntBackedEnum::Zero, 'value')->toPage($this->request());

        $this->assertSame(['0' => 'value'], $page['flash']);
    }

    public function testBuildingThePageTwiceGivesTheSameResult(): void
    {
        $this->session->set(SessionKey::CLEAR_HISTORY, true);
        $this->session->set(SessionKey::FLASH_DATA, ['message' => 'Saved!']);

        $response = $this->response();
        $request = $this->request();

        $this->assertSame($response->toPage($request), $response->toPage($request));
    }

    public function testAnUnusedSessionIsNotStarted(): void
    {
        $session = new Session(new MockArraySessionStorage());

        $page = $this->response()->toPage($this->request($session));

        $this->assertFalse($session->isStarted());
        $this->assertArrayNotHasKey('flash', $page);
    }

    public function testRequestsWithoutASessionAreSupported(): void
    {
        $page = $this->response()->flash('message', 'Hi')->toPage(Request::create('/'));

        $this->assertSame(['message' => 'Hi'], $page['flash']);
        $this->assertArrayNotHasKey('clearHistory', $page);
    }
}
