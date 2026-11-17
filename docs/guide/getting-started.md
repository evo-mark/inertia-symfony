# Getting started

## Requirements

- PHP 8.4 or later
- Symfony 8
- Twig, for the root template

## Install

```sh
composer require evo-mark/inertia-symfony
```

The bundle class is `EvoMark\InertiaSymfonyBundle\EvoMarkInertiaSymfonyBundle`. If
it is not already listed in `config/bundles.php`, add it:

```php
return [
    // ...
    EvoMark\InertiaSymfonyBundle\EvoMarkInertiaSymfonyBundle::class => ['all' => true],
];
```

You also need the client side of Inertia (`@inertiajs/vue3`, `@inertiajs/react`
or `@inertiajs/svelte`) and a bundler to build it. Any bundler works.

## The root template

Create the root template with the bundled command:

```sh
bin/console inertia:view
```

It writes `templates/app.html.twig`, named after the [`root_view`](/reference/configuration#root-view)
setting. Pass `--force` to overwrite an existing file. The template looks like this:

```twig
<!DOCTYPE html>
<html lang="{{ app.request.locale|default('en')|replace({'_': '-'}) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {% block stylesheets %}
        {{ reprise_entry_link_tags('app') }}
    {% endblock %}
    {{ inertiaHead(page) }}
</head>
<body>
{{ inertiaApp(page) }}
{% block javascripts %}
    {{ reprise_entry_script_tags('app') }}
{% endblock %}
</body>
</html>
```

The `reprise_entry_*` functions come from [Symfony Reprise](https://github.com/symfony/reprise).
If you use a different bundler, replace them with your own asset tags.

`inertiaHead(page)` and `inertiaApp(page)` are provided by this bundle:

| Function | Output |
| --- | --- |
| `inertiaHead(page)` | The `<head>` tags from [server-side rendering](./ssr), or nothing when SSR is off. |
| `inertiaApp(page, id = 'app')` | The server-rendered app, or the page data and the `<div id="app">` the client mounts on. |

## Your first page

Render a page from a controller by injecting `InertiaInterface`:

```php
namespace App\Controller;

use EvoMark\InertiaSymfonyBundle\Interfaces\InertiaInterface;
use Symfony\Component\Routing\Attribute\Route;

class HomeController
{
    #[Route('/', name: 'home')]
    public function __invoke(InertiaInterface $inertia)
    {
        return $inertia->render('Home', ['title' => 'Welcome']);
    }
}
```

`Home` is the component name. By default components are looked up in
`assets/pages` (see [`pages`](/reference/configuration#pages)), for example
`assets/pages/Home.vue`:

```vue
<script setup>
defineProps({ title: String });
</script>

<template>
    <h1>{{ title }}</h1>
</template>
```

Then boot the client app from your entry file:

```js
import { createInertiaApp } from "@inertiajs/vue3";

createInertiaApp({
    resolve: (name) => import(`./pages/${name}.vue`),
});
```

The exact client setup depends on your framework and bundler. See the
[Inertia documentation](https://inertiajs.com) for details.

## Asset versioning

Inertia tells the browser to do a full reload when your assets change. The version
is, in order of preference:

1. the `version` config value, or `$inertia->version(...)`
2. a hash of `asset_url`, when set
3. a hash of the build manifest at `public/build/manifest.json`

A request carrying an old version receives a `409` and the client reloads the page
for you.
