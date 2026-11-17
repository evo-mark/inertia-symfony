<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\Services;

use EvoMark\InertiaSymfonyBundle\Exceptions\ComponentNotFoundException;
use EvoMark\InertiaSymfonyBundle\Http\Response as InertiaResponse;
use EvoMark\InertiaSymfonyBundle\Interfaces\SsrGatewayInterface;
use EvoMark\InertiaSymfonyBundle\PageProps\AlwaysProp;
use EvoMark\InertiaSymfonyBundle\PageProps\DeferProp;
use EvoMark\InertiaSymfonyBundle\PageProps\MergeProp;
use EvoMark\InertiaSymfonyBundle\PageProps\OnceProp;
use EvoMark\InertiaSymfonyBundle\PageProps\OptionalProp;
use EvoMark\InertiaSymfonyBundle\PageProps\ScrollProp;
use EvoMark\InertiaSymfonyBundle\Ssr\HttpGateway;
use EvoMark\InertiaSymfonyBundle\Tests\Enums\IntBackedEnum;
use EvoMark\InertiaSymfonyBundle\Tests\Enums\StringBackedEnum;
use EvoMark\InertiaSymfonyBundle\Tests\Enums\UnitEnum;
use EvoMark\InertiaSymfonyBundle\Tests\Stubs\ExampleInertiaPropsProvider;
use EvoMark\InertiaSymfonyBundle\Tests\Stubs\FakeGateway;
use EvoMark\InertiaSymfonyBundle\Tests\Stubs\ScrollMetadata;
use EvoMark\InertiaSymfonyBundle\Tests\TestCase;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The {@see \EvoMark\InertiaSymfonyBundle\Interfaces\InertiaInterface} service.
 * Tests that need routing and the bundle's listeners go through
 * {@see TestCase::makeMockRequest()}.
 *
 * `sharedProps` lists start with `errors`, which ValidationErrorsListener shares
 * on every request.
 */
final class ResponseFactoryTest extends TestCase
{
    public function testLocationResponseForInertiaRequests(): void
    {
        $this->pushRequest($this->makeRequest(headers: ['X-Inertia' => 'true']));

        $response = $this->inertia()->location('https://inertiajs.com');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_CONFLICT, $response->getStatusCode());
        $this->assertEquals('https://inertiajs.com', $response->headers->get('X-Inertia-Location'));
    }

    public function testLocationResponseForNonInertiaRequests(): void
    {
        $this->pushRequest($this->makeRequest());

        $response = $this->inertia()->location('https://inertiajs.com');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertEquals('https://inertiajs.com', $response->headers->get('location'));
    }

    public function testLocationResponseForInertiaRequestsUsingRedirectResponse(): void
    {
        $this->pushRequest($this->makeRequest(headers: ['X-Inertia' => 'true']));

        $redirect = new RedirectResponse('https://inertiajs.com');
        $response = $this->inertia()->location($redirect);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(409, $response->getStatusCode());
        $this->assertEquals('https://inertiajs.com', $response->headers->get('X-Inertia-Location'));
    }

    public function testLocationResponseForNonInertiaRequestsUsingRedirectResponse(): void
    {
        $this->pushRequest($this->makeRequest());

        $redirect = new RedirectResponse('https://inertiajs.com');
        $response = $this->inertia()->location($redirect);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertEquals('https://inertiajs.com', $response->headers->get('location'));
    }

    public function testLocationRedirectsAreNotModified(): void
    {
        $this->pushRequest($this->makeRequest());

        $response = $this->inertia()->location('/foo');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertEquals('/foo', $response->headers->get('location'));
    }

    public function testTheVersionCanBeAClosure(): void
    {
        // AssetVersionListener compares versions before the controller runs, so the
        // version is set before the request.
        $this->assertSame('', $this->inertia()->getVersion());

        $this->inertia()->version(function () {
            return hash('xxh128', 'Inertia');
        });

        $response = $this->makeMockRequest(fn () => $this->inertia()->render('User/Edit'), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => 'f445bd0a2c393a5af14fc677f59980a9',
        ]);

        $this->assertTrue($response->isSuccessful());
        $this->assertArrayContains([
            'component' => 'User/Edit',
            'version' => 'f445bd0a2c393a5af14fc677f59980a9',
        ], $this->getPage($response));
    }

    public function testTheUrlCanBeResolvedWithACustomResolver(): void
    {
        $response = $this->makeMockRequest(function () {
            $this->inertia()->resolveUrlUsing(function (Request $request) {
                $this->assertInstanceOf(Request::class, $request);

                return '/my-custom-url';
            });

            return $this->inertia()->render('User/Edit');
        }, ['X-Inertia' => 'true']);

        $this->assertTrue($response->isSuccessful());
        $this->assertArrayContains([
            'component' => 'User/Edit',
            'url' => '/my-custom-url',
        ], $this->getPage($response));
    }

    public function testSharedDataCanBeSharedFromAnywhere(): void
    {
        $response = $this->makeMockRequest(function () {
            $this->inertia()->share('foo', 'bar');

            return $this->inertia()->render('User/Edit');
        }, ['X-Inertia' => 'true']);

        $this->assertTrue($response->isSuccessful());
        $this->assertArrayContains([
            'component' => 'User/Edit',
            'props' => [
                'foo' => 'bar',
            ],
        ], $this->getPage($response));
    }

    public function testDotPropsAreMergedFromShared(): void
    {
        $response = $this->makeMockRequest(function () {
            $this->inertia()->share('auth.user', [
                'name' => 'Jonathan',
            ]);

            return $this->inertia()->render('User/Edit', [
                'auth.user.can.create_group' => false,
            ]);
        }, ['X-Inertia' => 'true']);

        $this->assertTrue($response->isSuccessful());
        $this->assertArrayContains([
            'component' => 'User/Edit',
            'props' => [
                'auth' => [
                    'user' => [
                        'name' => 'Jonathan',
                        'can' => [
                            'create_group' => false,
                        ],
                    ],
                ],
            ],
        ], $this->getPage($response));
    }

    public function testDotPropsWithCallbacksAreMergedFromShared(): void
    {
        $response = $this->makeMockRequest(function () {
            $this->inertia()->share('auth.user', fn () => [
                'name' => 'Jonathan',
            ]);

            return $this->inertia()->render('User/Edit', [
                'auth.user.can.create_group' => false,
            ]);
        }, ['X-Inertia' => 'true']);

        $this->assertTrue($response->isSuccessful());
        $this->assertArrayContains([
            'component' => 'User/Edit',
            'props' => [
                'auth' => [
                    'user' => [
                        'name' => 'Jonathan',
                        'can' => [
                            'create_group' => false,
                        ],
                    ],
                ],
            ],
        ], $this->getPage($response));
    }

    public function testCanFlushSharedData(): void
    {
        $this->inertia()->share('foo', 'bar');
        $this->assertSame(['foo' => 'bar'], $this->inertia()->getShared());
        $this->inertia()->flushShared();
        $this->assertSame([], $this->inertia()->getShared());
    }

    public function testCanCreateDeferredProp(): void
    {
        $deferredProp = $this->inertia()->defer(function () {
            return 'A deferred value';
        });

        $this->assertInstanceOf(DeferProp::class, $deferredProp);
        $this->assertSame($deferredProp->group(), 'default');
    }

    public function testCanCreateDeferredPropWithCustomGroup(): void
    {
        $deferredProp = $this->inertia()->defer(function () {
            return 'A deferred value';
        }, 'foo');

        $this->assertInstanceOf(DeferProp::class, $deferredProp);
        $this->assertSame($deferredProp->group(), 'foo');
    }

    public function testCanCreateMergedProp(): void
    {
        $mergedProp = $this->inertia()->merge(function () {
            return 'A merged value';
        });

        $this->assertInstanceOf(MergeProp::class, $mergedProp);
    }

    public function testCanCreateDeepMergedProp(): void
    {
        $mergedProp = $this->inertia()->deepMerge(function () {
            return 'A merged value';
        });

        $this->assertInstanceOf(MergeProp::class, $mergedProp);
    }

    public function testCanCreateDeferredAndMergedProp(): void
    {
        $deferredProp = $this->inertia()->defer(function () {
            return 'A deferred + merged value';
        })->merge();

        $this->assertInstanceOf(DeferProp::class, $deferredProp);
    }

    public function testCanCreateDeferredAndDeepMergedProp(): void
    {
        $deferredProp = $this->inertia()->defer(function () {
            return 'A deferred + merged value';
        })->deepMerge();

        $this->assertInstanceOf(DeferProp::class, $deferredProp);
    }

    public function testCanCreateOptionalProp(): void
    {
        $optionalProp = $this->inertia()->optional(function () {
            return 'An optional value';
        });

        $this->assertInstanceOf(OptionalProp::class, $optionalProp);
    }

    public function testCanCreateScrollProp(): void
    {
        $data = ['item1', 'item2'];

        $scrollProp = $this->inertia()->scroll($data);

        $this->assertInstanceOf(ScrollProp::class, $scrollProp);
        $this->assertSame($data, $scrollProp());
    }

    public function testCanCreateScrollPropWithMetadataProvider(): void
    {
        $data = ['item1', 'item2'];
        $metadataProvider = new ScrollMetadata('custom', 1, 3, 2);

        $scrollProp = $this->inertia()->scroll($data, 'data', $metadataProvider);

        $this->assertInstanceOf(ScrollProp::class, $scrollProp);
        $this->assertSame($data, $scrollProp());
        $this->assertEquals([
            'pageName' => 'custom',
            'previousPage' => 1,
            'nextPage' => 3,
            'currentPage' => 2,
        ], $scrollProp->metadata());
    }

    public function testCanCreateOnceProp(): void
    {
        $onceProp = $this->inertia()->once(function () {
            return 'A once value';
        });

        $this->assertInstanceOf(OnceProp::class, $onceProp);
    }

    public function testCanCreateDeferredAndOnceProp(): void
    {
        $deferredProp = $this->inertia()->defer(function () {
            return 'A deferred + once value';
        })->once();

        $this->assertInstanceOf(DeferProp::class, $deferredProp);
        $this->assertTrue($deferredProp->shouldResolveOnce());
    }

    public function testCanCreateAlwaysProp(): void
    {
        $alwaysProp = $this->inertia()->always(function () {
            return 'An always value';
        });

        $this->assertInstanceOf(AlwaysProp::class, $alwaysProp);
    }

    public function testWillAcceptJsonSerializableProps(): void
    {
        $response = $this->makeMockRequest(function () {
            $this->inertia()->share('foo', 'bar');

            return $this->inertia()->render('User/Edit', new class implements \JsonSerializable {
                public function jsonSerialize(): array
                {
                    return [
                        'foo' => 'bar',
                    ];
                }
            });
        }, ['X-Inertia' => 'true']);

        $this->assertTrue($response->isSuccessful());
        $this->assertArrayContains([
            'component' => 'User/Edit',
            'props' => [
                'foo' => 'bar',
            ],
        ], $this->getPage($response));
    }

    public function testWillAcceptInstancesOfProvidesInertiaProps(): void
    {
        $response = $this->makeMockRequest(function () {
            return $this->inertia()->render('User/Edit', new ExampleInertiaPropsProvider([
                'foo' => 'bar',
            ]));
        }, ['X-Inertia' => 'true']);

        $this->assertTrue($response->isSuccessful());
        $this->assertArrayContains([
            'component' => 'User/Edit',
            'props' => [
                'foo' => 'bar',
            ],
        ], $this->getPage($response));
    }

    public function testWillAcceptArraysContainingProvidesInertiaPropsInRender(): void
    {
        $response = $this->makeMockRequest(function () {
            return $this->inertia()->render('User/Edit', [
                'regular' => 'prop',
                new ExampleInertiaPropsProvider([
                    'from_object' => 'value',
                ]),
                'another' => 'normal_prop',
            ]);
        }, ['X-Inertia' => 'true']);

        $this->assertTrue($response->isSuccessful());
        $this->assertArrayContains([
            'component' => 'User/Edit',
            'props' => [
                'regular' => 'prop',
                'from_object' => 'value',
                'another' => 'normal_prop',
            ],
        ], $this->getPage($response));
    }

    public function testCanShareInstancesOfProvidesInertiaProps(): void
    {
        $response = $this->makeMockRequest(function () {
            $this->inertia()->share(new ExampleInertiaPropsProvider([
                'shared' => 'data',
            ]));

            return $this->inertia()->render('User/Edit', [
                'regular' => 'prop',
            ]);
        }, ['X-Inertia' => 'true']);

        $this->assertTrue($response->isSuccessful());
        $this->assertArrayContains([
            'component' => 'User/Edit',
            'props' => [
                'shared' => 'data',
                'regular' => 'prop',
            ],
        ], $this->getPage($response));
    }

    public function testCanShareArraysContainingProvidesInertiaProps(): void
    {
        $response = $this->makeMockRequest(function () {
            $this->inertia()->share([
                'regular' => 'shared_prop',
                new ExampleInertiaPropsProvider([
                    'from_object' => 'shared_value',
                ]),
            ]);

            return $this->inertia()->render('User/Edit', [
                'component' => 'prop',
            ]);
        }, ['X-Inertia' => 'true']);

        $this->assertTrue($response->isSuccessful());
        $this->assertArrayContains([
            'component' => 'User/Edit',
            'props' => [
                'regular' => 'shared_prop',
                'from_object' => 'shared_value',
                'component' => 'prop',
            ],
        ], $this->getPage($response));
    }

    public function testWillThrowExceptionIfComponentDoesNotExistWhenEnsuringIsEnabled(): void
    {
        $this->configure(['pages' => ['ensure_pages_exist' => true]]);

        $this->expectException(ComponentNotFoundException::class);
        $this->expectExceptionMessage('Inertia page component [foo] not found.');

        $this->inertia()->render('foo');
    }

    public function testWillNotThrowExceptionIfComponentDoesNotExistWhenEnsuringIsDisabled(): void
    {
        $this->configure(['pages' => ['ensure_pages_exist' => false]]);

        $response = $this->inertia()->render('foo');
        $this->assertInstanceOf(InertiaResponse::class, $response);
    }

    public function testCanResolveComponentNameBeforeRendering(): void
    {
        $calledWith = null;

        $response = $this->makeMockRequest(function () use (&$calledWith) {
            $this->inertia()->transformComponentUsing(static function (string $name) use (&$calledWith): string {
                $calledWith = $name;

                return "{$name}/Page";
            });

            return $this->inertia()->render('Stubs/Example');
        }, ['X-Inertia' => 'true']);

        $this->assertTrue($response->isSuccessful());
        $this->assertArrayContains([
            'component' => 'Stubs/Example/Page',
        ], $this->getPage($response));
        $this->assertSame('Stubs/Example', $calledWith);
    }

    public function testResolvedComponentNameIsUsedForPageExistenceChecks(): void
    {
        $calledWith = null;

        $this->configure(['pages' => ['ensure_pages_exist' => true]]);

        $response = $this->makeMockRequest(function () use (&$calledWith) {
            $this->inertia()->transformComponentUsing(static function (string $name) use (&$calledWith): string {
                $calledWith = $name;

                return "{$name}/Page";
            });

            return $this->inertia()->render('Stubs/Example');
        }, ['X-Inertia' => 'true']);

        $this->assertTrue($response->isSuccessful());
        $this->assertSame('Stubs/Example', $calledWith);
    }

    public function testRenderAcceptsBackedEnum(): void
    {
        $request = $this->pushRequest($this->makeRequest(headers: ['X-Inertia' => 'true']));

        $response = $this->inertia()->render(StringBackedEnum::UsersIndex);
        $this->assertInstanceOf(InertiaResponse::class, $response);

        $this->assertSame('UsersPage/Index', $this->getPage($response->toResponse($request))['component']);
    }

    public function testRenderAcceptsUnitEnum(): void
    {
        $request = $this->pushRequest($this->makeRequest(headers: ['X-Inertia' => 'true']));

        $response = $this->inertia()->render(UnitEnum::Index);
        $this->assertInstanceOf(InertiaResponse::class, $response);

        $this->assertSame('Index', $this->getPage($response->toResponse($request))['component']);
    }

    public function testRenderThrowsForNonStringBackedEnum(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->inertia()->render(IntBackedEnum::Zero);
    }

    public function testShareOnceSharesAOnceProp(): void
    {
        $response = $this->makeMockRequest(function () {
            $this->inertia()->shareOnce('settings', fn () => ['theme' => 'dark']);

            return $this->inertia()->render('User/Edit');
        }, ['X-Inertia' => 'true']);

        $this->assertTrue($response->isSuccessful());
        $this->assertArrayContains([
            'component' => 'User/Edit',
            'props' => [
                'settings' => ['theme' => 'dark'],
            ],
            'onceProps' => [
                'settings' => [
                    'prop' => 'settings',
                    'expiresAt' => null,
                ],
            ],
        ], $this->getPage($response));
    }

    public function testShareOnceIsChainable(): void
    {
        $response = $this->makeMockRequest(function () {
            $prop = $this->inertia()->shareOnce('settings', fn () => ['theme' => 'dark'])
                ->as('app-settings')
                ->until(60);

            $this->assertInstanceOf(OnceProp::class, $prop);

            return $this->inertia()->render('User/Edit');
        }, ['X-Inertia' => 'true']);

        $this->assertTrue($response->isSuccessful());
        $data = $this->getPage($response);

        $this->assertArrayHasKey('onceProps', $data);
        $this->assertArrayHasKey('app-settings', $data['onceProps']);
        $this->assertEquals('settings', $data['onceProps']['app-settings']['prop']);
        $this->assertNotNull($data['onceProps']['app-settings']['expiresAt']);
    }

    public function testForcefullyRefreshingAOncePropIncludesItInOnceProps(): void
    {
        $response = $this->makeMockRequest(function () {
            return $this->inertia()->render('User/Edit', [
                'settings' => $this->inertia()->once(fn () => ['theme' => 'dark'])->fresh(),
            ]);
        }, ['X-Inertia' => 'true']);

        $this->assertTrue($response->isSuccessful());
        $this->assertArrayContains([
            'component' => 'User/Edit',
            'props' => [
                'settings' => ['theme' => 'dark'],
            ],
            'onceProps' => [
                'settings' => ['prop' => 'settings', 'expiresAt' => null],
            ],
        ], $this->getPage($response));
    }

    public function testOncePropIsIncludedInOncePropsByDefault(): void
    {
        $response = $this->makeMockRequest(function () {
            return $this->inertia()->render('User/Edit', [
                'settings' => $this->inertia()->once(fn () => ['theme' => 'dark']),
            ]);
        }, ['X-Inertia' => 'true']);

        $this->assertTrue($response->isSuccessful());
        $this->assertArrayContains([
            'component' => 'User/Edit',
            'props' => [
                'settings' => ['theme' => 'dark'],
            ],
            'onceProps' => [
                'settings' => [
                    'prop' => 'settings',
                    'expiresAt' => null,
                ],
            ],
        ], $this->getPage($response));
    }

    public function testFlashDataIsFlashedToSessionOnRedirect(): void
    {
        $response = $this->makeMockRequest(function () {
            return $this->inertia()->flash(['message' => 'Success!'])->back();
        }, ['X-Inertia' => 'true'], 'POST');

        $this->assertTrue($response->isRedirect());
        $this->assertEquals(['message' => 'Success!'], $this->lastRequest->getSession()->get('inertia.flash_data'));
    }

    public function testRenderWithFlashIncludesFlashInPage(): void
    {
        $response = $this->makeMockRequest(function () {
            return $this->inertia()->flash('type', 'success')
                ->render('User/Edit', ['user' => 'Jonathan'])
                ->flash(['message' => 'User updated!']);
        }, ['X-Inertia' => 'true'], 'POST');

        $this->assertTrue($response->isSuccessful());
        $this->assertArrayContains([
            'component' => 'User/Edit',
            'props' => [
                'user' => 'Jonathan',
            ],
            'flash' => [
                'message' => 'User updated!',
                'type' => 'success',
            ],
        ], $this->getPage($response));

        // Flash data should not persist in session after being included in response
        $this->assertNull($this->lastRequest->getSession()->get('inertia.flash_data'));
    }

    public function testBigIntegersInFlashDataAreWrappedWhenEnabled(): void
    {
        $this->configure(['preserve_big_integers' => true]);

        $response = $this->makeMockRequest(function () {
            return $this->inertia()->flash('id', 900719925474099988)
                ->flash('safe', 42)
                ->render('User/Edit');
        }, ['X-Inertia' => 'true'], 'POST');

        $this->assertTrue($response->isSuccessful());
        $this->assertArrayContains([
            'flash' => [
                'id' => ['$bigint' => '900719925474099988'],
                'safe' => 42,
            ],
        ], $this->getPage($response));
    }

    public function testBigIntegersInFlashDataAreUntouchedWhenDisabled(): void
    {
        $this->configure(['preserve_big_integers' => false]);

        $response = $this->makeMockRequest(function () {
            return $this->inertia()->flash('id', 900719925474099988)->render('User/Edit');
        }, ['X-Inertia' => 'true'], 'POST');

        $this->assertTrue($response->isSuccessful());
        $this->assertArrayContains(['flash' => ['id' => 900719925474099988]], $this->getPage($response));
    }

    public function testBigIntegersCanBePreservedForASingleResponse(): void
    {
        $this->configure(['preserve_big_integers' => false]);

        $response = $this->makeMockRequest(function () {
            return $this->inertia()->render('User/Edit', ['id' => 900719925474099988])->preserveBigIntegers();
        }, ['X-Inertia' => 'true']);

        $this->assertArrayContains([
            'props' => ['id' => ['$bigint' => '900719925474099988']],
            'preserveBigIntegers' => true,
        ], $this->getPage($response));
    }

    public function testASingleResponseCanOptOutOfPreservingBigIntegers(): void
    {
        $this->configure(['preserve_big_integers' => true]);

        $response = $this->makeMockRequest(function () {
            return $this->inertia()->render('User/Edit', ['id' => 900719925474099988])->preserveBigIntegers(false);
        }, ['X-Inertia' => 'true']);

        $page = $this->getPage($response);
        $this->assertArrayContains(['props' => ['id' => 900719925474099988]], $page);
        $this->assertArrayNotHasKey('preserveBigIntegers', $page);
    }

    public function testTheInitialPageTellsTheClientWhenBigIntegersMayBeWrapped(): void
    {
        $this->configure(['preserve_big_integers' => true]);

        $response = $this->makeMockRequest(function () {
            return $this->inertia()->render('User/Edit', ['id' => 900719925474099988]);
        });

        $this->assertStringContainsString('"preserveBigIntegers":true', (string) $response->getContent());
    }

    public function testTheInitialPageIsUnmarkedWhenBigIntegersAreDisabled(): void
    {
        $this->configure(['preserve_big_integers' => false]);

        $response = $this->makeMockRequest(function () {
            return $this->inertia()->render('User/Edit', ['id' => 900719925474099988]);
        });

        $this->assertStringNotContainsString('preserveBigIntegers', (string) $response->getContent());
    }

    public function testRenderWithoutFlashDoesNotIncludeFlashKey(): void
    {
        $response = $this->makeMockRequest(function () {
            return $this->inertia()->render('User/Edit', ['user' => 'Jonathan']);
        }, ['X-Inertia' => 'true']);

        $this->assertTrue($response->isSuccessful());
        $page = $this->getPage($response);
        $this->assertArrayContains([
            'component' => 'User/Edit',
        ], $page);
        $this->assertArrayNotHasKey('flash', $page);
    }

    public function testMultipleFlashCallsAreMerged(): void
    {
        $response = $this->makeMockRequest(function () {
            $this->inertia()->flash('foo', 'value1');
            $this->inertia()->flash('bar', 'value2');

            return $this->inertia()->render('User/Show');
        }, ['X-Inertia' => 'true'], 'POST');

        $this->assertArrayContains([
            'flash' => [
                'foo' => 'value1',
                'bar' => 'value2',
            ],
        ], $this->getPage($response));
    }

    public function testSharedPropsTrackingCanBeDisabled(): void
    {
        $this->configure(['expose_shared_prop_keys' => false]);

        $response = $this->makeMockRequest(function () {
            $this->inertia()->share('app_name', 'My App');

            return $this->inertia()->render('User/Edit');
        }, ['X-Inertia' => 'true']);

        $this->assertTrue($response->isSuccessful());
        $data = $this->getPage($response);
        $this->assertArrayNotHasKey('sharedProps', $data);
        $this->assertSame('My App', $data['props']['app_name']);
    }

    public function testSharedPropsMetadataIncludesKeysFromMiddlewareShare(): void
    {
        $response = $this->makeMockRequest(function () {
            return $this->inertia()->render('User/Edit', [
                'user' => ['name' => 'Jonathan'],
            ]);
        }, ['X-Inertia' => 'true']);

        $this->assertTrue($response->isSuccessful());
        $this->assertArrayContains([
            'component' => 'User/Edit',
            'props' => [
                'user' => ['name' => 'Jonathan'],
            ],
            'sharedProps' => ['errors'],
        ], $this->getPage($response));
    }

    public function testSharedPropsMetadataIncludesKeysFromInertiaShare(): void
    {
        $response = $this->makeMockRequest(function () {
            $this->inertia()->share('app_name', 'My App');

            return $this->inertia()->render('User/Edit');
        }, ['X-Inertia' => 'true']);

        $this->assertTrue($response->isSuccessful());
        $this->assertArrayContains([
            'sharedProps' => ['errors', 'app_name'],
        ], $this->getPage($response));
    }

    public function testSharedPropsMetadataIncludesDotNotationKeysAsTopLevel(): void
    {
        $response = $this->makeMockRequest(function () {
            $this->inertia()->share('auth.user', ['name' => 'Jonathan']);

            return $this->inertia()->render('User/Edit');
        }, ['X-Inertia' => 'true']);

        $this->assertTrue($response->isSuccessful());
        $this->assertArrayContains([
            'sharedProps' => ['errors', 'auth'],
        ], $this->getPage($response));
    }

    public function testSharedPropsMetadataIncludesKeysFromShareOnce(): void
    {
        $response = $this->makeMockRequest(function () {
            $this->inertia()->shareOnce('permissions', fn () => ['admin' => true]);

            return $this->inertia()->render('User/Edit');
        }, ['X-Inertia' => 'true']);

        $this->assertTrue($response->isSuccessful());
        $this->assertArrayContains([
            'props' => [
                'permissions' => ['admin' => true],
            ],
            'sharedProps' => ['errors', 'permissions'],
        ], $this->getPage($response));
    }

    public function testSharedPropsMetadataIncludesKeysFromProvidesInertiaProperties(): void
    {
        $response = $this->makeMockRequest(function () {
            $this->inertia()->share(new ExampleInertiaPropsProvider([
                'app_name' => 'My App',
                'locale' => 'en',
            ]));

            return $this->inertia()->render('User/Edit');
        }, ['X-Inertia' => 'true']);

        $this->assertTrue($response->isSuccessful());
        $this->assertArrayContains([
            'props' => [
                'app_name' => 'My App',
                'locale' => 'en',
            ],
            'sharedProps' => ['errors', 'app_name', 'locale'],
        ], $this->getPage($response));
    }

    public function testSharedPropsMetadataIncludesPageSpecificOverrideKeys(): void
    {
        $response = $this->makeMockRequest(function () {
            $this->inertia()->share('auth', ['user' => null]);

            return $this->inertia()->render('User/Edit', [
                'auth' => ['user' => ['name' => 'Jonathan']],
            ]);
        }, ['X-Inertia' => 'true']);

        $this->assertTrue($response->isSuccessful());
        $this->assertArrayContains([
            'props' => [
                'auth' => ['user' => ['name' => 'Jonathan']],
            ],
            'sharedProps' => ['errors', 'auth'],
        ], $this->getPage($response));
    }

    public function testSharedPropsMetadataWithMultipleShareCalls(): void
    {
        $response = $this->makeMockRequest(function () {
            $this->inertia()->share('app_name', 'My App');
            $this->inertia()->share('locale', 'en');
            $this->inertia()->shareOnce('permissions', fn () => ['admin' => true]);

            return $this->inertia()->render('User/Edit');
        }, ['X-Inertia' => 'true']);

        $this->assertTrue($response->isSuccessful());
        $this->assertArrayContains([
            'sharedProps' => ['errors', 'app_name', 'locale', 'permissions'],
        ], $this->getPage($response));
    }

    public function testSharedPropsMetadataWithArrayShare(): void
    {
        $response = $this->makeMockRequest(function () {
            $this->inertia()->share([
                'flash' => fn () => ['message' => 'Hello'],
                'auth' => fn () => ['user' => ['name' => 'Jonathan']],
            ]);

            return $this->inertia()->render('User/Edit');
        }, ['X-Inertia' => 'true']);

        $this->assertTrue($response->isSuccessful());
        $this->assertArrayContains([
            'sharedProps' => ['errors', 'flash', 'auth'],
        ], $this->getPage($response));
    }

    public function testSharedPropsMetadataIncludesAlreadyLoadedOnceProps(): void
    {
        $response = $this->makeMockRequest(function () {
            $this->inertia()->shareOnce('permissions', fn () => ['admin' => true]);

            return $this->inertia()->render('User/Edit');
        }, [
            'X-Inertia' => 'true',
            'X-Inertia-Except-Once-Props' => 'permissions',
        ]);

        $this->assertTrue($response->isSuccessful());
        $data = $this->getPage($response);

        // The once-prop value should be excluded from props since the client already has it
        $this->assertArrayNotHasKey('permissions', $data['props']);

        // But its key should still appear in the sharedProps metadata
        $this->assertContains('permissions', $data['sharedProps']);

        // And its onceProps metadata should also be preserved
        $this->assertArrayHasKey('permissions', $data['onceProps']);
    }

    public function testWithoutSsrRegistersPathsWithGateway(): void
    {
        $this->inertia()->withoutSsr(['admin/*', 'nova/*']);

        $gateway = static::getContainer()->get(HttpGateway::class);
        $this->assertContains('admin/*', $gateway->getExcludedPaths());
        $this->assertContains('nova/*', $gateway->getExcludedPaths());
    }

    public function testWithoutSsrAcceptsString(): void
    {
        $this->inertia()->withoutSsr('admin/*');

        $this->assertContains('admin/*', static::getContainer()->get(HttpGateway::class)->getExcludedPaths());
    }

    public function testConfigureSsrRequestUsingRegistersTheCallbackWithTheGateway(): void
    {
        $this->configure(['ssr' => ['enabled' => true, 'ensure_bundle_exists' => false]]);

        $sent = [];
        $this->fakeHttpClient(function (string $method, string $url, array $options) use (&$sent) {
            $sent[] = $options;

            return new MockResponse(json_encode(['head' => [], 'body' => '<div id="app"></div>']));
        });

        // The callback receives and returns the Symfony HttpClient options.
        $this->inertia()->configureSsrRequestUsing(fn (array $options) => array_merge_recursive($options, ['headers' => ['X-Tenant' => 'acme']]));

        $this->assertNotNull(static::getContainer()->get(HttpGateway::class)->dispatch(self::EXAMPLE_PAGE_OBJECT));

        $this->assertCount(1, $sent);
        $this->assertContains('X-Tenant: acme', $sent[0]['headers']);
    }

    public function testSsrRendersEachPageWhenTheContainerIsReusedBetweenRequests(): void
    {
        $this->configure(['ssr' => ['enabled' => true, 'ensure_bundle_exists' => false]]);

        // Counted here: the services resetter clears MockHttpClient's own count between requests.
        $requests = 0;
        $this->fakeHttpClient(function (string $method, string $url, array $options) use (&$requests) {
            ++$requests;
            $page = json_decode($options['body'], true);

            return new MockResponse(json_encode([
                'head' => [],
                'body' => '<div id="app">'.$page['component'].'</div>',
            ]));
        });

        $pageA = $this->makeMockRequest(fn () => $this->inertia()->render('Page/A'));
        $pageB = $this->makeMockRequest(fn () => $this->inertia()->render('Page/B'));

        $this->assertStringContainsString('<div id="app">Page/A</div>', (string) $pageA->getContent());
        $this->assertStringContainsString('<div id="app">Page/B</div>', (string) $pageB->getContent());

        $this->assertSame(2, $requests);
    }

    public function testConfigureSsrRequestUsingThrowsWhenTheGatewayDoesNotSupportIt(): void
    {
        self::bootKernel();
        static::getContainer()->set(SsrGatewayInterface::class, new FakeGateway());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The configured SSR gateway does not support configuring server-side rendering requests.');

        $this->inertia()->configureSsrRequestUsing(fn (array $options) => $options);
    }
}
