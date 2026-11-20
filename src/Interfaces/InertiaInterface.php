<?php

namespace EvoMark\InertiaSymfonyBundle\Interfaces;

use Closure;
use EvoMark\InertiaSymfonyBundle\Http\ExceptionResponse;
use EvoMark\InertiaSymfonyBundle\Http\Response as InertiaResponse;
use EvoMark\InertiaSymfonyBundle\Interfaces\ProvidesInertiaPropertiesInterface;
use EvoMark\InertiaSymfonyBundle\PageProps\AlwaysProp;
use EvoMark\InertiaSymfonyBundle\PageProps\DeferProp;
use EvoMark\InertiaSymfonyBundle\PageProps\MergeProp;
use EvoMark\InertiaSymfonyBundle\PageProps\OnceProp;
use EvoMark\InertiaSymfonyBundle\PageProps\OptionalProp;
use EvoMark\InertiaSymfonyBundle\PageProps\ScrollProp;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * The Inertia service: renders pages, shares props and sets per-request options.
 */
interface InertiaInterface
{
    /**
     * Use a different root Twig template for this request.
     */
    public function setRootView(string $rootView): void;

    /**
     * The root Twig template pages are rendered into.
     */
    public function getRootView(): string;

    /**
     * Set the asset version: a string, a closure returning one, or null for the default.
     */
    public function version(string|\Closure|null $version): void;

    /**
     * The current asset version.
     */
    public function getVersion(): string;

    /**
     * Share props with every page: a key (dot notation nests) and value, an
     * array of props, or a props provider.
     */
    public function share(string|array|ProvidesInertiaPropertiesInterface $key, mixed $value = null): void;

    /**
     * All shared props, or the one under the given key (dot notation) with a fallback.
     */
    public function getShared(?string $key = null, mixed $default = null): mixed;

    /**
     * Remove every shared prop.
     */
    public function flushShared(): void;

    /**
     * Share a once prop: resolved once and remembered by the client.
     */
    public function shareOnce(string $key, callable $callback): OnceProp;

    /**
     * @param (\Closure(Request): string)|null $urlResolver
     */
    public function resolveUrlUsing(?\Closure $urlResolver = null): void;

    /**
     * Set the callback that transforms component names before rendering; null removes it.
     */
    public function transformComponentUsing(?\Closure $componentTransformer = null): void;

    /**
     * @param callable(ExceptionResponse): (ExceptionResponse|InertiaResponse|Response|null) $callback
     */
    public function handleExceptionsUsing(callable $callback): void;

    /**
     * The callback registered with handleExceptionsUsing(), if any.
     */
    public function getExceptionHandler(): ?\Closure;

    /**
     * Disable server-side rendering for this request, optionally based on a condition.
     */
    public function disableSsr(\Closure|bool $condition = true): void;

    /**
     * @param array<int, string>|string $paths
     */
    public function withoutSsr(array|string $paths): void;

    /**
     * Adjust the HTTP request sent to the SSR server; null removes the callback.
     */
    public function configureSsrRequestUsing(?\Closure $callback = null): void;

    /**
     * Clear the browser history on the next visit.
     */
    public function clearHistory(): void;

    /**
     * Preserve the URL fragment across the next redirect.
     */
    public function preserveFragment(): void;

    /**
     * Encrypt the browser history for this request, overriding `history.encrypt`.
     */
    public function encryptHistory(bool $encrypt = true): void;

    /**
     * Create an optional prop: only sent when a partial reload asks for it.
     */
    public function optional(callable $callback): OptionalProp;

    /**
     * Create a deferred prop: loaded by the client after the page renders.
     */
    public function defer(callable $callback, string $group = 'default', bool $rescue = false): DeferProp;

    /**
     * Create a merge prop: appended to the client's existing value.
     */
    public function merge(mixed $value): MergeProp;

    /**
     * Create a deep merge prop: deep merged with the client's existing value.
     */
    public function deepMerge(mixed $value): MergeProp;

    /**
     * Create an always prop: included even when a partial reload doesn't ask for it.
     */
    public function always(mixed $value): AlwaysProp;

    /**
     * Create a scroll prop for infinite scrolling.
     */
    public function scroll(mixed $value, string $wrapper = 'data', ProvidesScrollMetadataInterface|callable|null $metadata = null): ScrollProp;

    /**
     * Create a once prop: resolved once and remembered by the client.
     */
    public function once(callable $value): OnceProp;

    /**
     * Make the client do a full page visit to a URL: a 409 for Inertia requests, otherwise a redirect.
     */
    public function location(string|RedirectResponse $url): Response;

    /**
     * @param array<string, string> $headers
     */
    public function back(int $status = Response::HTTP_FOUND, array $headers = [], string|false $fallback = false): RedirectResponse;

    /**
     * @param \BackedEnum|\UnitEnum|string|array<string, mixed> $key
     */
    public function flash(\BackedEnum|\UnitEnum|string|array $key, mixed $value = null): static;

    /**
     * @param ConstraintViolationListInterface|array<string, string|list<string>> $errors
     */
    public function withErrors(ConstraintViolationListInterface|array $errors, string $bag = 'default'): static;

    /**
     * @return array<string, mixed>
     */
    public function getFlashed(?Request $request = null): array;

    /**
     * @return array<string, mixed>
     */
    public function pullFlashed(?Request $request = null): array;

    /**
     * Create an Inertia response, built when the kernel turns it into a Symfony response.
     */
    public function render(string|\BackedEnum|\UnitEnum $component, $props = [], $viewData = [], $context = [], $url = null): InertiaResponse;
}
