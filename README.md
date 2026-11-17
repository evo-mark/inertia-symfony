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
  <a href="https://github.com/evo-mark/inertia-symfony/blob/main/LICENCE"><img src="https://img.shields.io/github/license/evo-mark/inertia-symfony?style=flat" alt="Licence"></a>
</p>

# evo-mark/inertia-symfony

A Symfony bundle for building [Inertia.js](https://inertiajs.com) applications,
including server-side rendering (SSR).

Requires PHP 8.4+ and Symfony 8.

## Installation

```sh
composer require evo-mark/inertia-symfony
```

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

Settings live under `inertia_symfony` in `config/packages/inertia_symfony.yaml`.
All of them are optional:

```yaml
inertia_symfony:
    root_view: app.html.twig
    ssr:
        enabled: false
        url: http://127.0.0.1:13714
        throw_on_error: false
```

Run `bin/console config:dump-reference inertia_symfony` to see every option.

## Testing

```sh
composer test
```

## Licence

Released under the [MIT licence](LICENCE).
