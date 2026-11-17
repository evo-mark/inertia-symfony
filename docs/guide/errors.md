# Error handling

By default exceptions get Symfony's error page. To show an Inertia page instead,
register a handler once, for example from a `kernel.request` listener:

```php
use EvoMark\InertiaSymfonyBundle\Http\ExceptionResponse;

$inertia->handleExceptionsUsing(function (ExceptionResponse $response) {
    return $response->render('Error', [
        'status' => $response->statusCode(),
    ]);
});
```

The callback receives an `ExceptionResponse` with the `exception` and the `request`.
Return one of:

- the `ExceptionResponse`, after calling `render()`, for an Inertia error page
- any other response, such as a redirect
- `null`, to fall back to Symfony's error page

`render($component, $props)` accepts the same arguments as a normal page, and
`rootView()` swaps the root template for the error page.

The status code is the HTTP exception's own status, or `500` for any other
exception. The kernel's response listeners still run on the result, so redirects
become `303`s and so on.
