# Symfony Flex recipe (draft)

`evo-mark/inertia-symfony/1.0/` is the Flex recipe for this bundle, laid out as
[symfony/recipes-contrib](https://github.com/symfony/recipes-contrib) expects.
It is not part of the package: Flex reads recipes from a recipe repository.

On `composer require evo-mark/inertia-symfony` it:

- registers the bundle in `config/bundles.php`;
- creates `config/packages/evo_mark_inertia_symfony.yaml`, reading SSR settings from env
  vars and turning SSR off in the test environment;
- copies the package's root template stub to `templates/app.html.twig` (the file
  `bin/console inertia:view` also publishes), unless the app already has one;
- adds `INERTIA_SSR_ENABLED` and `INERTIA_SSR_URL` to `.env`;
- prints the next steps from `post-install.txt`.

`composer remove` reverses it, and `composer recipes:update` diffs later versions.

## Publishing

1. Tag a release on Packagist. The version folder (`1.0`) is the first version the
   recipe applies to; rename it if the first release is a `0.x`, and add a new
   folder (e.g. `2.0`) only when a release needs different recipe files.
2. Copy `evo-mark/` into a fork of `symfony/recipes-contrib` and open a pull
   request. Its CI validates the manifest. Flex asks users to confirm contrib
   recipes the first time they install one.

To try it before then, push the folder to a repository of your own, publish it as
a Flex endpoint with the
[recipes-checker](https://github.com/symfony-tools/recipes-checker) GitHub Action,
and point a test app at it:

```json
"extra": {
    "symfony": {
        "endpoint": ["https://api.github.com/repos/evo-mark/recipes/contents/index.json", "flex://defaults"]
    }
}
```

Then run `composer recipes:install evo-mark/inertia-symfony --force -v`.
