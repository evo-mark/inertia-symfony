# Configuration

All settings live under `inertia_symfony` and are optional. List them with:

```sh
bin/console config:dump-reference inertia_symfony
```

```yaml
# config/packages/inertia_symfony.yaml
inertia_symfony:
    root_view: app.html.twig
    ssr:
        enabled: false
```

## Options

### `root_view`

Default `app.html.twig`. The Twig template that wraps every page.

### `version`, `asset_url`, `manifest_path`

How the [asset version](/guide/getting-started#asset-versioning) is chosen.

| Option | Default | |
| --- | --- | --- |
| `version` | `null` | A fixed version string. Wins over the other two. |
| `asset_url` | `null` | The base URL assets are served from, such as a CDN. When set, its hash is the version. |
| `manifest_path` | `%kernel.project_dir%/public/build/manifest.json` | The build manifest that is hashed when neither of the above is set. |

### `ssr`

See [Server-side rendering](/guide/ssr).

| Option | Default | |
| --- | --- | --- |
| `enabled` | `false` | Render pages on the SSR server. |
| `url` | `http://127.0.0.1:13714` | Where the SSR server listens. |
| `timeout` | `null` | Request timeout, in seconds. `null` uses the HttpClient default. |
| `throw_on_error` | `false` | Throw an `SsrException` when rendering fails. |

### `pages`

| Option | Default | |
| --- | --- | --- |
| `ensure_pages_exist` | `false` | Throw `ComponentNotFoundException` when a rendered component has no file. |
| `paths` | `[%kernel.project_dir%/assets/pages]` | Where components live. |
| `extensions` | `js`, `jsx`, `svelte`, `ts`, `tsx`, `vue` | File extensions a component may have. |

### `expose_shared_prop_keys`

Default `true`. Sends the top-level keys of shared props in the page as `sharedProps`
metadata, so the client knows which props are shared. Override it per response with
`->exposeSharedPropKeys(false)`.

### `store_previous_url`

Default `false`. Remembers each visit's URL and route in the session, which
`$inertia->back()` uses when there is no `Referer` header.

### `history`

| Option | Default | |
| --- | --- | --- |
| `encrypt` | `false` | Encrypt the browser history state of every page. |

### `preserve_big_integers`

Default `false`. Sends integers beyond JavaScript's safe range as strings.

## Commands

| Command | |
| --- | --- |
| `inertia:view` | Create the root Twig template. `--force` overwrites it. |
| `inertia:subscriber [name]` | Create an event listener for [shared props](/guide/props#shared-props). `--force` overwrites it. |
