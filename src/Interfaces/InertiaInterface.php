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

interface InertiaInterface
{
    public function setRootView(string $rootView): void;

    public function getRootView(): string;

    public function version(string|\Closure|null $version): void;

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

    public function flushShared(): void;

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

    public function getExceptionHandler(): ?\Closure;

    public function disableSsr(\Closure|bool $condition = true): void;

    /**
     * @param array<int, string>|string $paths
     */
    public function withoutSsr(array|string $paths): void;

    public function configureSsrRequestUsing(?\Closure $callback = null): void;

    public function clearHistory(): void;

    public function preserveFragment(): void;

    public function encryptHistory(bool $encrypt = true): void;

    public function optional(callable $callback): OptionalProp;

    public function defer(callable $callback, string $group = 'default', bool $rescue = false): DeferProp;

    public function merge(mixed $value): MergeProp;

    public function deepMerge(mixed $value): MergeProp;

    public function always(mixed $value): AlwaysProp;

    public function scroll(mixed $value, string $wrapper = 'data', ProvidesScrollMetadataInterface|callable|null $metadata = null): ScrollProp;

    public function once(callable $value): OnceProp;

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
