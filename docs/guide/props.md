# Props

Every prop is a key and a value. Wrap a value in one of the helpers below, all
available on `InertiaInterface`, to change when and how it reaches the client.

```php
return $inertia->render('Dashboard', [
    'user' => $user,                                    // always sent
    'stats' => fn () => $this->stats->forUser($user),   // resolved when the page is built
    'audit' => $inertia->optional(fn () => $this->audit->all()),
]);
```

A plain closure is called when the page is built, and only for props the client
asked for on a [partial reload](https://inertiajs.com/partial-reloads).

## Shared props

Props shared with every page:

```php
$inertia->share('appName', 'My app');
$inertia->share(['locale' => 'en', 'auth.user' => $user]); // dot notation nests
$inertia->shareOnce('permissions', fn () => $this->permissions->all());
```

A good place to share props is an event listener. Generate one with:

```sh
bin/console inertia:subscriber
```

This creates `src/EventSubscriber/InertiaSubscriber.php`, a listener on `ControllerEvent`
that calls `share()`. Pass a name to choose the class, and `--force` to overwrite it.

Use `getShared()` to read shared props and `flushShared()` to clear them.

## Optional props

```php
'comments' => $inertia->optional(fn () => $post->comments()),
```

Only sent when a partial reload asks for them by name. They are left out of the first
page load.

## Deferred props

```php
'permissions' => $inertia->defer(fn () => $this->permissions->all()),
'stats' => $inertia->defer(fn () => $this->stats->all(), group: 'analytics'),
```

Left out of the first page load, then loaded by the client straight after it renders.
Deferred props with the same `group` are fetched in a single request.

Pass `rescue: true` to let a failing deferred prop fail on its own, without
breaking the rest of the response.

## Merge props

```php
'messages' => $inertia->merge($newMessages),
'settings' => $inertia->deepMerge($settings),
```

The client appends the new value to the existing one instead of replacing it.
`merge()` merges at the top level and `deepMerge()` merges nested data. Tune the
strategy by chaining:

```php
$inertia->merge($items)->append('data')->matchOn('id');
$inertia->merge($items)->prepend('data');
```

## Once props

```php
'countries' => $inertia->once(fn () => $this->countries->all()),
```

Resolved once and then remembered by the client, so later visits don't send them
again. Use `shareOnce()` to share one with every page.

## Always props

```php
'flash' => $inertia->always($flash),
```

Included even on a partial reload that doesn't ask for them. The built-in
`errors` prop is one.

## Scroll props

For infinite scrolling:

```php
'posts' => $inertia->scroll($posts, wrapper: 'data', metadata: new PostsPage($posts)),
```

`metadata` describes the pagination state. Symfony has no paginator of its own, so
implement `ProvidesScrollMetadataInterface` on an adapter around whichever one you use
(Doctrine, Pagerfanta, KnpPaginator...):

```php
use EvoMark\InertiaSymfonyBundle\Interfaces\ProvidesScrollMetadataInterface;

final class PostsPage implements ProvidesScrollMetadataInterface
{
    public function __construct(private Paginator $paginator) {}

    // getPageName(), getPreviousPage(), getNextPage(), getCurrentPage()
}
```

`metadata` can also be a callable that receives the value and returns the provider.

## Props providers

To contribute several props at once, implement `ProvidesInertiaPropertiesInterface`:

```php
use EvoMark\InertiaSymfonyBundle\Interfaces\ProvidesInertiaPropertiesInterface;
use EvoMark\InertiaSymfonyBundle\Support\RenderContext;

final class UserProps implements ProvidesInertiaPropertiesInterface
{
    public function toInertiaProperties(RenderContext $context): iterable
    {
        yield 'user' => $this->user;
        yield 'roles' => $this->roles;
    }
}

return $inertia->render('Profile', new UserProps(/* ... */));
```

A provider can be passed as the props argument, or without a key inside the props
array, or to `share()` / `with()`.

## Large integers

JavaScript can't represent integers above 2^53 exactly. With
[`preserve_big_integers`](/reference/configuration#preserve-big-integers) on, big
integers are sent as strings so they survive the trip.
