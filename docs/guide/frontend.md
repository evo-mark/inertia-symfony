# Frontend setup

This bundle is bundler-agnostic, but the smoothest setup is Vite with
[Symfony Reprise](https://github.com/symfony/reprise) and
[`@evomark/inertia-reprise`](https://github.com/evo-mark/inertia-reprise). The package is a
Vite plugin that wires Reprise and the Inertia Vite plugin together and sets sensible
defaults for client and SSR builds.

## Install

On the PHP side, install Reprise, which provides the `reprise_entry_*` Twig functions:

```sh
composer require symfony/reprise
```

Then the JavaScript packages. Vue is shown here; swap in the adapter and plugin for
React or Svelte:

```sh
pnpm add -D vite @evomark/inertia-reprise @symfony/reprise @inertiajs/vite @vitejs/plugin-vue
pnpm add @inertiajs/vue3 vue
```

`vite` (8), `@symfony/reprise` and `@inertiajs/vite` are peer dependencies of the
plugin, so they have to be installed in your project.

## Vite config

```js
// vite.config.js
import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import inertiaReprise from "@evomark/inertia-reprise/vite";

export default defineConfig({
    plugins: [vue(), inertiaReprise()],
});
```

The plugin is not tied to a UI framework. Add the plugin for yours (`@vitejs/plugin-vue`,
`@vitejs/plugin-react`, ...) next to it, along with anything else you use, such as
Tailwind.

## Entry file

By default the plugin builds `assets/app.js`:

```js
// assets/app.js
import { createInertiaApp } from "@inertiajs/vue3";
import "./styles/app.css";

createInertiaApp();
```

The Inertia Vite plugin fills in the page resolver for you when `createInertiaApp()` is
given neither `pages` nor `resolve`, so components in `assets/pages` are found without
any glob code. This matches the default [`pages.paths`](/reference/configuration#pages)
setting.

## Root template

Output the built entry in your root template with the Reprise Twig functions. This is
what `bin/console inertia:view` generates:

```twig
{% block stylesheets %}
    {{ reprise_entry_link_tags('app') }}
{% endblock %}

{% block javascripts %}
    {{ reprise_entry_script_tags('app') }}
{% endblock %}
```

`app` is the name of the entry in the plugin's `input` option, and `app` by default.

## Development and builds

```sh
pnpm vite          # dev server with hot reload
pnpm vite build    # production build into public/build
```

In development, Reprise points the Twig tags at the dev server. After a production
build it reads `public/build/entrypoints.json` and `manifest.json`, which also feed the
bundle's [asset versioning](./getting-started#asset-versioning).

## Server-side rendering

The plugin has SSR defaults that line up with this bundle's:

```sh
pnpm vite build --ssr
```

- The SSR bundle goes to `var/ssr/ssr.js`.
- The SSR entry is `assets/ssr.js` when that file exists, otherwise the normal `input`.
- The Reprise plugin is skipped for SSR builds, and the public directory isn't copied.
- The output directory is emptied first, and chunks are written to `var/ssr/chunks/`.

A typical build script runs both builds:

```json
{
    "scripts": {
        "dev": "vite",
        "build": "vite build && vite build --ssr"
    }
}
```

Start the server with `node var/ssr/ssr.js` and turn on [`ssr.enabled`](./ssr).

## Plugin options

All of them are optional.

```js
inertiaReprise({
    input: { app: "assets/app.js" },
    ssr: {
        input: { ssr: "assets/ssr.js" },
        outDir: "var/ssr",
        entryFileName: "ssr.js",
        noExternal: true,
    },
    symfony: {}, // passed to the Reprise plugin
    inertia: {}, // passed to the Inertia Vite plugin
});
```

| Option | Default | |
| --- | --- | --- |
| `input` | `{ app: "assets/app.js" }` | Entry points. An input set in your own Vite config always wins. |
| `ssr` | `{}` | SSR build settings, or `false` to leave SSR builds alone. |
| `ssr.input` | `assets/ssr.js` if it exists, otherwise `input` | Entry points for SSR builds. |
| `ssr.outDir` | `var/ssr` | Where the SSR bundle is written. |
| `ssr.entryFileName` | `ssr.js` | The SSR bundle's file name. |
| `ssr.noExternal` | unset | Passed to Vite's `ssr.noExternal`. `true` bundles every dependency, so the output can run without `node_modules`. |
| `symfony` | | Options for the Reprise plugin. |
| `inertia` | | Options for the Inertia Vite plugin. |

Passing `ssr: false` leaves SSR builds untouched, runs the Reprise plugin for them,
and passes `ssr: false` to the Inertia plugin too.

## Another bundler

Nothing in the bundle requires Vite. With another bundler, build your assets and
output their tags from your own root template instead of the `reprise_entry_*`
functions. Make sure the SSR bundle ends up where [`ssr`](./ssr) expects it.
