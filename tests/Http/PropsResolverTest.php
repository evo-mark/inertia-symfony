<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\Http;

use EvoMark\InertiaSymfonyBundle\Http\Response as InertiaResponse;
use EvoMark\InertiaSymfonyBundle\Interfaces\ProvidesInertiaPropertiesInterface;
use EvoMark\InertiaSymfonyBundle\Interfaces\ProvidesInertiaPropertyInterface;
use EvoMark\InertiaSymfonyBundle\Interfaces\ProvidesScrollMetadataInterface;
use EvoMark\InertiaSymfonyBundle\PageProps\AlwaysProp;
use EvoMark\InertiaSymfonyBundle\PageProps\DeferProp;
use EvoMark\InertiaSymfonyBundle\PageProps\MergeProp;
use EvoMark\InertiaSymfonyBundle\PageProps\OnceProp;
use EvoMark\InertiaSymfonyBundle\PageProps\OptionalProp;
use EvoMark\InertiaSymfonyBundle\PageProps\ScrollProp;
use EvoMark\InertiaSymfonyBundle\Support\PropertyContext;
use EvoMark\InertiaSymfonyBundle\Support\RenderContext;
use EvoMark\InertiaSymfonyBundle\Tests\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Prop resolution: closures, providers, partial reloads, prop types and their
 * metadata, and big integers.
 *
 * Pages are read back from the rendered response, so assertions on object props
 * use decoded arrays (or the raw JSON where decoding would change the shape).
 */
final class PropsResolverTest extends TestCase
{
    public function testNestedClosureIsResolved(): void
    {
        $page = $this->makePage($this->makeRequest('/'), [
            'auth' => fn () => ['user' => 'Jonathan'],
        ]);

        $this->assertSame('Jonathan', $page['props']['auth']['user']);
    }

    public function testNestedClosureInsideArrayIsResolved(): void
    {
        $page = $this->makePage($this->makeRequest('/'), [
            'auth' => [
                'user' => fn () => 'Jonathan',
            ],
        ]);

        $this->assertSame('Jonathan', $page['props']['auth']['user']);
    }

    public function testNestedProvidesInertiaPropertiesIsResolved(): void
    {
        $page = $this->makePage($this->makeRequest('/'), [
            'auth' => [
                new class implements ProvidesInertiaPropertiesInterface {
                    public function toInertiaProperties(RenderContext $context): iterable
                    {
                        return ['user' => 'Jonathan', 'role' => 'admin'];
                    }
                },
                'team' => 'Inertia',
            ],
        ]);

        $this->assertSame('Jonathan', $page['props']['auth']['user']);
        $this->assertSame('admin', $page['props']['auth']['role']);
        $this->assertSame('Inertia', $page['props']['auth']['team']);
    }

    public function testNestedProvidesInertiaPropertiesWithPropTypes(): void
    {
        $page = $this->makePage($this->makeRequest('/'), [
            'auth' => [
                new class implements ProvidesInertiaPropertiesInterface {
                    public function toInertiaProperties(RenderContext $context): iterable
                    {
                        return [
                            'user' => 'Jonathan',
                            'permissions' => new OptionalProp(fn () => ['manage-users']),
                        ];
                    }
                },
            ],
        ]);

        $this->assertSame('Jonathan', $page['props']['auth']['user']);
        $this->assertArrayNotHasKey('permissions', $page['props']['auth']);
    }

    public function testNestedProvidesInertiaPropertiesWithPropTypesOnPartialRequest(): void
    {
        $page = $this->makePage(
            $this->makePartialRequest('auth.permissions'),
            [
                'auth' => [
                    new class implements ProvidesInertiaPropertiesInterface {
                        public function toInertiaProperties(RenderContext $context): iterable
                        {
                            return [
                                'user' => 'Jonathan',
                                'permissions' => new OptionalProp(fn () => ['manage-users']),
                            ];
                        }
                    },
                ],
            ],
        );

        $this->assertArrayNotHasKey('user', $page['props']['auth']);
        $this->assertSame(['manage-users'], $page['props']['auth']['permissions']);
    }

    public function testNestedAlwaysPropIsResolved(): void
    {
        $page = $this->makePage($this->makeRequest('/'), [
            'auth' => [
                'user' => new AlwaysProp(fn () => 'Jonathan'),
            ],
        ]);

        $this->assertSame('Jonathan', $page['props']['auth']['user']);
    }

    public function testNestedMergePropIsResolved(): void
    {
        $page = $this->makePage($this->makeRequest('/'), [
            'feed' => [
                'posts' => new MergeProp([['id' => 1]]),
            ],
        ]);

        $this->assertSame([['id' => 1]], $page['props']['feed']['posts']);
    }

    public function testNestedOncePropIsResolvedOnInitialLoad(): void
    {
        $page = $this->makePage($this->makeRequest('/'), [
            'config' => [
                'locale' => new OnceProp(fn () => 'en'),
            ],
        ]);

        $this->assertSame('en', $page['props']['config']['locale']);
    }

    public function testNestedOptionalPropIsExcludedFromInitialLoad(): void
    {
        $resolved = false;

        $page = $this->makePage($this->makeRequest('/'), [
            'auth' => [
                'user' => 'Jonathan',
                'permissions' => new OptionalProp(function () use (&$resolved) {
                    $resolved = true;

                    return ['admin'];
                }),
            ],
        ]);

        $this->assertSame('Jonathan', $page['props']['auth']['user']);
        $this->assertArrayNotHasKey('permissions', $page['props']['auth']);
        $this->assertFalse($resolved, 'OptionalProp closure should not be resolved on initial load');
    }

    public function testNestedDeferPropIsExcludedFromInitialLoad(): void
    {
        $resolved = false;

        $page = $this->makePage($this->makeRequest('/'), [
            'auth' => [
                'user' => 'Jonathan',
                'notifications' => new DeferProp(function () use (&$resolved) {
                    $resolved = true;

                    return [];
                }),
            ],
        ]);

        $this->assertSame('Jonathan', $page['props']['auth']['user']);
        $this->assertArrayNotHasKey('notifications', $page['props']['auth']);
        $this->assertFalse($resolved, 'DeferProp closure should not be resolved on initial load');
    }

    public function testRescuedDeferPropIsOmittedAndMetadataOnPartialRequest(): void
    {
        $page = $this->makePage($this->makePartialRequest('auth.notifications'), [
            'auth' => [
                'notifications' => new DeferProp(function () {
                    throw new \RuntimeException('Rescue this deferred prop');
                }, rescue: true),
            ],
        ]);

        $this->assertArrayNotHasKey('notifications', $page['props']['auth']);
        $this->assertSame(['auth.notifications'], $page['rescuedProps']);
    }

    public function testRescuedDeferPropIsOmittedWhenProvidesInertiaPropertyThrows(): void
    {
        $page = $this->makePage($this->makePartialRequest('stats'), [
            'stats' => new DeferProp(function () {
                return new class implements ProvidesInertiaPropertyInterface {
                    public function toInertiaProperty(PropertyContext $prop): mixed
                    {
                        throw new \RuntimeException('Failed to resolve stats');
                    }
                };
            }, rescue: true),
        ]);

        $this->assertArrayNotHasKey('stats', $page['props']);
        $this->assertSame(['stats'], $page['rescuedProps']);
    }

    public function testExcludedPropsAreNotResolvedOnInitialLoad(): void
    {
        $optionalResolved = false;
        $deferResolved = false;

        $page = $this->makePage($this->makeRequest('/'), [
            'name' => 'Jonathan',
            'permissions' => new OptionalProp(function () use (&$optionalResolved) {
                $optionalResolved = true;

                return ['admin'];
            }),
            'notifications' => new DeferProp(function () use (&$deferResolved) {
                $deferResolved = true;

                return ['You have a new follower'];
            }),
        ]);

        $this->assertSame('Jonathan', $page['props']['name']);
        $this->assertArrayNotHasKey('permissions', $page['props']);
        $this->assertArrayNotHasKey('notifications', $page['props']);
        $this->assertFalse($optionalResolved, 'OptionalProp closure should not be resolved on initial load');
        $this->assertFalse($deferResolved, 'DeferProp closure should not be resolved on initial load');
    }

    public function testClosureReturningOptionalPropIsExcludedFromInitialLoad(): void
    {
        $resolved = false;

        $page = $this->makePage($this->makeRequest('/'), [
            'auth' => fn () => [
                'user' => 'Jonathan',
                'permissions' => new OptionalProp(function () use (&$resolved) {
                    $resolved = true;

                    return ['admin'];
                }),
            ],
        ]);

        $this->assertSame('Jonathan', $page['props']['auth']['user']);
        $this->assertArrayNotHasKey('permissions', $page['props']['auth']);
        $this->assertFalse($resolved, 'OptionalProp closure should not be resolved on initial load'); // @phpstan-ignore method.impossibleType
    }

    public function testClosureReturningDeferPropIsExcludedFromInitialLoad(): void
    {
        $resolved = false;

        $page = $this->makePage($this->makeRequest('/'), [
            'auth' => fn () => [
                'user' => 'Jonathan',
                'notifications' => new DeferProp(function () use (&$resolved) {
                    $resolved = true;

                    return [];
                }),
            ],
        ]);

        $this->assertSame('Jonathan', $page['props']['auth']['user']);
        $this->assertArrayNotHasKey('notifications', $page['props']['auth']);
        $this->assertFalse($resolved, 'DeferProp closure should not be resolved on initial load'); // @phpstan-ignore method.impossibleType
    }

    public function testClosureReturningMergePropResolvesWithMetadata(): void
    {
        $page = $this->makePage($this->makeRequest('/'), [
            'posts' => fn () => new MergeProp([['id' => 1]]),
        ]);

        $this->assertSame([['id' => 1]], $page['props']['posts']);
        $this->assertSame(['posts'], $page['mergeProps']);
    }

    public function testClosureReturningOncePropResolvesWithMetadata(): void
    {
        $page = $this->makePage($this->makeRequest('/'), [
            'locale' => fn () => new OnceProp(fn () => 'en'),
        ]);

        $this->assertSame('en', $page['props']['locale']);
        $this->assertSame(['locale' => ['prop' => 'locale', 'expiresAt' => null]], $page['onceProps']);
    }

    public function testClosureReturningDeferPropCollectsDeferredAndMergeMetadata(): void
    {
        $page = $this->makePage($this->makeRequest('/'), [
            'posts' => fn () => new DeferProp(fn () => [['id' => 1]])->merge(),
        ]);

        $this->assertArrayNotHasKey('posts', $page['props']);
        $this->assertSame(['default' => ['posts']], $page['deferredProps']);
        $this->assertSame(['posts'], $page['mergeProps']);
    }

    public function testNestedOptionalPropIsIncludedOnPartialRequest(): void
    {
        $page = $this->makePage($this->makePartialRequest('auth.permissions'), [
            'auth' => [
                'user' => 'Jonathan',
                'permissions' => new OptionalProp(fn () => ['admin']),
            ],
        ]);

        $this->assertSame(['admin'], $page['props']['auth']['permissions']);
    }

    public function testNestedDeferPropIsIncludedOnPartialRequest(): void
    {
        $page = $this->makePage($this->makePartialRequest('auth.notifications'), [
            'auth' => [
                'user' => 'Jonathan',
                'notifications' => new DeferProp(fn () => ['new message']),
            ],
        ]);

        $this->assertSame(['new message'], $page['props']['auth']['notifications']);
    }

    public function testNestedAlwaysPropIsIncludedOnPartialRequest(): void
    {
        $page = $this->makePage($this->makePartialRequest('auth.user'), [
            'auth' => [
                'user' => 'Jonathan',
                'errors' => new AlwaysProp(fn () => ['name' => 'required']),
            ],
        ]);

        $this->assertSame('Jonathan', $page['props']['auth']['user']);
        $this->assertSame(['name' => 'required'], $page['props']['auth']['errors']);
    }

    public function testTopLevelAlwaysPropIsIncludedWhenNotRequested(): void
    {
        $page = $this->makePage($this->makePartialRequest('other'), [
            'other' => 'value',
            'errors' => new AlwaysProp(fn () => ['name' => 'required']),
        ]);

        $this->assertSame('value', $page['props']['other']);
        $this->assertSame(['name' => 'required'], $page['props']['errors']);
    }

    public function testNestedMergePropIsIncludedOnPartialRequest(): void
    {
        $page = $this->makePage($this->makePartialRequest('feed.posts'), [
            'feed' => [
                'posts' => new MergeProp([['id' => 1]]),
            ],
        ]);

        $this->assertSame([['id' => 1]], $page['props']['feed']['posts']);
    }

    public function testNestedPropIsExcludedViaExceptHeader(): void
    {
        $request = $this->makeRequest('/');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Partial-Component' => 'TestComponent']);
        $request->headers->add(['X-Inertia-Partial-Data' => 'auth']);
        $request->headers->add(['X-Inertia-Partial-Except' => 'auth.token']);

        $page = $this->makePage($request, [
            'auth' => [
                'user' => 'Jonathan',
                'token' => 'secret',
            ],
        ]);

        $this->assertSame('Jonathan', $page['props']['auth']['user']);
        $this->assertArrayNotHasKey('token', $page['props']['auth']);
    }

    public function testPartialRequestForParentResolvesAllNestedPropTypes(): void
    {
        $page = $this->makePage($this->makePartialRequest('dashboard'), [
            'dashboard' => [
                'stats' => 'visible',
                'feed' => new MergeProp([['id' => 1]]),
                'notifications' => new DeferProp(fn () => ['msg']),
                'settings' => new OptionalProp(fn () => ['theme' => 'dark']),
                'locale' => new OnceProp(fn () => 'en'),
            ],
        ]);

        $this->assertSame('visible', $page['props']['dashboard']['stats']);
        $this->assertSame([['id' => 1]], $page['props']['dashboard']['feed']);
        $this->assertSame(['msg'], $page['props']['dashboard']['notifications']);
        $this->assertSame(['theme' => 'dark'], $page['props']['dashboard']['settings']);
        $this->assertSame('en', $page['props']['dashboard']['locale']);
        $this->assertSame(['dashboard.feed'], $page['mergeProps']);
        $this->assertSame(['dashboard.locale' => ['prop' => 'dashboard.locale', 'expiresAt' => null]], $page['onceProps']);
        $this->assertArrayNotHasKey('deferredProps', $page);
    }

    public function testNestedDeferPropMetadataIsCollected(): void
    {
        $page = $this->makePage($this->makeRequest('/'), [
            'auth' => [
                'user' => 'Jonathan',
                'notifications' => new DeferProp(fn () => []),
            ],
        ]);

        $this->assertSame(['default' => ['auth.notifications']], $page['deferredProps']);
    }

    public function testNestedDeferPropMetadataPreservesGroup(): void
    {
        $page = $this->makePage($this->makeRequest('/'), [
            'auth' => [
                'notifications' => new DeferProp(fn () => [], 'sidebar'),
                'messages' => new DeferProp(fn () => [], 'sidebar'),
            ],
        ]);

        $this->assertSame(['sidebar' => ['auth.notifications', 'auth.messages']], $page['deferredProps']);
    }

    public function testClosureReturningDeferPropMetadataIsCollected(): void
    {
        $page = $this->makePage($this->makeRequest('/'), [
            'auth' => fn () => [
                'user' => 'Jonathan',
                'notifications' => new DeferProp(fn () => [], 'alerts'),
            ],
        ]);

        $this->assertSame(['alerts' => ['auth.notifications']], $page['deferredProps']);
    }

    public function testNestedMergePropMetadataIsCollected(): void
    {
        $page = $this->makePage($this->makeRequest('/'), [
            'feed' => [
                'posts' => new MergeProp([['id' => 1]]),
            ],
        ]);

        $this->assertSame(['feed.posts'], $page['mergeProps']);
    }

    public function testNestedPrependMergePropMetadataIsCollected(): void
    {
        $page = $this->makePage($this->makeRequest('/'), [
            'feed' => [
                'posts' => new MergeProp([['id' => 1]])->prepend(),
            ],
        ]);

        $this->assertSame(['feed.posts'], $page['prependProps']);
    }

    public function testNestedDeepMergePropMetadataIsCollected(): void
    {
        $page = $this->makePage($this->makeRequest('/'), [
            'settings' => [
                'preferences' => new MergeProp(['theme' => 'dark'])->deepMerge(),
            ],
        ]);

        $this->assertSame(['settings.preferences'], $page['deepMergeProps']);
    }

    public function testNestedMergePropWithNestedPathMetadataIsCollected(): void
    {
        $page = $this->makePage($this->makeRequest('/'), [
            'feed' => [
                'posts' => new MergeProp(['data' => [['id' => 1]]])->append('data'),
            ],
        ]);

        $this->assertSame(['feed.posts.data'], $page['mergeProps']);
    }

    public function testNestedMergePropWithMatchOnMetadataIsCollected(): void
    {
        $page = $this->makePage($this->makeRequest('/'), [
            'feed' => [
                'posts' => new MergeProp([['id' => 1]])->matchOn('id')->deepMerge(),
            ],
        ]);

        $this->assertSame(['feed.posts'], $page['deepMergeProps']);
        $this->assertSame(['feed.posts.id'], $page['matchPropsOn']);
    }

    public function testNestedDeferWithMergeMetadataIsCollected(): void
    {
        $page = $this->makePage($this->makeRequest('/'), [
            'feed' => [
                'posts' => new DeferProp(fn () => [['id' => 1]])->merge(),
            ],
        ]);

        $this->assertSame(['default' => ['feed.posts']], $page['deferredProps']);
        $this->assertSame(['feed.posts'], $page['mergeProps']);
    }

    public function testNestedMergeMetadataIsCollectedOnExactPartialRequest(): void
    {
        $page = $this->makePage($this->makePartialRequest('feed.posts'), [
            'feed' => [
                'posts' => new MergeProp([['id' => 1]]),
            ],
        ]);

        $this->assertSame([['id' => 1]], $page['props']['feed']['posts']);
        $this->assertSame(['feed.posts'], $page['mergeProps']);
    }

    public function testNestedMergeMetadataIsCollectedWhenParentIsRequested(): void
    {
        $page = $this->makePage($this->makePartialRequest('feed'), [
            'feed' => [
                'posts' => new MergeProp([['id' => 1]]),
            ],
        ]);

        $this->assertSame([['id' => 1]], $page['props']['feed']['posts']);
        $this->assertSame(['feed.posts'], $page['mergeProps']);
    }

    public function testNestedMergePropMetadataIsSuppressedByResetHeader(): void
    {
        $request = $this->makePartialRequest('feed.posts');
        $request->headers->add(['X-Inertia-Reset' => 'feed.posts']);

        $page = $this->makePage($request, [
            'feed' => [
                'posts' => new MergeProp([['id' => 1]]),
            ],
        ]);

        $this->assertSame([['id' => 1]], $page['props']['feed']['posts']);
        $this->assertArrayNotHasKey('mergeProps', $page);
    }

    public function testNestedOncePropMetadataIsCollected(): void
    {
        $page = $this->makePage($this->makeRequest('/'), [
            'config' => [
                'locale' => new OnceProp(fn () => 'en'),
            ],
        ]);

        $this->assertSame(['config.locale' => ['prop' => 'config.locale', 'expiresAt' => null]], $page['onceProps']);
    }

    public function testNestedOncePropWithCustomKeyMetadataIsCollected(): void
    {
        $page = $this->makePage($this->makeRequest('/'), [
            'config' => [
                'locale' => new OnceProp(fn () => 'en')->as('app-locale'),
            ],
        ]);

        $this->assertSame(['app-locale' => ['prop' => 'config.locale', 'expiresAt' => null]], $page['onceProps']);
    }

    public function testNestedOncePropIsExcludedWhenAlreadyLoaded(): void
    {
        $request = $this->makeRequest('/');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Except-Once-Props' => 'config.locale']);

        $page = $this->makePage($request, [
            'config' => [
                'locale' => new OnceProp(fn () => 'en'),
                'timezone' => 'UTC',
            ],
        ]);

        $this->assertSame('UTC', $page['props']['config']['timezone']);
        $this->assertArrayNotHasKey('locale', $page['props']['config']);
        $this->assertSame(['config.locale' => ['prop' => 'config.locale', 'expiresAt' => null]], $page['onceProps']);
    }

    public function testNestedOnceMetadataIsCollectedOnExactPartialRequest(): void
    {
        $page = $this->makePage($this->makePartialRequest('config.locale'), [
            'config' => [
                'locale' => new OnceProp(fn () => 'en'),
            ],
        ]);

        $this->assertSame('en', $page['props']['config']['locale']);
        $this->assertSame(['config.locale' => ['prop' => 'config.locale', 'expiresAt' => null]], $page['onceProps']);
    }

    public function testNestedOnceMetadataIsCollectedWhenParentIsRequested(): void
    {
        $page = $this->makePage($this->makePartialRequest('config'), [
            'config' => [
                'locale' => new OnceProp(fn () => 'en'),
            ],
        ]);

        $this->assertSame('en', $page['props']['config']['locale']);
        $this->assertSame(['config.locale' => ['prop' => 'config.locale', 'expiresAt' => null]], $page['onceProps']);
    }

    public function testNestedScrollPropMetadataIsCollected(): void
    {
        $page = $this->makePage($this->makeRequest('/'), [
            'feed' => [
                'posts' => new ScrollProp(
                    ['data' => [['id' => 1]]],
                    'data',
                    $this->makeScrollMetadata(),
                ),
            ],
        ]);

        $this->assertSame([
            'feed.posts' => [
                'pageName' => 'page',
                'previousPage' => null,
                'nextPage' => 2,
                'currentPage' => 1,
                'reset' => false,
            ],
        ], $page['scrollProps']);
    }

    public function testNestedDeferredScrollPropIsExcludedFromInitialLoad(): void
    {
        $page = $this->makePage($this->makeRequest('/'), [
            'feed' => [
                'posts' => new ScrollProp(
                    ['data' => [['id' => 1]]],
                    'data',
                    $this->makeScrollMetadata(),
                )->defer(),
            ],
        ]);

        $this->assertArrayNotHasKey('posts', $page['props']['feed'] ?? []);
        $this->assertSame(['default' => ['feed.posts']], $page['deferredProps']);
    }

    public function testNestedScrollPropIsIncludedOnPartialRequest(): void
    {
        $page = $this->makePage($this->makePartialRequest('feed.posts'), [
            'feed' => [
                'posts' => new ScrollProp(
                    ['data' => [['id' => 1]]],
                    'data',
                    $this->makeScrollMetadata(),
                ),
            ],
        ]);

        $this->assertSame(['data' => [['id' => 1]]], $page['props']['feed']['posts']);
        $this->assertSame([
            'feed.posts' => [
                'pageName' => 'page',
                'previousPage' => null,
                'nextPage' => 2,
                'currentPage' => 1,
                'reset' => false,
            ],
        ], $page['scrollProps']);
    }

    public function testNestedScrollPropResetFlagIsSetByResetHeader(): void
    {
        $request = $this->makeRequest('/');
        $request->headers->add(['X-Inertia-Reset' => 'feed.posts']);

        $page = $this->makePage($request, [
            'feed' => [
                'posts' => new ScrollProp(
                    ['data' => [['id' => 1]]],
                    'data',
                    $this->makeScrollMetadata(),
                ),
            ],
        ]);

        $this->assertTrue($page['scrollProps']['feed.posts']['reset']);
    }

    public function testNestedDeferOncePropSuppressesDeferredMetadataWhenAlreadyLoaded(): void
    {
        $request = $this->makeRequest('/');
        $request->headers->add(['X-Inertia-Except-Once-Props' => 'feed.posts']);

        $page = $this->makePage($request, [
            'feed' => [
                'posts' => new DeferProp(fn () => [])->once(),
            ],
        ]);

        $this->assertArrayNotHasKey('deferredProps', $page);
    }

    public function testNestedDeferOncePropIncludesDeferredMetadataOnFirstLoad(): void
    {
        $page = $this->makePage($this->makeRequest('/'), [
            'feed' => [
                'posts' => new DeferProp(fn () => [])->once(),
            ],
        ]);

        $this->assertSame(['default' => ['feed.posts']], $page['deferredProps']);
        $this->assertSame(['feed.posts' => ['prop' => 'feed.posts', 'expiresAt' => null]], $page['onceProps']);
    }

    public function testNestedPropsOnNonPartialInertiaRequestBehaveLikeInitialLoad(): void
    {
        $request = $this->makeRequest('/');
        $request->headers->add(['X-Inertia' => 'true']);

        $page = $this->makePage($request, [
            'dashboard' => [
                'stats' => 'visible',
                'feed' => new MergeProp([['id' => 1]]),
                'notifications' => new DeferProp(fn () => []),
                'settings' => new OptionalProp(fn () => []),
            ],
        ]);

        $this->assertSame('visible', $page['props']['dashboard']['stats']);
        $this->assertSame([['id' => 1]], $page['props']['dashboard']['feed']);
        $this->assertArrayNotHasKey('notifications', $page['props']['dashboard']);
        $this->assertArrayNotHasKey('settings', $page['props']['dashboard']);
        $this->assertSame(['dashboard.feed'], $page['mergeProps']);
        $this->assertSame(['default' => ['dashboard.notifications']], $page['deferredProps']);
    }

    public function testExceptHeaderSuppressesNestedMergeMetadata(): void
    {
        $request = $this->makeRequest('/');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Partial-Component' => 'TestComponent']);
        $request->headers->add(['X-Inertia-Partial-Data' => 'feed.posts,feed.comments']);
        $request->headers->add(['X-Inertia-Partial-Except' => 'feed.posts']);

        $page = $this->makePage($request, [
            'feed' => [
                'posts' => new MergeProp([['id' => 1]]),
                'comments' => new MergeProp([['id' => 2]]),
            ],
        ]);

        $this->assertArrayNotHasKey('posts', $page['props']['feed']);
        $this->assertSame([['id' => 2]], $page['props']['feed']['comments']);
        $this->assertSame(['feed.comments'], $page['mergeProps']);
    }

    public function testExceptHeaderForParentSuppressesAllNestedMetadata(): void
    {
        $request = $this->makeRequest('/');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Partial-Component' => 'TestComponent']);
        $request->headers->add(['X-Inertia-Partial-Data' => 'feed,other']);
        $request->headers->add(['X-Inertia-Partial-Except' => 'feed']);

        $page = $this->makePage($request, [
            'feed' => [
                'posts' => new MergeProp([['id' => 1]]),
            ],
            'other' => 'value',
        ]);

        $this->assertArrayNotHasKey('feed', $page['props']);
        $this->assertSame('value', $page['props']['other']);
        $this->assertArrayNotHasKey('mergeProps', $page);
    }

    public function testDeeplyNestedDeferPropIsExcludedWithMetadata(): void
    {
        $page = $this->makePage($this->makeRequest('/'), [
            'app' => [
                'auth' => [
                    'notifications' => new DeferProp(fn () => [], 'alerts'),
                ],
            ],
        ]);

        $this->assertArrayNotHasKey('notifications', $page['props']['app']['auth']);
        $this->assertSame(['alerts' => ['app.auth.notifications']], $page['deferredProps']);
    }

    public function testDeeplyNestedMergePropMetadataUsesFullPath(): void
    {
        $page = $this->makePage($this->makeRequest('/'), [
            'app' => [
                'feed' => [
                    'posts' => new MergeProp([['id' => 1]]),
                ],
            ],
        ]);

        $this->assertSame(['app.feed.posts'], $page['mergeProps']);
    }

    public function testDeeplyNestedOptionalPropIsIncludedOnPartialRequest(): void
    {
        $page = $this->makePage($this->makePartialRequest('app.auth.permissions'), [
            'app' => [
                'auth' => [
                    'permissions' => new OptionalProp(fn () => ['admin']),
                ],
            ],
        ]);

        $this->assertSame(['admin'], $page['props']['app']['auth']['permissions']);
    }

    public function testMultipleNestedPropTypesAreHandledTogether(): void
    {
        $page = $this->makePage($this->makeRequest('/'), [
            'dashboard' => [
                'stats' => 'visible',
                'feed' => new MergeProp([['id' => 1]]),
                'notifications' => new DeferProp(fn () => []),
                'settings' => new OptionalProp(fn () => []),
                'locale' => new OnceProp(fn () => 'en'),
            ],
        ]);

        $this->assertSame('visible', $page['props']['dashboard']['stats']);
        $this->assertSame([['id' => 1]], $page['props']['dashboard']['feed']);
        $this->assertSame('en', $page['props']['dashboard']['locale']);
        $this->assertArrayNotHasKey('notifications', $page['props']['dashboard']);
        $this->assertArrayNotHasKey('settings', $page['props']['dashboard']);
        $this->assertSame(['dashboard.feed'], $page['mergeProps']);
        $this->assertSame(['default' => ['dashboard.notifications']], $page['deferredProps']);
        $this->assertSame(['dashboard.locale' => ['prop' => 'dashboard.locale', 'expiresAt' => null]], $page['onceProps']);
    }

    public function testDeferredPropsAtMixedDepthsCollectCorrectMetadata(): void
    {
        $page = $this->makePage($this->makeRequest('/'), [
            'foo' => new DeferProp(fn () => 'bar'),
            'nested' => [
                'a' => 'b',
                'c' => new DeferProp(fn () => 'd'),
            ],
        ]);

        $this->assertSame('b', $page['props']['nested']['a']);
        $this->assertArrayNotHasKey('foo', $page['props']);
        $this->assertArrayNotHasKey('c', $page['props']['nested']);
        $this->assertSame(['default' => ['foo', 'nested.c']], $page['deferredProps']);
    }

    public function testDeferredPropsAtMixedDepthsResolveOnPartialRequest(): void
    {
        $page = $this->makePage($this->makePartialRequest('foo,nested.c'), [
            'foo' => new DeferProp(fn () => 'bar'),
            'nested' => [
                'a' => 'b',
                'c' => new DeferProp(fn () => 'd'),
            ],
        ]);

        $this->assertSame('bar', $page['props']['foo']);
        $this->assertSame('d', $page['props']['nested']['c']);
        $this->assertArrayNotHasKey('a', $page['props']['nested']);
        $this->assertArrayNotHasKey('deferredProps', $page);
    }

    public function testDotNotationPropMergesIntoExistingNestedStructure(): void
    {
        $page = $this->makePage($this->makeRequest('/'), [
            'auth' => [
                'user' => [
                    'name' => 'Jonathan Reinink',
                    'email' => 'jonathan@example.com',
                ],
            ],
            'auth.user.permissions' => fn () => ['edit-posts', 'delete-posts'],
        ]);

        $this->assertSame('Jonathan Reinink', $page['props']['auth']['user']['name']);
        $this->assertSame('jonathan@example.com', $page['props']['auth']['user']['email']);
        $this->assertSame(['edit-posts', 'delete-posts'], $page['props']['auth']['user']['permissions']);
        $this->assertArrayNotHasKey('auth.user.permissions', $page['props']);
    }

    public function testDotNotationPropMergesWhenParentIsAClosure(): void
    {
        $page = $this->makePage($this->makeRequest('/'), [
            'auth' => fn () => [
                'user' => [
                    'name' => 'Jonathan Reinink',
                    'email' => 'jonathan@example.com',
                ],
            ],
            'auth.user.permissions' => fn () => ['edit-posts', 'delete-posts'],
        ]);

        $this->assertSame('Jonathan Reinink', $page['props']['auth']['user']['name']);
        $this->assertSame('jonathan@example.com', $page['props']['auth']['user']['email']);
        $this->assertSame(['edit-posts', 'delete-posts'], $page['props']['auth']['user']['permissions']);
    }

    public function testDotNotationOptionalPropIsExcludedFromInitialLoad(): void
    {
        $page = $this->makePage($this->makeRequest('/'), [
            'auth' => [
                'user' => [
                    'name' => 'Jonathan Reinink',
                    'email' => 'jonathan@example.com',
                ],
            ],
            'auth.user.permissions' => new OptionalProp(fn () => ['edit-posts', 'delete-posts']),
        ]);

        $this->assertSame('Jonathan Reinink', $page['props']['auth']['user']['name']);
        $this->assertSame('jonathan@example.com', $page['props']['auth']['user']['email']);
        $this->assertArrayNotHasKey('permissions', $page['props']['auth']['user']);
        $this->assertArrayNotHasKey('auth.user.permissions', $page['props']);
    }

    public function testDotNotationOptionalPropIsIncludedOnPartialRequest(): void
    {
        $page = $this->makePage($this->makePartialRequest('auth.user.permissions'), [
            'auth' => [
                'user' => [
                    'name' => 'Jonathan Reinink',
                    'email' => 'jonathan@example.com',
                ],
            ],
            'auth.user.permissions' => new OptionalProp(fn () => ['edit-posts', 'delete-posts']),
        ]);

        $this->assertSame(['edit-posts', 'delete-posts'], $page['props']['auth']['user']['permissions']);
        $this->assertArrayNotHasKey('auth.user.permissions', $page['props']);
    }

    public function testOptionalPropsInsideIndexedArraysAreResolvedOnPartialRequest(): void
    {
        $page = $this->makePage($this->makePartialRequest('foos'), [
            'foos' => [
                [
                    'name' => 'First',
                    'bar' => new OptionalProp(fn () => 'expensive-data-1'),
                ],
                [
                    'name' => 'Second',
                    'bar' => new OptionalProp(fn () => 'expensive-data-2'),
                ],
            ],
        ]);

        $this->assertSame('First', $page['props']['foos'][0]['name']);
        $this->assertSame('expensive-data-1', $page['props']['foos'][0]['bar']);
        $this->assertSame('Second', $page['props']['foos'][1]['name']);
        $this->assertSame('expensive-data-2', $page['props']['foos'][1]['bar']);
    }

    public function testOptionalPropsInsideIndexedArraysAreExcludedFromInitialLoad(): void
    {
        $resolved = false;

        $page = $this->makePage($this->makeRequest('/'), [
            'foos' => [
                [
                    'name' => 'First',
                    'bar' => new OptionalProp(function () use (&$resolved) {
                        $resolved = true;

                        return 'expensive-data';
                    }),
                ],
            ],
        ]);

        $this->assertSame('First', $page['props']['foos'][0]['name']);
        $this->assertArrayNotHasKey('bar', $page['props']['foos'][0]);
        $this->assertFalse($resolved, 'OptionalProp closure should not be resolved on initial load');
    }

    public function testDeferredPropsInsideClosureAreExcludedFromInitialLoad(): void
    {
        $notificationsResolved = false;
        $rolesResolved = false;

        $page = $this->makePage($this->makeRequest('/'), [
            'auth' => fn () => [
                'user' => [
                    'name' => 'Jonathan Reinink',
                    'email' => 'jonathan@example.com',
                ],
                'notifications' => new DeferProp(function () use (&$notificationsResolved) {
                    $notificationsResolved = true;

                    return ['You have a new follower'];
                }),
                'roles' => new DeferProp(function () use (&$rolesResolved) {
                    $rolesResolved = true;

                    return ['admin'];
                }),
            ],
        ]);

        $this->assertSame('Jonathan Reinink', $page['props']['auth']['user']['name']);
        $this->assertArrayNotHasKey('notifications', $page['props']['auth']);
        $this->assertArrayNotHasKey('roles', $page['props']['auth']);
        $this->assertSame(['default' => ['auth.notifications', 'auth.roles']], $page['deferredProps']);
        $this->assertFalse($notificationsResolved, 'DeferProp closure should not be resolved on initial load'); // @phpstan-ignore method.impossibleType
        $this->assertFalse($rolesResolved, 'DeferProp closure should not be resolved on initial load'); // @phpstan-ignore method.impossibleType
    }

    public function testDeferredPropsInsideClosureAreResolvedOnPartialRequest(): void
    {
        $page = $this->makePage($this->makePartialRequest('auth.notifications,auth.roles'), [
            'auth' => fn () => [
                'user' => [
                    'name' => 'Jonathan Reinink',
                    'email' => 'jonathan@example.com',
                ],
                'notifications' => new DeferProp(fn () => ['You have a new follower']),
                'roles' => new DeferProp(fn () => ['admin']),
            ],
        ]);

        $this->assertSame(['You have a new follower'], $page['props']['auth']['notifications']);
        $this->assertSame(['admin'], $page['props']['auth']['roles']);
    }

    public function testArraysMatchingCallableSyntaxAreNotInvoked(): void
    {
        $page = $this->makePage($this->makeRequest('/'), [
            'job' => [
                'name' => 'Import',
                'fields' => ['Context', 'comment'],
            ],
        ]);

        $this->assertSame(['Context', 'comment'], $page['props']['job']['fields']);
    }

    public function testItDescendsIntoJsonSerializableProps(): void
    {
        $dto = new class implements \JsonSerializable {
            public function jsonSerialize(): mixed
            {
                return ['nested' => ['name' => 'John']];
            }
        };

        $page = $this->makePage($this->makeRequest('/'), ['dto' => $dto]);

        $this->assertSame(['nested' => ['name' => 'John']], $page['props']['dto']);
    }

    public function testPropTypesNestedInJsonSerializablePropsAreResolved(): void
    {
        $props = fn () => [
            'dto' => new class implements \JsonSerializable {
                public function jsonSerialize(): mixed
                {
                    return ['thing' => new DeferProp(fn () => 'deferred value'), 'plain' => 1];
                }
            },
        ];

        $page = $this->makePage($this->makeRequest('/'), $props());

        $this->assertSame(['plain' => 1], $page['props']['dto']);
        $this->assertSame(['default' => ['dto.thing']], $page['deferredProps']);

        $request = $this->makeRequest('/');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Partial-Component' => 'TestComponent']);
        $request->headers->add(['X-Inertia-Partial-Data' => 'dto.thing']);

        $page = $this->makePage($request, $props());

        $this->assertSame('deferred value', $page['props']['dto']['thing']);
    }

    public function testBigIntegersInsidePlainObjectsAreWrappedWhenEnabled(): void
    {
        $page = $this->makePage($this->makeRequest('/'), [
            'object' => (object) ['id' => 900719925474099988],
            'dto' => new class implements \JsonSerializable {
                public function jsonSerialize(): mixed
                {
                    return ['id' => 900719925474099988];
                }
            },
        ], preserveBigIntegers: true);

        $this->assertSame(['$bigint' => '900719925474099988'], $page['props']['object']['id']);
        $this->assertSame(['$bigint' => '900719925474099988'], $page['props']['dto']['id']);
    }

    public function testBigIntegersInsideArbitraryObjectsAreWrappedWhenEnabled(): void
    {
        $money = new class {
            public int $cents = 900719925474099988;
        };

        $page = $this->makePage($this->makeRequest('/'), [
            'money' => $money,
            'nested' => ['money' => $money],
            'wrapped' => (object) ['money' => $money],
            'collection' => new \ArrayObject(['id' => 900719925474099988]),
        ], preserveBigIntegers: true);

        $marker = ['$bigint' => '900719925474099988'];

        $this->assertSame($marker, $page['props']['money']['cents']);
        $this->assertSame($marker, $page['props']['nested']['money']['cents']);
        $this->assertSame($marker, $page['props']['wrapped']['money']['cents']);
        $this->assertSame($marker, $page['props']['collection']['id']);
    }

    public function testSelfReferencingObjectsDoNotRecurseForever(): void
    {
        $cyclic = new \stdClass();
        $cyclic->id = 900719925474099988;
        $cyclic->self = $cyclic;

        // A cyclic value cannot be encoded at all, so read the unencoded page.
        $response = new InertiaResponse('TestComponent', [], ['cyclic' => $cyclic], 'app.html.twig', '123', preserveBigIntegers: true);
        $page = $response->toPage($this->makeRequest('/'));

        $this->assertSame(['$bigint' => '900719925474099988'], $page['props']['cyclic']->id);
    }

    public function testAnObjectSharedByTwoPropsIsWrappedInBoth(): void
    {
        $shared = (object) ['id' => 900719925474099988];

        $page = $this->makePage($this->makeRequest('/'), ['first' => $shared, 'second' => $shared], preserveBigIntegers: true);

        $marker = ['$bigint' => '900719925474099988'];

        $this->assertSame($marker, $page['props']['first']['id']);
        $this->assertSame($marker, $page['props']['second']['id']);
    }

    public function testInternalObjectsKeepTheShapeJsonEncodeGivesThem(): void
    {
        $this->makePage($this->makeRequest('/'), [
            'when' => ['at' => new \DateTimeImmutable('2020-01-01T00:00:00Z')],
            'extended' => new class('2020-01-01T00:00:00Z') extends \DateTimeImmutable {},
            'bag' => new class(['name' => 'John']) extends \ArrayObject {},
        ], preserveBigIntegers: true);

        // Walking its properties would flatten it to an empty object.
        $this->assertStringContainsString(
            '"when":{"at":{"date":"2020-01-01 00:00:00.000000","timezone_type":2,"timezone":"Z"}}',
            $this->pageJson,
        );
        $this->assertStringContainsString(
            '"extended":{"date":"2020-01-01 00:00:00.000000","timezone_type":2,"timezone":"Z"}',
            $this->pageJson,
        );
        $this->assertStringContainsString('"bag":{"name":"John"}', $this->pageJson);
    }

    public function testBigIntegersInsideNumericallyKeyedObjectsAreWrappedWithoutChangingTheShape(): void
    {
        $this->makePage($this->makeRequest('/'), [
            'list' => (object) ['0' => 900719925474099988, '1' => -900719925474099988],
            'empty' => new \stdClass(),
        ], preserveBigIntegers: true);

        // Asserted on the encoded JSON: decoding into PHP arrays would turn the object into a list.
        $this->assertStringContainsString(
            '"list":{"0":{"$bigint":"900719925474099988"},"1":{"$bigint":"-900719925474099988"}}',
            $this->pageJson,
        );
        $this->assertStringContainsString('"empty":{}', $this->pageJson);
    }

    public function testBigIntegersAreWrappedWhenEnabled(): void
    {
        $page = $this->makePage($this->makeRequest('/'), [
            'safe' => 42,
            'boundary' => 9007199254740991,
            'negativeBoundary' => -9007199254740991,
            'big' => 900719925474099988,
            'negative' => -900719925474099988,
            'nested' => ['deep' => [900719925474099988, 2]],
        ], preserveBigIntegers: true);

        $this->assertSame(42, $page['props']['safe']);
        $this->assertSame(9007199254740991, $page['props']['boundary']);
        $this->assertSame(-9007199254740991, $page['props']['negativeBoundary']);
        $this->assertSame(['$bigint' => '900719925474099988'], $page['props']['big']);
        $this->assertSame(['$bigint' => '-900719925474099988'], $page['props']['negative']);
        $this->assertSame(['$bigint' => '900719925474099988'], $page['props']['nested']['deep'][0]);
        $this->assertSame(2, $page['props']['nested']['deep'][1]);
    }

    public function testBigIntegersAreNotWrappedWhenDisabled(): void
    {
        $page = $this->makePage($this->makeRequest('/'), [
            'big' => 900719925474099988,
        ]);

        $this->assertSame(900719925474099988, $page['props']['big']);
    }

    /**
     * The JSON-encoded page from the last {@see self::makePage()} call, for
     * assertions about object shape that decoding into PHP arrays would lose.
     */
    private string $pageJson = '';

    /**
     * Resolve the given props through the Inertia response and return the page data.
     *
     * @param array<string, mixed> $props
     *
     * @return array<string, mixed>
     */
    protected function makePage(Request $request, array $props, bool $preserveBigIntegers = false): array
    {
        if ($preserveBigIntegers) {
            $this->configure(['preserve_big_integers' => true]);
        }

        $this->pushRequest($request);
        $this->inertia()->version('123');

        $response = $this->inertia()->render('TestComponent', $props)->toResponse($request);
        $page = $this->getPage($response);

        if ($response->headers->has('X-Inertia')) {
            $this->pageJson = (string) $response->getContent();
        } elseif (preg_match('#<script data-page="[^"]*" type="application/json">(.*?)</script>#s', (string) $response->getContent(), $matches)) {
            $this->pageJson = $matches[1];
        }

        return $page;
    }

    /**
     * Create a partial Inertia request for the given props.
     */
    protected function makePartialRequest(string $only): Request
    {
        $request = $this->makeRequest('/');
        $request->headers->add(['X-Inertia' => 'true']);
        $request->headers->add(['X-Inertia-Partial-Component' => 'TestComponent']);
        $request->headers->add(['X-Inertia-Partial-Data' => $only]);

        return $request;
    }

    /**
     * Create a scroll metadata provider for testing.
     */
    protected function makeScrollMetadata(): ProvidesScrollMetadataInterface
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
}
