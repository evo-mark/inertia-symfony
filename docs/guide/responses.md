# Pages and responses

## Rendering

```php
return $inertia->render('Users/Show', ['user' => $user]);
```

`render()` returns a response object that is turned into a Symfony response after
your controller returns, so you can keep changing it first:

```php
return $inertia->render('Users/Show')
    ->with('user', $user)               // add props
    ->withViewData('title', 'Profile')  // variables for the root Twig template
    ->rootView('admin.html.twig')       // use a different root template
    ->flash('status', 'Saved');         // one-time data, see below
```

`render()` accepts these arguments: `$component`, `$props`, `$viewData`, `$context`
and `$url`. The component can also be a backed or unit enum.

For pages with fixed props and no logic of their own, you can skip the controller
and render them straight from a route: see [Pages without a controller](./routes).

Props can be a plain array, a `JsonSerializable`, or a
[props provider](./props#props-providers). Objects are normalised with the Symfony
Serializer when it is installed. Pass extra Serializer context as the fourth
argument (or with `->serializationContext([...])`).

### Component names

If `pages.ensure_pages_exist` is on, rendering a component that has no file under
the configured page paths throws `ComponentNotFoundException`. To rewrite names
before they are rendered:

```php
$inertia->transformComponentUsing(fn (string $component) => 'Admin/'.$component);
```

### The page URL

The page URL defaults to the request URI. Override it with:

```php
$inertia->resolveUrlUsing(fn (Request $request) => $request->getRequestUri());
```

## Redirects

Return normal Symfony redirects. The bundle makes them work with Inertia:

- A redirect after a `PUT`, `PATCH`, `DELETE` or `QUERY` request becomes a `303`, so the
  browser follows it with a `GET`.
- A controller that returns an empty `200` to an Inertia request redirects back.
- A redirect to a URL with a `#fragment` becomes a `409` with an `X-Inertia-Redirect`
  header, because browsers drop the fragment on XHR redirects. Call
  `$inertia->preserveFragment()` to keep the fragment of the current URL.

### Back

```php
return $inertia->back();
return $inertia->back(fallback: '/dashboard');
```

`back()` redirects to the `Referer` header, then to the previously stored URL
(when [`store_previous_url`](/reference/configuration#store-previous-url) is on), then to
the `fallback`, then to `/`.

### External locations

For a full page visit to another site, or to a non-Inertia page:

```php
return $inertia->location('https://example.com');
```

For Inertia requests this is a `409` with `X-Inertia-Location`. Other requests get a
plain redirect.

## Flash data

```php
$inertia->flash('toast', 'Profile updated');
$inertia->flash(['toast' => 'Updated', 'level' => 'success']);
```

Flash data is stored in the session and delivered in the next page's `flash` key.
Unlike props, it is not kept in the browser's history state, which makes it a good
fit for one-time messages. Read it yourself with `getFlashed()` or `pullFlashed()`
(which removes it).

## History

```php
$inertia->clearHistory();        // clear the browser history on the next visit
$inertia->encryptHistory();      // encrypt this page's history entry
$inertia->encryptHistory(false); // opt out when history.encrypt is on
```

Set [`history.encrypt`](/reference/configuration#history) to encrypt every page.

### Encrypting history per route

Encrypted history keeps a page's props out of the browser's history state, so they
can't be read back after the user logs out, for example by pressing Back. To
encrypt the pages of particular controllers or actions, add `#[EncryptHistory]`:

```php
use EvoMark\InertiaSymfonyBundle\Attribute\EncryptHistory;

#[EncryptHistory]
final class AccountController extends AbstractController
{
    #[Route('/account', name: 'app_account')]
    public function show(InertiaInterface $inertia): InertiaResponse
    {
        return $inertia->render('Account/Show'); // encrypted
    }

    #[EncryptHistory(false)]
    #[Route('/account/help', name: 'app_account_help')]
    public function help(InertiaInterface $inertia): InertiaResponse
    {
        return $inertia->render('Account/Help'); // not encrypted
    }
}
```

On a class, the attribute applies to every action, and an action's own attribute
overrides it. `#[EncryptHistory(false)]` also opts an action out when
`history.encrypt` is on for the whole app.

Routes without a controller of their own, such as those rendered by
[`InertiaController`](./routes), can't carry an attribute. Set the
`_encrypt_history` route default instead:

```php
$routes->add('app_privacy', '/privacy')
    ->controller(InertiaController::class)
    ->defaults(['component' => 'Privacy', '_encrypt_history' => true]);
```

The route default works on any route, and wins over the controller's attribute,
since one controller can serve several routes.

From most to least specific, the first that applies decides:

1. `$inertia->encryptHistory()` called while handling the request
2. the `_encrypt_history` route default
3. `#[EncryptHistory]` on the action
4. `#[EncryptHistory]` on the controller class
5. the `history.encrypt` config
