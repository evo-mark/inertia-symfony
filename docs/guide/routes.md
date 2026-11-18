# Pages without a controller

Some pages need no logic of their own: an About page, a privacy policy, a landing
page with fixed content. Rather than write a controller that only calls
`$inertia->render()`, point a route at `InertiaController` and give it the
component and props as route defaults.

It is the Inertia counterpart of Symfony's
[`TemplateController`](https://symfony.com/doc/current/templates.html#rendering-a-template-directly-from-a-route),
and takes the same caching, status code and header options.

## Defining the route

In `config/routes.php` (or any PHP routing file):

```php
use EvoMark\InertiaSymfonyBundle\Controller\InertiaController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->add('app_about', '/about')
        ->controller(InertiaController::class)
        ->methods(['GET'])
        ->defaults([
            'component' => 'About',
            'props' => [
                'team' => 'Platform',
                'founded' => 2019,
            ],
        ]);
};
```

The same route in YAML:

```yaml
# config/routes.yaml
app_about:
    path: /about
    methods: GET
    controller: EvoMark\InertiaSymfonyBundle\Controller\InertiaController
    defaults:
        component: About
        props:
            team: Platform
            founded: 2019
```

`/about` now renders the `About` page with those props, exactly as a controller
returning `$inertia->render('About', [...])` would.

## Options

Each option is a route default. Only `component` is required.

| Default      | Type      | Description                                                            |
| ------------ | --------- | ---------------------------------------------------------------------- |
| `component`  | string    | The page component to render.                                          |
| `props`      | array     | The page's props.                                                      |
| `viewData`   | array     | Variables for the root Twig template (not sent to the client).         |
| `maxAge`     | int       | Max age, in seconds, for client caching (`Cache-Control: max-age`).    |
| `sharedAge`  | int       | Max age, in seconds, for shared caches such as proxies (`s-maxage`).   |
| `private`    | bool      | `true` limits caching to the client; `false` allows shared caches.     |
| `statusCode` | int       | The response status code. Defaults to `200`.                           |
| `headers`    | array     | Extra response headers.                                                |

The `_encrypt_history` route default encrypts the page's history entry, as
[`#[EncryptHistory]`](./responses#encrypting-history-per-route) does on a controller.

Caching follows the same rules as `TemplateController`: setting `maxAge` or
`sharedAge` makes the response public unless `private` is `true`.

```php
$routes->add('app_pricing', '/pricing')
    ->controller(InertiaController::class)
    ->defaults([
        'component' => 'Pricing',
        'props' => ['plans' => ['Starter', 'Team', 'Enterprise']],
        'maxAge' => 600,
        'sharedAge' => 3600,
    ]);

$routes->add('app_gone', '/old-campaign')
    ->controller(InertiaController::class)
    ->defaults([
        'component' => 'Gone',
        'statusCode' => 410,
        'headers' => ['X-Robots-Tag' => 'noindex'],
        'viewData' => ['title' => 'This page has gone'],
    ]);
```

The status code and headers apply to both kinds of response: the full HTML page on
a first visit, and the JSON page object on an Inertia visit.

## What still applies

The page goes through the same rendering as any other, so everything else works as
usual:

- **Shared props**, from `$inertia->share()` or your Inertia listener, are merged in.
- **Partial reloads** (`router.reload({ only: [...] })`) return only the props asked for.
- **Asset versioning**, **server-side rendering**, **history** and **flash data**
  behave as they do for controller-rendered pages.
- **Caching and Inertia visits:** the bundle adds `Vary: X-Inertia` to every
  response, so a cache keeps the HTML page and the JSON page object apart.

## Limitations

Route defaults are compiled into Symfony's routing cache, so `props` can only hold
values that can be stored there: strings, numbers, booleans, `null` and arrays of
them. You can't use closures, objects or the prop types (`$inertia->defer()`,
`$inertia->optional()`, ...) in a route definition.

When a page needs data from the request, the database or a service, write a
controller instead:

```php
#[Route('/team', name: 'app_team', methods: ['GET'])]
public function team(InertiaInterface $inertia, MemberRepository $members): InertiaResponse
{
    return $inertia->render('Team', ['members' => $members->findAll()]);
}
```
