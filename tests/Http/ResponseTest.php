<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\Http;

use EvoMark\InertiaSymfonyBundle\Interfaces\ProvidesInertiaPropertiesInterface;
use EvoMark\InertiaSymfonyBundle\Interfaces\ProvidesScrollMetadataInterface;
use EvoMark\InertiaSymfonyBundle\PageProps\AlwaysProp;
use EvoMark\InertiaSymfonyBundle\PageProps\DeferProp;
use EvoMark\InertiaSymfonyBundle\PageProps\MergeProp;
use EvoMark\InertiaSymfonyBundle\PageProps\OptionalProp;
use EvoMark\InertiaSymfonyBundle\PageProps\ScrollProp;
use EvoMark\InertiaSymfonyBundle\Support\RenderContext;
use EvoMark\InertiaSymfonyBundle\Tests\Stubs\FakeResource;
use EvoMark\InertiaSymfonyBundle\Tests\Stubs\MergeWithSharedProp;
use EvoMark\InertiaSymfonyBundle\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function Symfony\Component\Clock\now;

/**
 * Building Inertia responses: JSON and full page loads, props, partial reloads,
 * prop types, metadata and URLs. Responses come from the Inertia service via
 * {@see self::respond()}.
 */
final class ResponseTest extends TestCase
{
    use ClockSensitiveTrait;

    protected function tearDown(): void
    {
        Request::setTrustedProxies([], -1);

        parent::tearDown();
    }

    /**
     * Render $component with $props for $request, at the given asset version.
     *
     * @param array<array-key, mixed> $props
     */
    private function respond(Request $request, string $component, array $props = [], string $version = '123'): Response
    {
        $this->pushRequest($request);
        $this->inertia()->version($version);

        return $this->inertia()->render($component, $props)->toResponse($request);
    }

    private static function scrollMetadata(): ProvidesScrollMetadataInterface
    {
        return new class implements ProvidesScrollMetadataInterface {
            public function getPageName(): string
            {
                return 'page';
            }

            public function getPreviousPage(): ?int
            {
                return null;
            }

            public function getNextPage(): int
            {
                return 2;
            }

            public function getCurrentPage(): int
            {
                return 1;
            }
        };
    }

    public function testServerResponse(): void
    {
        $request = $this->makeRequest('/user/123');

        $user = ['name' => 'Jonathan'];
        $response = $this->respond($request, 'User/Edit', ['user' => $user]);
        $page = $this->getPage($response);

        $this->assertIsInertiaView($response);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertArrayNotHasKey('clearHistory', $page);
        $this->assertArrayNotHasKey('encryptHistory', $page);
        $this->assertStringContainsString('<script data-page="app" type="application/json">{"component":"User\/Edit","props":{"user":{"name":"Jonathan"}},"url":"\/user\/123","version":"123"}</script><div id="app"></div>', (string) $response->getContent());
    }

    public function testServerResponseWithDeferredProp(): void
    {
        $request = $this->makeRequest('/user/123');

        $user = ['name' => 'Jonathan'];
        $response = $this->respond($request, 'User/Edit', [
            'user' => $user,
            'foo' => new DeferProp(function () {
                return 'bar';
            }, 'default'),
        ]);
        $page = $this->getPage($response);

        $this->assertIsInertiaView($response);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame([
            'default' => ['foo'],
        ], $page['deferredProps']);
        $this->assertArrayNotHasKey('clearHistory', $page);
        $this->assertArrayNotHasKey('encryptHistory', $page);
        $this->assertStringContainsString('<script data-page="app" type="application/json">{"component":"User\/Edit","props":{"user":{"name":"Jonathan"}},"url":"\/user\/123","version":"123","deferredProps":{"default":["foo"]}}</script><div id="app"></div>', (string) $response->getContent());
    }

    public function testServerResponseWithDeferredPropAndMultipleGroups(): void
    {
        $request = $this->makeRequest('/user/123');

        $user = ['name' => 'Jonathan'];
        $response = $this->respond($request, 'User/Edit', [
            'user' => $user,
            'foo' => new DeferProp(function () {
                return 'foo value';
            }, 'default'),
            'bar' => new DeferProp(function () {
                return 'bar value';
            }, 'default'),
            'baz' => new DeferProp(function () {
                return 'baz value';
            }, 'custom'),
        ]);
        $page = $this->getPage($response);

        $this->assertIsInertiaView($response);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame([
            'default' => ['foo', 'bar'],
            'custom' => ['baz'],
        ], $page['deferredProps']);
        $this->assertArrayNotHasKey('clearHistory', $page);
        $this->assertArrayNotHasKey('encryptHistory', $page);
        $this->assertStringContainsString('<script data-page="app" type="application/json">{"component":"User\/Edit","props":{"user":{"name":"Jonathan"}},"url":"\/user\/123","version":"123","deferredProps":{"default":["foo","bar"],"custom":["baz"]}}</script><div id="app"></div>', (string) $response->getContent());
    }

    /**
     * @return array<string, array{0: bool}>
     */
    public static function resetUsersProp(): array
    {
        return [
            'no reset' => [false],
            'with reset' => [true],
        ];
    }

    #[DataProvider('resetUsersProp')]
    public function testServerResponseWithScrollProps(bool $resetUsersProp): void
    {
        $request = $this->makeRequest('/user/123');

        if ($resetUsersProp) {
            $request->headers->add(['X-Inertia-Reset' => 'users']);
        }

        $response = $this->respond($request, 'User/Index', [
            'users' => new ScrollProp(['data' => [['id' => 1]]], 'data', self::scrollMetadata()),
        ]);
        $page = $this->getPage($response);

        $this->assertIsInertiaView($response);

        $this->assertSame('User/Index', $page['component']);
        $this->assertSame(['data' => [['id' => 1]]], $page['props']['users']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame([
            'users' => [
                'pageName' => 'page',
                'previousPage' => null,
                'nextPage' => 2,
                'currentPage' => 1,
                'reset' => $resetUsersProp,
            ],
        ], $page['scrollProps']);
    }

    public function testServerResponseWithMergeProps(): void
    {
        $request = $this->makeRequest('/user/123');

        $user = ['name' => 'Jonathan'];
        $response = $this->respond($request, 'User/Edit', [
            'user' => $user,
            'foo' => new MergeProp('foo value'),
            'bar' => new MergeProp('bar value'),
        ]);
        $page = $this->getPage($response);

        $this->assertIsInertiaView($response);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame([
            'foo',
            'bar',
        ], $page['mergeProps']);
        $this->assertArrayNotHasKey('clearHistory', $page);
        $this->assertArrayNotHasKey('encryptHistory', $page);
        $this->assertStringContainsString('<script data-page="app" type="application/json">{"component":"User\/Edit","props":{"user":{"name":"Jonathan"},"foo":"foo value","bar":"bar value"},"url":"\/user\/123","version":"123","mergeProps":["foo","bar"]}</script><div id="app"></div>', (string) $response->getContent());
    }

    public function testServerResponseWithMergePropsThatShouldPrepend(): void
    {
        $request = $this->makeRequest('/user/123');

        $user = ['name' => 'Jonathan'];
        $response = $this->respond($request, 'User/Edit', [
            'user' => $user,
            'foo' => new MergeProp('foo value')->prepend(),
            'bar' => new MergeProp('bar value'),
        ]);
        $page = $this->getPage($response);

        $this->assertIsInertiaView($response);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame(['bar'], $page['mergeProps']);
        $this->assertSame(['foo'], $page['prependProps']);
        $this->assertArrayNotHasKey('clearHistory', $page);
        $this->assertArrayNotHasKey('encryptHistory', $page);
        $this->assertStringContainsString('<script data-page="app" type="application/json">{"component":"User\/Edit","props":{"user":{"name":"Jonathan"},"foo":"foo value","bar":"bar value"},"url":"\/user\/123","version":"123","mergeProps":["bar"],"prependProps":["foo"]}</script><div id="app"></div>', (string) $response->getContent());
    }

    public function testServerResponseWithMergePropsThatHasNestedPathsToAppendAndPrepend(): void
    {
        $request = $this->makeRequest('/user/123');

        $user = ['name' => 'Jonathan'];
        $response = $this->respond($request, 'User/Edit', [
            'user' => $user,
            'foo' => new MergeProp(['data' => [['id' => 1], ['id' => 2]]])->append('data'),
            'bar' => new MergeProp(['data' => ['items' => [['uuid' => 1], ['uuid' => 2]]]])->prepend('data.items'),
        ]);
        $page = $this->getPage($response);

        $this->assertIsInertiaView($response);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame(['foo.data'], $page['mergeProps']);
        $this->assertSame(['bar.data.items'], $page['prependProps']);
        $this->assertArrayNotHasKey('matchPropsOn', $page);
        $this->assertArrayNotHasKey('clearHistory', $page);
        $this->assertArrayNotHasKey('encryptHistory', $page);
        $this->assertStringContainsString('<script data-page="app" type="application/json">{"component":"User\/Edit","props":{"user":{"name":"Jonathan"},"foo":{"data":[{"id":1},{"id":2}]},"bar":{"data":{"items":[{"uuid":1},{"uuid":2}]}}},"url":"\/user\/123","version":"123","mergeProps":["foo.data"],"prependProps":["bar.data.items"]}</script><div id="app"></div>', (string) $response->getContent());
    }

    public function testServerResponseWithMergePropsThatHasNestedPathsToAppendAndPrependWithMatchOnStrategies(): void
    {
        $request = $this->makeRequest('/user/123');

        $user = ['name' => 'Jonathan'];
        $response = $this->respond($request, 'User/Edit', [
            'user' => $user,
            'foo' => new MergeProp(['data' => [['id' => 1], ['id' => 2]]])->append('data', 'id'),
            'bar' => new MergeProp(['data' => ['items' => [['uuid' => 1], ['uuid' => 2]]]])->prepend('data.items', 'uuid'),
        ]);
        $page = $this->getPage($response);

        $this->assertIsInertiaView($response);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame(['foo.data'], $page['mergeProps']);
        $this->assertSame(['bar.data.items'], $page['prependProps']);
        $this->assertSame(['foo.data.id', 'bar.data.items.uuid'], $page['matchPropsOn']);
        $this->assertArrayNotHasKey('clearHistory', $page);
        $this->assertArrayNotHasKey('encryptHistory', $page);
        $this->assertStringContainsString('<script data-page="app" type="application/json">{"component":"User\/Edit","props":{"user":{"name":"Jonathan"},"foo":{"data":[{"id":1},{"id":2}]},"bar":{"data":{"items":[{"uuid":1},{"uuid":2}]}}},"url":"\/user\/123","version":"123","mergeProps":["foo.data"],"prependProps":["bar.data.items"],"matchPropsOn":["foo.data.id","bar.data.items.uuid"]}</script><div id="app"></div>', (string) $response->getContent());
    }

    public function testServerResponseWithDeepMergeProps(): void
    {
        $request = $this->makeRequest('/user/123');

        $user = ['name' => 'Jonathan'];
        $response = $this->respond($request, 'User/Edit', [
            'user' => $user,
            'foo' => new MergeProp('foo value')->deepMerge(),
            'bar' => new MergeProp('bar value')->deepMerge(),
        ]);
        $page = $this->getPage($response);

        $this->assertIsInertiaView($response);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame([
            'foo',
            'bar',
        ], $page['deepMergeProps']);
        $this->assertArrayNotHasKey('clearHistory', $page);
        $this->assertArrayNotHasKey('encryptHistory', $page);
        $this->assertStringContainsString('<script data-page="app" type="application/json">{"component":"User\/Edit","props":{"user":{"name":"Jonathan"},"foo":"foo value","bar":"bar value"},"url":"\/user\/123","version":"123","deepMergeProps":["foo","bar"]}</script><div id="app"></div>', (string) $response->getContent());
    }

    public function testServerResponseWithMatchOnProps(): void
    {
        $request = $this->makeRequest('/user/123');

        $user = ['name' => 'Jonathan'];
        $response = $this->respond($request, 'User/Edit', [
            'user' => $user,
            'foo' => new MergeProp('foo value')->matchOn('foo-key')->deepMerge(),
            'bar' => new MergeProp('bar value')->matchOn('bar-key')->deepMerge(),
        ]);
        $page = $this->getPage($response);

        $this->assertIsInertiaView($response);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame([
            'foo',
            'bar',
        ], $page['deepMergeProps']);

        $this->assertSame([
            'foo.foo-key',
            'bar.bar-key',
        ], $page['matchPropsOn']);
        $this->assertArrayNotHasKey('clearHistory', $page);
        $this->assertArrayNotHasKey('encryptHistory', $page);
        $this->assertStringContainsString('<script data-page="app" type="application/json">{"component":"User\/Edit","props":{"user":{"name":"Jonathan"},"foo":"foo value","bar":"bar value"},"url":"\/user\/123","version":"123","deepMergeProps":["foo","bar"],"matchPropsOn":["foo.foo-key","bar.bar-key"]}</script><div id="app"></div>', (string) $response->getContent());
    }

    public function testServerResponseWithDeferAndMergeProps(): void
    {
        $request = $this->makeRequest('/user/123');

        $user = ['name' => 'Jonathan'];
        $response = $this->respond($request, 'User/Edit', [
            'user' => $user,
            'foo' => new DeferProp(function () {
                return 'foo value';
            }, 'default')->merge(),
            'bar' => new MergeProp('bar value'),
        ]);
        $page = $this->getPage($response);

        $this->assertIsInertiaView($response);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame([
            'default' => ['foo'],
        ], $page['deferredProps']);
        $this->assertSame([
            'foo',
            'bar',
        ], $page['mergeProps']);
        $this->assertArrayNotHasKey('clearHistory', $page);
        $this->assertArrayNotHasKey('encryptHistory', $page);
        $this->assertStringContainsString('<script data-page="app" type="application/json">{"component":"User\/Edit","props":{"user":{"name":"Jonathan"},"bar":"bar value"},"url":"\/user\/123","version":"123","mergeProps":["foo","bar"],"deferredProps":{"default":["foo"]}}</script><div id="app"></div>', (string) $response->getContent());
    }

    public function testServerResponseWithDeferAndDeepMergeProps(): void
    {
        $request = $this->makeRequest('/user/123');

        $user = ['name' => 'Jonathan'];
        $response = $this->respond($request, 'User/Edit', [
            'user' => $user,
            'foo' => new DeferProp(function () {
                return 'foo value';
            }, 'default')->deepMerge(),
            'bar' => new MergeProp('bar value')->deepMerge(),
        ]);
        $page = $this->getPage($response);

        $this->assertIsInertiaView($response);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame([
            'default' => ['foo'],
        ], $page['deferredProps']);
        $this->assertSame([
            'foo',
            'bar',
        ], $page['deepMergeProps']);
        $this->assertArrayNotHasKey('clearHistory', $page);
        $this->assertArrayNotHasKey('encryptHistory', $page);
        $this->assertStringContainsString('<script data-page="app" type="application/json">{"component":"User\/Edit","props":{"user":{"name":"Jonathan"},"bar":"bar value"},"url":"\/user\/123","version":"123","deepMergeProps":["foo","bar"],"deferredProps":{"default":["foo"]}}</script><div id="app"></div>', (string) $response->getContent());
    }

    public function testExcludeMergePropsFromPartialOnlyResponse(): void
    {
        $request = $this->makeRequest('/user/123', headers: [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'User/Edit',
            'X-Inertia-Partial-Data' => 'user',
        ]);

        $user = ['name' => 'Jonathan'];
        $response = $this->respond($request, 'User/Edit', [
            'user' => $user,
            'foo' => new MergeProp('foo value'),
            'bar' => new MergeProp('bar value'),
        ]);
        $page = $this->getPage($response);
        $props = $page['props'];

        $this->assertIsInertiaJson($response);

        $this->assertSame('Jonathan', $props['user']['name']);
        $this->assertArrayNotHasKey('foo', $props);
        $this->assertArrayNotHasKey('bar', $props);
        $this->assertArrayNotHasKey('mergeProps', $page);
    }

    public function testExcludeMergePropsFromPartialExceptResponse(): void
    {
        $request = $this->makeRequest('/user/123', headers: [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'User/Edit',
            'X-Inertia-Partial-Except' => 'foo',
        ]);

        $user = ['name' => 'Jonathan'];
        $response = $this->respond($request, 'User/Edit', [
            'user' => $user,
            'foo' => new MergeProp('foo value'),
            'bar' => new MergeProp('bar value'),
        ]);
        $page = $this->getPage($response);
        $props = $page['props'];

        $this->assertIsInertiaJson($response);

        $this->assertSame('Jonathan', $props['user']['name']);
        $this->assertArrayNotHasKey('foo', $props);
        $this->assertArrayHasKey('bar', $props);
        $this->assertSame(['bar'], $page['mergeProps']);
    }

    public function testExcludeMergePropsWhenPassedInResetHeader(): void
    {
        $request = $this->makeRequest('/user/123', headers: [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'User/Edit',
            'X-Inertia-Partial-Data' => 'foo',
            'X-Inertia-Reset' => 'foo',
        ]);

        $user = ['name' => 'Jonathan'];
        $response = $this->respond($request, 'User/Edit', [
            'user' => $user,
            'foo' => new MergeProp('foo value'),
            'bar' => new MergeProp('bar value'),
        ]);
        $page = $this->getPage($response);
        $props = $page['props'];

        $this->assertIsInertiaJson($response);
        $this->assertSame('foo value', $props['foo']);
        $this->assertArrayNotHasKey('bar', $props);
        $this->assertArrayNotHasKey('mergeProps', $page);
    }

    public function testXhrResponse(): void
    {
        $request = $this->makeRequest('/user/123', headers: ['X-Inertia' => 'true']);

        $user = (object) ['name' => 'Jonathan'];
        $response = $this->respond($request, 'User/Edit', ['user' => $user]);
        $page = $this->getPage($response);

        $this->assertIsInertiaJson($response);
        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
    }

    public function testXhrResponseWithDeferredPropsIncludesDeferredMetadata(): void
    {
        $request = $this->makeRequest('/user/123', headers: ['X-Inertia' => 'true']);

        $response = $this->respond($request, 'User/Edit', [
            'user' => ['name' => 'Jonathan'],
            'results' => new DeferProp(fn () => ['data' => ['item1', 'item2']], 'default'),
        ]);
        $page = $this->getPage($response);

        $this->assertIsInertiaJson($response);
        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertArrayNotHasKey('results', $page['props']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame(['default' => ['results']], $page['deferredProps']);
    }

    public function testResourceResponse(): void
    {
        $request = $this->makeRequest('/user/123', headers: ['X-Inertia' => 'true']);

        $resource = new FakeResource(['name' => 'Jonathan']);

        $response = $this->respond($request, 'User/Edit', ['user' => $resource]);
        $page = $this->getPage($response);

        $this->assertIsInertiaJson($response);
        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
    }

    public function testOptionalCallableResourceResponse(): void
    {
        $request = $this->makeRequest('/users', headers: ['X-Inertia' => 'true']);

        $response = $this->respond($request, 'User/Index', [
            'users' => fn () => [['name' => 'Jonathan']],
            'organizations' => fn () => [['name' => 'Inertia']],
        ]);
        $page = $this->getPage($response);

        $this->assertIsInertiaJson($response);
        $this->assertSame('User/Index', $page['component']);
        $this->assertSame('/users', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame([['name' => 'Jonathan']], $page['props']['users']);
        $this->assertSame([['name' => 'Inertia']], $page['props']['organizations']);
    }

    public function testOptionalCallableResourcePartialResponse(): void
    {
        $request = $this->makeRequest('/users', headers: [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Data' => 'users',
            'X-Inertia-Partial-Component' => 'User/Index',
        ]);

        $response = $this->respond($request, 'User/Index', [
            'users' => fn () => [['name' => 'Jonathan']],
            'organizations' => fn () => [['name' => 'Inertia']],
        ]);
        $page = $this->getPage($response);

        $this->assertIsInertiaJson($response);
        $this->assertSame('User/Index', $page['component']);
        $this->assertSame('/users', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertArrayNotHasKey('organizations', $page['props']);
        $this->assertSame([['name' => 'Jonathan']], $page['props']['users']);
    }

    public function testJsonSerializablePropResponse(): void
    {
        $request = $this->makeRequest('/user/123', headers: ['X-Inertia' => 'true']);

        $resource = FakeResource::make(['name' => 'Jonathan']);

        $response = $this->respond($request, 'User/Edit', ['user' => $resource]);
        $page = $this->getPage($response);

        $this->assertIsInertiaJson($response);
        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
    }

    public function testXhrPartialResponse(): void
    {
        $request = $this->makeRequest('/user/123', headers: [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'User/Edit',
            'X-Inertia-Partial-Data' => 'partial',
        ]);

        $user = (object) ['name' => 'Jonathan'];
        $response = $this->respond($request, 'User/Edit', ['user' => $user, 'partial' => 'partial-data']);
        $page = $this->getPage($response);
        $props = $page['props'];

        $this->assertIsInertiaJson($response);
        $this->assertSame('User/Edit', $page['component']);
        $this->assertArrayNotHasKey('user', $props);
        $this->assertCount(1, $props);
        $this->assertSame('partial-data', $props['partial']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
    }

    public function testExcludePropsFromPartialResponse(): void
    {
        $request = $this->makeRequest('/user/123', headers: [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'User/Edit',
            'X-Inertia-Partial-Except' => 'user',
        ]);

        $user = (object) ['name' => 'Jonathan'];
        $response = $this->respond($request, 'User/Edit', ['user' => $user, 'partial' => 'partial-data']);
        $page = $this->getPage($response);
        $props = $page['props'];

        $this->assertIsInertiaJson($response);
        $this->assertSame('User/Edit', $page['component']);
        $this->assertArrayNotHasKey('user', $props);
        $this->assertCount(1, $props);
        $this->assertSame('partial-data', $props['partial']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
    }

    public function testNestedClosuresAreResolved(): void
    {
        $request = $this->makeRequest('/user/123');

        $response = $this->respond($request, 'User/Edit', [
            'auth' => [
                'user' => fn () => ['name' => 'Jonathan'],
                'token' => 'value',
            ],
        ]);
        $page = $this->getPage($response);

        $this->assertSame('Jonathan', $page['props']['auth']['user']['name']);
        $this->assertSame('value', $page['props']['auth']['token']);
    }

    public function testDoubleNestedClosuresAreResolved(): void
    {
        $request = $this->makeRequest('/user/123');

        $response = $this->respond($request, 'User/Edit', [
            'auth' => fn () => [
                'user' => fn () => ['name' => 'Jonathan'],
                'token' => 'value',
            ],
        ]);
        $page = $this->getPage($response);

        $this->assertSame('Jonathan', $page['props']['auth']['user']['name']);
        $this->assertSame('value', $page['props']['auth']['token']);
    }

    public function testNestedOptionalPropInsideClosureIsExcluded(): void
    {
        $request = $this->makeRequest('/user/123');
        $this->pushRequest($request);

        $response = $this->respond($request, 'User/Edit', [
            'auth' => fn () => [
                'user' => ['name' => 'Jonathan'],
                'pending' => $this->inertia()->optional(fn () => 'secret'),
            ],
        ]);
        $page = $this->getPage($response);

        $this->assertSame('Jonathan', $page['props']['auth']['user']['name']);
        $this->assertArrayNotHasKey('pending', $page['props']['auth']);
    }

    public function testNestedPartialProps(): void
    {
        $request = $this->makeRequest('/user/123', headers: [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'User/Edit',
            'X-Inertia-Partial-Data' => 'auth.user,auth.refresh_token',
        ]);

        $props = [
            'auth' => [
                'user' => new OptionalProp(function () {
                    return [
                        'name' => 'Jonathan Reinink',
                        'email' => 'jonathan@example.com',
                    ];
                }),
                'refresh_token' => 'value',
                'token' => 'value',
            ],
            'shared' => [
                'flash' => 'value',
            ],
        ];

        $response = $this->respond($request, 'User/Edit', $props, '');
        $page = $this->getPage($response);

        $this->assertArrayNotHasKey('shared', $page['props']);
        $this->assertArrayNotHasKey('token', $page['props']['auth']);
        $this->assertSame('Jonathan Reinink', $page['props']['auth']['user']['name']);
        $this->assertSame('jonathan@example.com', $page['props']['auth']['user']['email']);
        $this->assertSame('value', $page['props']['auth']['refresh_token']);
    }

    public function testExcludeNestedPropsFromPartialResponse(): void
    {
        $request = $this->makeRequest('/user/123', headers: [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'User/Edit',
            'X-Inertia-Partial-Data' => 'auth',
            'X-Inertia-Partial-Except' => 'auth.user',
        ]);

        $props = [
            'auth' => [
                'user' => new OptionalProp(function () {
                    return [
                        'name' => 'Jonathan Reinink',
                        'email' => 'jonathan@example.com',
                    ];
                }),
                'refresh_token' => 'value',
            ],
            'shared' => [
                'flash' => 'value',
            ],
        ];

        $response = $this->respond($request, 'User/Edit', $props, '');
        $page = $this->getPage($response);

        $this->assertArrayNotHasKey('user', $page['props']['auth']);
        $this->assertArrayNotHasKey('shared', $page['props']);
        $this->assertSame('value', $page['props']['auth']['refresh_token']);
    }

    public function testOptionalPropsAreNotIncludedByDefault(): void
    {
        $request = $this->makeRequest('/users', headers: ['X-Inertia' => 'true']);

        $optionalProp = new OptionalProp(function () {
            return 'An optional value';
        });

        $response = $this->respond($request, 'Users', ['users' => [], 'optional' => $optionalProp]);
        $page = $this->getPage($response);

        $this->assertSame([], $page['props']['users']);
        $this->assertArrayNotHasKey('optional', $page['props']);
    }

    public function testOptionalPropsAreIncludedInPartialReload(): void
    {
        $request = $this->makeRequest('/users', headers: [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'Users',
            'X-Inertia-Partial-Data' => 'optional',
        ]);

        $optionalProp = new OptionalProp(function () {
            return 'An optional value';
        });

        $response = $this->respond($request, 'Users', ['users' => [], 'optional' => $optionalProp]);
        $page = $this->getPage($response);

        $this->assertArrayNotHasKey('users', $page['props']);
        $this->assertSame('An optional value', $page['props']['optional']);
    }

    public function testDeferJsonSerializablePropsAreResolvedInPartialReload(): void
    {
        $request = $this->makeRequest('/users', headers: [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'Users',
            'X-Inertia-Partial-Data' => 'defer',
        ]);

        $deferProp = new DeferProp(function () {
            return new class implements \JsonSerializable {
                public function jsonSerialize(): array
                {
                    return ['foo' => 'bar'];
                }
            };
        });

        $response = $this->respond($request, 'Users', ['users' => [], 'defer' => $deferProp]);
        $page = $this->getPage($response);

        $this->assertArrayNotHasKey('users', $page['props']);
        $this->assertSame(['foo' => 'bar'], $page['props']['defer']);
    }

    public function testAlwaysPropsAreIncludedOnPartialReload(): void
    {
        $request = $this->makeRequest('/user/123', headers: [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'User/Edit',
            'X-Inertia-Partial-Data' => 'data',
        ]);

        $props = [
            'user' => new OptionalProp(function () {
                return [
                    'name' => 'Jonathan Reinink',
                    'email' => 'jonathan@example.com',
                ];
            }),
            'data' => [
                'name' => 'Taylor Otwell',
            ],
            'errors' => new AlwaysProp(function () {
                return [
                    'name' => 'The email field is required.',
                ];
            }),
        ];

        $response = $this->respond($request, 'User/Edit', $props);
        $page = $this->getPage($response);

        $this->assertSame('The email field is required.', $page['props']['errors']['name']);
        $this->assertSame('Taylor Otwell', $page['props']['data']['name']);
        $this->assertArrayNotHasKey('user', $page['props']);
    }

    public function testStringFunctionNamesAreNotInvokedAsCallables(): void
    {
        $request = $this->makeRequest('/user/123');

        $response = $this->respond($request, 'User/Edit', [
            'always' => new AlwaysProp('date'),
            'merge' => new MergeProp('trim'),
        ]);
        $page = $this->getPage($response);

        $this->assertSame('date', $page['props']['always']);
        $this->assertSame('trim', $page['props']['merge']);
    }

    public function testPropsProviderObjects(): void
    {
        $request = $this->makeRequest('/user/123');

        $response = $this->respond($request, 'User/Edit', [
            'foo' => 'bar',
            new class implements ProvidesInertiaPropertiesInterface {
                /**
                 * @return iterable<string, string>
                 */
                public function toInertiaProperties(RenderContext $context): iterable
                {
                    return [
                        'baz' => 'qux',
                    ];
                }
            },
            'quux' => 'corge',
        ]);
        $page = $this->getPage($response);

        $this->assertSame('bar', $page['props']['foo']);
        $this->assertSame('qux', $page['props']['baz']);
        $this->assertSame('corge', $page['props']['quux']);
    }

    public function testInertiaResponseTypeProp(): void
    {
        $request = $this->makeRequest('/user/123');
        $this->pushRequest($request);

        $this->inertia()->share('items', ['foo']);
        $this->inertia()->share('deep.foo.bar', ['foo']);

        $response = $this->respond($request, 'User/Edit', [
            'items' => new MergeWithSharedProp($this->inertia(), ['bar']),
            'deep' => [
                'foo' => [
                    'bar' => new MergeWithSharedProp($this->inertia(), ['baz']),
                ],
            ],
        ]);
        $page = $this->getPage($response);

        $this->assertSame(['foo', 'bar'], $page['props']['items']);
        $this->assertSame(['foo', 'baz'], $page['props']['deep']['foo']['bar']);
    }

    public function testTopLevelDotPropsGetUnpacked(): void
    {
        $props = [
            'auth' => [
                'user' => [
                    'name' => 'Jonathan Reinink',
                ],
            ],
            'auth.user.can' => [
                'do.stuff' => true,
            ],
            'product' => ['name' => 'My example product'],
        ];

        $request = $this->makeRequest('/products/123', headers: ['X-Inertia' => 'true']);

        $response = $this->respond($request, 'User/Edit', $props);
        $page = $this->getPage($response);

        $user = $page['props']['auth']['user'];
        $this->assertSame('Jonathan Reinink', $user['name']);
        $this->assertTrue($user['can']['do.stuff']);
        $this->assertArrayNotHasKey('auth.user.can', $page['props']);
    }

    public function testNestedDotPropsDoNotGetUnpacked(): void
    {
        $props = [
            'auth' => [
                'user.can' => [
                    'do.stuff' => true,
                ],
                'user' => [
                    'name' => 'Jonathan Reinink',
                ],
            ],
            'product' => ['name' => 'My example product'],
        ];

        $request = $this->makeRequest('/products/123', headers: ['X-Inertia' => 'true']);

        $response = $this->respond($request, 'User/Edit', $props);
        $page = $this->getPage($response);

        $auth = $page['props']['auth'];
        $this->assertSame('Jonathan Reinink', $auth['user']['name']);
        $this->assertTrue($auth['user.can']['do.stuff']);
        $this->assertArrayNotHasKey('can', $auth);
    }

    public function testPropsCanBeAddedUsingTheWithMethod(): void
    {
        $request = $this->makeRequest('/user/123');
        $this->pushRequest($request);
        $this->inertia()->version('123');

        $response = $this->inertia()->render('User/Edit');

        $response->with(['foo' => 'bar', 'baz' => 'qux'])
            ->with(['quux' => 'corge'])
            ->with(new class implements ProvidesInertiaPropertiesInterface {
                /**
                 * @return iterable<string, string>
                 */
                public function toInertiaProperties(RenderContext $context): iterable
                {
                    return ['grault' => 'garply'];
                }
            });

        $page = $this->getPage($response->toResponse($request));

        $this->assertSame('bar', $page['props']['foo']);
        $this->assertSame('qux', $page['props']['baz']);
        $this->assertSame('corge', $page['props']['quux']);
        $this->assertSame('garply', $page['props']['grault']);
    }

    public function testOncePropsAreAlwaysResolvedOnInitialPageLoad(): void
    {
        $request = $this->makeRequest('/user/123');
        $this->pushRequest($request);

        $response = $this->respond($request, 'User/Edit', ['foo' => $this->inertia()->once(fn () => 'bar')]);
        $page = $this->getPage($response);

        $this->assertIsInertiaView($response);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('bar', $page['props']['foo']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertArrayNotHasKey('clearHistory', $page);
        $this->assertArrayNotHasKey('encryptHistory', $page);
        $this->assertSame(['foo' => ['prop' => 'foo', 'expiresAt' => null]], $page['onceProps']);
        $this->assertStringContainsString('<script data-page="app" type="application/json">{"component":"User\/Edit","props":{"foo":"bar"},"url":"\/user\/123","version":"123","onceProps":{"foo":{"prop":"foo","expiresAt":null}}}</script><div id="app"></div>', (string) $response->getContent());
    }

    public function testFreshOncePropsAreIncludedOnInitialPageLoad(): void
    {
        $request = $this->makeRequest('/user/123');
        $this->pushRequest($request);

        $response = $this->respond($request, 'User/Edit', ['foo' => $this->inertia()->once(fn () => 'bar')->fresh()]);
        $page = $this->getPage($response);

        $this->assertIsInertiaView($response);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('bar', $page['props']['foo']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertArrayHasKey('onceProps', $page);
        $this->assertSame(['foo' => ['prop' => 'foo', 'expiresAt' => null]], $page['onceProps']);
    }

    public function testOncePropsAreResolvedWithACustomKeyAndTtlValue(): void
    {
        self::mockTime();

        $request = $this->makeRequest('/user/123', headers: ['X-Inertia' => 'true']);
        $this->pushRequest($request);

        $response = $this->respond($request, 'User/Edit', [
            'foo' => $this->inertia()->once(fn () => 'bar')->as('baz')->until(now()->modify('+1 minute')),
        ]);
        $page = $this->getPage($response);

        $this->assertIsInertiaJson($response);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('bar', $page['props']['foo']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame(['baz' => ['prop' => 'foo', 'expiresAt' => now()->modify('+1 minute')->getTimestamp() * 1000]], $page['onceProps']);
    }

    public function testOncePropsAreNotResolvedOnSubsequentRequestsWhenTheyAreInTheOncePropsHeader(): void
    {
        $request = $this->makeRequest('/user/123', headers: [
            'X-Inertia' => 'true',
            'X-Inertia-Except-Once-Props' => 'foo',
        ]);
        $this->pushRequest($request);

        $response = $this->respond($request, 'User/Edit', ['foo' => $this->inertia()->once(fn () => 'bar')]);
        $page = $this->getPage($response);

        $this->assertIsInertiaJson($response);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertArrayNotHasKey('foo', $page['props']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame(['foo' => ['prop' => 'foo', 'expiresAt' => null]], $page['onceProps']);
    }

    public function testOncePropsAreResolvedOnSubsequentRequestsWhenTheOncePropsHeaderIsMissing(): void
    {
        $request = $this->makeRequest('/user/123', headers: ['X-Inertia' => 'true']);
        $this->pushRequest($request);

        $response = $this->respond($request, 'User/Edit', ['foo' => $this->inertia()->once(fn () => 'bar')]);
        $page = $this->getPage($response);

        $this->assertIsInertiaJson($response);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('bar', $page['props']['foo']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame(['foo' => ['prop' => 'foo', 'expiresAt' => null]], $page['onceProps']);
    }

    public function testOncePropsAreResolvedOnSubsequentRequestsWhenTheyAreNotInTheOncePropsHeader(): void
    {
        $request = $this->makeRequest('/user/123', headers: [
            'X-Inertia' => 'true',
            'X-Inertia-Except-Once-Props' => 'baz',
        ]);
        $this->pushRequest($request);

        $response = $this->respond($request, 'User/Edit', ['foo' => $this->inertia()->once(fn () => 'bar')]);
        $page = $this->getPage($response);

        $this->assertIsInertiaJson($response);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('bar', $page['props']['foo']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame(['foo' => ['prop' => 'foo', 'expiresAt' => null]], $page['onceProps']);
    }

    public function testOncePropsAreResolvedOnPartialRequestsWithoutOnlyOrExcept(): void
    {
        $request = $this->makeRequest('/user/123', headers: [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'User/Edit',
            'X-Inertia-Partial-Data' => 'foo',
            'X-Inertia-Except-Once-Props' => 'foo',
        ]);
        $this->pushRequest($request);

        $response = $this->respond($request, 'User/Edit', ['foo' => $this->inertia()->once(fn () => 'bar')]);
        $page = $this->getPage($response);

        $this->assertIsInertiaJson($response);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('bar', $page['props']['foo']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame(['foo' => ['prop' => 'foo', 'expiresAt' => null]], $page['onceProps']);
    }

    public function testOncePropsAreResolvedOnPartialRequestsWhenIncludedInOnlyHeaders(): void
    {
        $request = $this->makeRequest('/user/123', headers: [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'User/Edit',
            'X-Inertia-Partial-Data' => 'foo',
            'X-Inertia-Except-Once-Props' => 'foo',
        ]);
        $this->pushRequest($request);

        $response = $this->respond($request, 'User/Edit', [
            'foo' => $this->inertia()->once(fn () => 'bar'),
            'baz' => $this->inertia()->once(fn () => 'qux'),
        ]);
        $page = $this->getPage($response);

        $this->assertIsInertiaJson($response);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('bar', $page['props']['foo']);
        $this->assertArrayNotHasKey('baz', $page['props']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame([
            'foo' => ['prop' => 'foo', 'expiresAt' => null],
        ], $page['onceProps']);
    }

    public function testOncePropsAreNotResolvedOnPartialRequestsWhenExcludedInExceptHeaders(): void
    {
        $request = $this->makeRequest('/user/123', headers: [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'User/Edit',
            'X-Inertia-Partial-Except' => 'foo',
            'X-Inertia-Except-Once-Props' => 'foo',
        ]);
        $this->pushRequest($request);

        $response = $this->respond($request, 'User/Edit', [
            'foo' => $this->inertia()->once(fn () => 'bar'),
            'baz' => $this->inertia()->once(fn () => 'qux'),
        ]);
        $page = $this->getPage($response);

        $this->assertIsInertiaJson($response);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertArrayNotHasKey('foo', $page['props']);
        $this->assertSame('qux', $page['props']['baz']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame([
            'baz' => ['prop' => 'baz', 'expiresAt' => null],
        ], $page['onceProps']);
    }

    public function testFreshPropsAreResolvedEvenWhenInExceptOncePropsHeader(): void
    {
        $request = $this->makeRequest('/user/123', headers: [
            'X-Inertia' => 'true',
            'X-Inertia-Except-Once-Props' => 'foo',
        ]);
        $this->pushRequest($request);

        $response = $this->respond($request, 'User/Edit', ['foo' => $this->inertia()->once(fn () => 'bar')->fresh()]);
        $page = $this->getPage($response);

        $this->assertIsInertiaJson($response);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('bar', $page['props']['foo']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame([
            'foo' => ['prop' => 'foo', 'expiresAt' => null],
        ], $page['onceProps']);
    }

    public function testFreshPropsAreNotExcludedWhileOncePropsAreExcluded(): void
    {
        $request = $this->makeRequest('/user/123', headers: [
            'X-Inertia' => 'true',
            'X-Inertia-Except-Once-Props' => 'foo,baz',
        ]);
        $this->pushRequest($request);

        $response = $this->respond($request, 'User/Edit', [
            'foo' => $this->inertia()->once(fn () => 'bar')->fresh(),
            'baz' => $this->inertia()->once(fn () => 'qux'),
        ]);
        $page = $this->getPage($response);

        $this->assertIsInertiaJson($response);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('bar', $page['props']['foo']);
        $this->assertArrayNotHasKey('baz', $page['props']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame([
            'foo' => ['prop' => 'foo', 'expiresAt' => null],
            'baz' => ['prop' => 'baz', 'expiresAt' => null],
        ], $page['onceProps']);
    }

    public function testDeferPropsThatAreOnceAndAlreadyLoadedAreExcluded(): void
    {
        $request = $this->makeRequest('/user/123', headers: [
            'X-Inertia' => 'true',
            'X-Inertia-Except-Once-Props' => 'defer',
        ]);
        $this->pushRequest($request);

        $response = $this->respond($request, 'User/Edit', [
            'defer' => $this->inertia()->defer(fn () => 'value')->once(),
        ]);
        $page = $this->getPage($response);

        $this->assertIsInertiaJson($response);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertArrayNotHasKey('defer', $page['props']);
        $this->assertArrayNotHasKey('deferredProps', $page);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertSame([
            'defer' => ['prop' => 'defer', 'expiresAt' => null],
        ], $page['onceProps']);
    }

    public function testDeferPropsThatAreOnceAndAlreadyLoadedNotExcludedWhenExplicitlyRequested(): void
    {
        $request = $this->makeRequest('/user/123', headers: [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'User/Edit',
            'X-Inertia-Partial-Data' => 'defer',
            'X-Inertia-Except-Once-Props' => 'defer',
        ]);
        $this->pushRequest($request);

        $response = $this->respond($request, 'User/Edit', [
            'defer' => $this->inertia()->defer(fn () => 'value')->once(),
        ]);
        $page = $this->getPage($response);

        $this->assertIsInertiaJson($response);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('value', $page['props']['defer']);
        $this->assertSame('/user/123', $page['url']);
        $this->assertSame('123', $page['version']);
        $this->assertArrayNotHasKey('deferredProps', $page);
        $this->assertSame([
            'defer' => ['prop' => 'defer', 'expiresAt' => null],
        ], $page['onceProps']);
    }

    public function testJsonSerializableWithInvalidKey(): void
    {
        $request = $this->makeRequest('/user/123', headers: ['X-Inertia' => 'true']);

        $resource = new FakeResource(["\x00*\x00_invalid_key" => 'for object']);

        $response = $this->respond($request, 'User/Edit', ['resource' => $resource]);
        $page = $this->getPage($response);

        $this->assertSame(
            ["\x00*\x00_invalid_key" => 'for object'],
            $page['props']['resource'],
        );
    }

    public function testThePageUrlIsPrefixedWithTheProxyPrefix(): void
    {
        Request::setTrustedProxies(['1.2.3.4'], Request::HEADER_X_FORWARDED_PREFIX);

        $request = $this->makeRequest('/user/123');
        $request->server->set('REMOTE_ADDR', '1.2.3.4');
        $request->headers->set('X_FORWARDED_PREFIX', '/sub/directory');

        $user = ['name' => 'Jonathan'];
        $response = $this->respond($request, 'User/Edit', ['user' => $user]);
        $page = $this->getPage($response);

        $this->assertIsInertiaView($response);

        $this->assertSame('/sub/directory/user/123', $page['url']);
    }

    public function testThePageUrlDoesntDoubleUp(): void
    {
        $request = Request::create('/subpath/product/123', 'GET', [], [], [], [
            'SCRIPT_FILENAME' => '/project/public/index.php',
            'SCRIPT_NAME' => '/subpath/index.php',
        ]);
        $request->headers->add(['X-Inertia' => 'true']);

        $response = $this->respond($request, 'Product/Show', [], '');
        $page = $this->getPage($response);

        $this->assertSame('/subpath/product/123', $page['url']);
    }

    public function testTrailingSlashesInAUrlArePreserved(): void
    {
        $request = $this->makeRequest('/users/', headers: ['X-Inertia' => 'true']);

        $response = $this->respond($request, 'User/Index', [], '');
        $page = $this->getPage($response);

        $this->assertSame('/users/', $page['url']);
    }

    public function testTrailingSlashesInAUrlWithQueryParametersArePreserved(): void
    {
        $request = $this->makeRequest('/users/?page=1&sort=name', headers: ['X-Inertia' => 'true']);

        $response = $this->respond($request, 'User/Index', [], '');
        $page = $this->getPage($response);

        $this->assertSame('/users/?page=1&sort=name', $page['url']);
    }

    public function testAUrlWithoutTrailingSlashIsResolvedCorrectly(): void
    {
        $request = $this->makeRequest('/users', headers: ['X-Inertia' => 'true']);

        $response = $this->respond($request, 'User/Index', [], '');
        $page = $this->getPage($response);

        $this->assertSame('/users', $page['url']);
    }

    public function testAUrlWithoutTrailingSlashAndQueryParametersIsResolvedCorrectly(): void
    {
        $request = $this->makeRequest('/users?page=1&sort=name', headers: ['X-Inertia' => 'true']);

        $response = $this->respond($request, 'User/Index', [], '');
        $page = $this->getPage($response);

        $this->assertSame('/users?page=1&sort=name', $page['url']);
    }

    public function testDeferredPropsFromProvidesInertiaPropertiesAreIncludedInDeferredPropsMetadata(): void
    {
        $request = $this->makeRequest('/user/123');

        $response = $this->respond($request, 'User/Edit', [
            'user' => ['name' => 'Jonathan'],
            new class implements ProvidesInertiaPropertiesInterface {
                public function toInertiaProperties(RenderContext $context): iterable
                {
                    return [
                        'foo' => new DeferProp(fn () => 'bar', 'default'),
                    ];
                }
            },
        ]);
        $page = $this->getPage($response);

        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertArrayNotHasKey('foo', $page['props']);
        $this->assertSame([
            'default' => ['foo'],
        ], $page['deferredProps']);
    }

    public function testDeferredPropsFromProvidesInertiaPropertiesWithMultipleGroups(): void
    {
        $request = $this->makeRequest('/user/123');

        $response = $this->respond($request, 'User/Edit', [
            'user' => ['name' => 'Jonathan'],
            new class implements ProvidesInertiaPropertiesInterface {
                public function toInertiaProperties(RenderContext $context): iterable
                {
                    return [
                        'foo' => new DeferProp(fn () => 'foo value', 'default'),
                        'bar' => new DeferProp(fn () => 'bar value', 'custom'),
                    ];
                }
            },
        ]);
        $page = $this->getPage($response);

        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertArrayNotHasKey('foo', $page['props']);
        $this->assertArrayNotHasKey('bar', $page['props']);
        $this->assertSame([
            'default' => ['foo'],
            'custom' => ['bar'],
        ], $page['deferredProps']);
    }

    public function testDeferredPropsFromProvidesInertiaPropertiesCanBeLoadedViaPartialRequest(): void
    {
        $request = $this->makeRequest('/user/123', headers: [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'User/Edit',
            'X-Inertia-Partial-Data' => 'foo',
        ]);

        $response = $this->respond($request, 'User/Edit', [
            'user' => ['name' => 'Jonathan'],
            new class implements ProvidesInertiaPropertiesInterface {
                public function toInertiaProperties(RenderContext $context): iterable
                {
                    return [
                        'foo' => new DeferProp(fn () => 'bar', 'default'),
                    ];
                }
            },
        ]);
        $page = $this->getPage($response);

        $this->assertSame('bar', $page['props']['foo']);
        $this->assertArrayNotHasKey('user', $page['props']);
    }

    public function testMergePropsFromProvidesInertiaPropertiesAreIncludedInMergePropsMetadata(): void
    {
        $request = $this->makeRequest('/user/123');

        $response = $this->respond($request, 'User/Edit', [
            'user' => ['name' => 'Jonathan'],
            new class implements ProvidesInertiaPropertiesInterface {
                public function toInertiaProperties(RenderContext $context): iterable
                {
                    return [
                        'foo' => new MergeProp('foo value'),
                    ];
                }
            },
        ]);
        $page = $this->getPage($response);

        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('foo value', $page['props']['foo']);
        $this->assertSame(['foo'], $page['mergeProps']);
    }

    public function testOncePropsFromProvidesInertiaPropertiesAreIncludedInOncePropsMetadata(): void
    {
        $request = $this->makeRequest('/user/123');
        $this->pushRequest($request);
        $once = $this->inertia()->once(fn () => 'bar');

        $response = $this->respond($request, 'User/Edit', [
            'user' => ['name' => 'Jonathan'],
            new class($once) implements ProvidesInertiaPropertiesInterface {
                public function __construct(private mixed $once)
                {
                }

                public function toInertiaProperties(RenderContext $context): iterable
                {
                    return [
                        'foo' => $this->once,
                    ];
                }
            },
        ]);
        $page = $this->getPage($response);

        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame('bar', $page['props']['foo']);
        $this->assertSame(['foo' => ['prop' => 'foo', 'expiresAt' => null]], $page['onceProps']);
    }

    public function testDeferredMergePropsFromProvidesInertiaPropertiesIncludeBothMetadata(): void
    {
        $request = $this->makeRequest('/user/123');

        $response = $this->respond($request, 'User/Edit', [
            'user' => ['name' => 'Jonathan'],
            new class implements ProvidesInertiaPropertiesInterface {
                public function toInertiaProperties(RenderContext $context): iterable
                {
                    return [
                        'foo' => new DeferProp(fn () => 'foo value', 'default')->merge(),
                    ];
                }
            },
        ]);
        $page = $this->getPage($response);

        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertArrayNotHasKey('foo', $page['props']);
        $this->assertSame([
            'default' => ['foo'],
        ], $page['deferredProps']);
        $this->assertSame(['foo'], $page['mergeProps']);
    }
}
