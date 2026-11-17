<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\PageProps;

use EvoMark\InertiaSymfonyBundle\Interfaces\ProvidesScrollMetadataInterface;
use EvoMark\InertiaSymfonyBundle\PageProps\ScrollProp;
use EvoMark\InertiaSymfonyBundle\Support\Header;
use EvoMark\InertiaSymfonyBundle\Tests\Stubs\ScrollMetadata;
use EvoMark\InertiaSymfonyBundle\Tests\Stubs\Users;
use EvoMark\InertiaSymfonyBundle\Tests\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Scroll props. A metadata provider is always passed explicitly, as there is
 * no built-in paginator to derive it from.
 */
final class ScrollPropTest extends TestCase
{
    public function testResolvesCustomMetaData(): void
    {
        $customMetaCallback = fn () => new class implements ProvidesScrollMetadataInterface {
            public function getPageName(): string
            {
                return 'usersPage';
            }

            public function getPreviousPage(): int
            {
                return 10;
            }

            public function getNextPage(): int
            {
                return 12;
            }

            public function getCurrentPage(): int
            {
                return 11;
            }
        };

        $scrollProp = new ScrollProp(Users::page(), 'data', $customMetaCallback);

        $metadata = $scrollProp->metadata();

        $this->assertEquals([
            'pageName' => 'usersPage',
            'previousPage' => 10,
            'nextPage' => 12,
            'currentPage' => 11,
        ], $metadata);
    }

    public function testCanSetTheMergeIntentBasedOnTheMergeIntentHeader(): void
    {
        $users = Users::page();

        // Test append intent without header
        $appendProp = new ScrollProp($users);
        $appendProp->configureMergeIntent(Request::create('/'));
        $this->assertContains('data', $appendProp->appendsAtPaths());
        $this->assertEmpty($appendProp->prependsAtPaths());

        // Test append intent with header set to 'append'
        $request = Request::create('/');
        $request->headers->set(Header::INFINITE_SCROLL_MERGE_INTENT, 'append');
        $appendProp = new ScrollProp($users);
        $appendProp->configureMergeIntent($request);
        $this->assertContains('data', $appendProp->appendsAtPaths());
        $this->assertEmpty($appendProp->prependsAtPaths());

        // Test prepend intent
        $request = Request::create('/');
        $request->headers->set(Header::INFINITE_SCROLL_MERGE_INTENT, 'prepend');
        $prependProp = new ScrollProp($users);
        $prependProp->configureMergeIntent($request);
        $this->assertContains('data', $prependProp->prependsAtPaths());
        $this->assertEmpty($prependProp->appendsAtPaths());

        // Test prepend intent with custom wrapper
        $prependProp = new ScrollProp($users, 'items');
        $prependProp->configureMergeIntent($request);
        $this->assertContains('items', $prependProp->prependsAtPaths());
        $this->assertEmpty($prependProp->appendsAtPaths());
    }

    public function testResolvesMetaDataWithCallableProvider(): void
    {
        $callableMetadata = function () {
            return new ScrollMetadata('callablePage', 5, 7, 6);
        };

        $scrollProp = new ScrollProp([], 'data', $callableMetadata);

        $metadata = $scrollProp->metadata();

        $this->assertEquals([
            'pageName' => 'callablePage',
            'previousPage' => 5,
            'nextPage' => 7,
            'currentPage' => 6,
        ], $metadata);
    }

    public function testScrollPropValueIsResolvedOnlyOnce(): void
    {
        $callCount = 0;

        $scrollProp = new ScrollProp(function () use (&$callCount) {
            ++$callCount;

            return ['item1', 'item2', 'item3'];
        });

        $value1 = $scrollProp();
        $value2 = $scrollProp();
        $value3 = $scrollProp();

        $this->assertEquals(1, $callCount, 'Scroll prop value callback should only be executed once');

        $this->assertEquals($value1, $value2);
        $this->assertEquals($value2, $value3);
        $this->assertEquals(['item1', 'item2', 'item3'], $value1);
    }

    public function testStringFunctionNamesAreNotInvoked(): void
    {
        $scrollProp = new ScrollProp('date');

        $this->assertSame('date', $scrollProp());
    }

    public function testDeferredScrollPropIsExcludedFromInitialRequest(): void
    {
        $request = $this->makeRequest('/users');

        $this->pushRequest($request);
        $this->inertia()->version('123');

        $response = $this->renderFor($request, 'Users/Index', [
            'users' => new ScrollProp(fn () => Users::page(), 'data', new ScrollMetadata())->defer(),
        ]);
        $page = $this->getPage($response);

        $this->assertArrayNotHasKey('users', $page['props']);
        $this->assertSame(['default' => ['users']], $page['deferredProps']);
        $this->assertArrayNotHasKey('scrollProps', $page);
    }

    public function testDeferredScrollPropIsResolvedOnPartialRequest(): void
    {
        $request = $this->makeRequest('/users', headers: [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'Users/Index',
            'X-Inertia-Partial-Data' => 'users',
        ]);

        $this->pushRequest($request);
        $this->inertia()->version('123');

        $response = $this->renderFor($request, 'Users/Index', [
            'users' => new ScrollProp(fn () => Users::page(), 'data', new ScrollMetadata())->defer(),
        ]);
        $page = $this->getPage($response);

        $this->assertArrayHasKey('users', $page['props']);
        $this->assertCount(15, $page['props']['users']['data']);
        $this->assertArrayHasKey('scrollProps', $page);
        $this->assertEquals('page', $page['scrollProps']['users']['pageName']);
        $this->assertContains('users.data', $page['mergeProps']);
    }

    public function testDeferredScrollPropCanHaveCustomGroup(): void
    {
        $request = $this->makeRequest('/users');

        $this->pushRequest($request);
        $this->inertia()->version('123');

        $response = $this->renderFor($request, 'Users/Index', [
            'users' => new ScrollProp(fn () => Users::page(), 'data', new ScrollMetadata())->defer('custom-group'),
        ]);
        $page = $this->getPage($response);

        $this->assertArrayNotHasKey('users', $page['props']);
        $this->assertSame(['custom-group' => ['users']], $page['deferredProps']);
    }
}
