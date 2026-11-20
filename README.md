<p align="center">
    <a href="https://evomark.co.uk" target="_blank" alt="Link to evoMark's website">
        <picture>
          <source media="(prefers-color-scheme: dark)" srcset="https://evomark.co.uk/wp-content/uploads/static/evomark-logo--dark.svg">
          <source media="(prefers-color-scheme: light)" srcset="https://evomark.co.uk/wp-content/uploads/static/evomark-logo--light.svg">
          <img alt="evoMark company logo" src="https://evomark.co.uk/wp-content/uploads/static/evomark-logo--light.svg" width="500">
        </picture>
    </a>
</p>

<p align="center">
  <a href="https://packagist.org/packages/evo-mark/inertia-symfony"><img src="https://img.shields.io/packagist/dm/evo-mark/inertia-symfony.svg" alt="Downloads"></a>
  <a href="https://packagist.org/packages/evo-mark/inertia-symfony"><img src="https://img.shields.io/packagist/v/evo-mark/inertia-symfony.svg" alt="Version"></a>
  <a href="https://github.com/evo-mark/inertia-symfony/blob/main/LICENSE"><img src="https://img.shields.io/github/license/evo-mark/inertia-symfony?style=flat" alt="Licence"></a>
</p>

# evo-mark/inertia-symfony

A Symfony bundle for building [Inertia.js](https://inertiajs.com) applications,
including server-side rendering (SSR).

Requires PHP 8.4+ and Symfony 8.

## Installation

Make sure Composer is installed globally, as explained in the
[installation chapter](https://getcomposer.org/doc/00-intro.md) of the Composer
documentation.

### Applications that use Symfony Flex

Open a command console, enter your project directory and run:

```sh
composer require evo-mark/inertia-symfony
```

### Applications that don't use Symfony Flex

#### Step 1: Download the bundle

Open a command console, enter your project directory and run this command to
download the latest stable version of the bundle:

```sh
composer require evo-mark/inertia-symfony
```

#### Step 2: Enable the bundle

Enable the bundle by adding it to the list of registered bundles in your project's
`config/bundles.php` file:

```php
// config/bundles.php

return [
    // ...
    EvoMark\InertiaSymfonyBundle\EvoMarkInertiaSymfonyBundle::class => ['all' => true],
];
```

Then set up the frontend and the root template: see
[Getting started](https://evo-mark.github.io/inertia-symfony/guide/getting-started).

## Usage

Render a page from a controller by injecting `InertiaInterface`:

```php
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

Create the root template with `bin/console inertia:view` (add `--force` to
overwrite). It is named after the `root_view` setting, `app.html.twig` by default,
and outputs the page with
the `inertiaHead()` and `inertiaApp()` Twig functions:

```twig
<head>
    {{ inertiaHead(page) }}
</head>
<body>
    {{ inertiaApp(page) }}
</body>
```

## Configuration

Settings live under `evo_mark_inertia_symfony` in `config/packages/evo_mark_inertia_symfony.yaml`.
All of them are optional:

```yaml
evo_mark_inertia_symfony:
    root_view: app.html.twig
    ssr:
        enabled: false
        url: http://127.0.0.1:13714
        throw_on_error: false
```

Run `bin/console config:dump-reference evo_mark_inertia_symfony` to see every option.

## Testing

```sh
composer test
```

## Licence

Released under the [MIT licence](LICENSE).
