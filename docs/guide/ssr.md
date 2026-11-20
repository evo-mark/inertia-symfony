# Server-side rendering

With SSR on, pages are rendered by a Node server and the HTML is sent with the first
response, which helps search engines and first paint.

## Setup

1. Build an SSR bundle with your bundler. With
   [`@evomark/inertia-reprise`](https://github.com/evo-mark/inertia-reprise) this is
   `vite build --ssr`, which writes `var/ssr/ssr.js`.
2. Start the SSR server: `node var/ssr/ssr.js`.
3. Turn it on:

```yaml
# config/packages/evo_mark_inertia_symfony.yaml
evo_mark_inertia_symfony:
    ssr:
        enabled: true
        url: http://127.0.0.1:13714
```

`inertiaHead(page)` and `inertiaApp(page)` in the root template then output the
server-rendered markup. Each page is rendered once, however many functions ask for it.

## Failures

When the server can't be reached, or answers with an error, the page falls back to
client-side rendering and a `SsrRenderFailed` event is dispatched. Listen for it to
log failures:

```php
use EvoMark\InertiaSymfonyBundle\Events\SsrRenderFailed;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class LogSsrFailure
{
    public function __construct(private LoggerInterface $logger) {}

    public function __invoke(SsrRenderFailed $event): void
    {
        $this->logger->warning('SSR failed', $event->toArray());
    }
}
```

The event has these properties and methods:

| | |
| --- | --- |
| `error` | The error message. |
| `type` | A `SsrErrorType`: `BrowserApi`, `ComponentResolution`, `Render`, `Connection` or `Unknown`. |
| `hint` | A suggestion for fixing the error, when the server gives one. |
| `browserApi` | The browser API that was used on the server, for `BrowserApi` errors. |
| `stack`, `sourceLocation` | The stack trace and `file:line:column`, when available. |
| `component()`, `url()` | The page being rendered. |
| `toArray()` | The non-empty details, for logging. |

### Throwing instead

Turn on `throw_on_error` (handy in development and CI) to throw an `SsrException`
after the event has been dispatched:

```yaml
evo_mark_inertia_symfony:
    ssr:
        throw_on_error: true
```

The message reads `SSR render failed for component [Users/Show]: window is not defined at assets/pages/Users/Show.vue:10:5`.
The exception also has `component()`, `type()`, `hint()`, `browserApi()` and
`sourceLocation()`, and carries the event in `$exception->event`.

## Controlling SSR per request

```php
$inertia->disableSsr();                          // for this request
$inertia->disableSsr(fn () => $this->isAdminArea());   // disabled when the closure returns true, evaluated at render time
$inertia->disableSsr(false);                           // force SSR on, whatever `ssr.enabled` says
$inertia->withoutSsr(['admin/*', 'https://example.com/embed']);
$inertia->configureSsrRequestUsing(fn (array $options) => [
    ...$options,
    'headers' => [...($options['headers'] ?? []), 'X-Tenant' => 'acme'],
]);
```

- `disableSsr()` and the condition reset after each request.
- `withoutSsr()` takes paths or full URLs, where `*` matches anything. It applies to
  the whole app, so register it once.
- `configureSsrRequestUsing()` receives the Symfony HttpClient options for the request
  to the SSR server. Return new options, or change them by reference. Pass `null`
  to remove it.

## Health check

The default gateway can check that the SSR server is up:

```php
$gateway->isHealthy(); // true for a 2xx answer from /health
```

## Timeouts

Set `ssr.timeout` (in seconds) to limit how long a render may take. A timeout counts
as a connection failure.
