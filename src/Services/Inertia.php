<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Services;

use EvoMark\InertiaSymfonyBundle\Config\InertiaConfig;
use EvoMark\InertiaSymfonyBundle\Exception\ComponentNotFoundException;
use EvoMark\InertiaSymfonyBundle\Http\ExceptionResponse;
use EvoMark\InertiaSymfonyBundle\Http\Response as InertiaResponse;
use EvoMark\InertiaSymfonyBundle\Interfaces\ConfiguresSsrRequestsInterface;
use EvoMark\InertiaSymfonyBundle\Interfaces\DisablesSsrInterface;
use EvoMark\InertiaSymfonyBundle\Interfaces\ExcludesSsrPathsInterface;
use EvoMark\InertiaSymfonyBundle\Interfaces\InertiaInterface;
use EvoMark\InertiaSymfonyBundle\Interfaces\ProvidesInertiaPropertiesInterface;
use EvoMark\InertiaSymfonyBundle\Interfaces\ProvidesScrollMetadataInterface;
use EvoMark\InertiaSymfonyBundle\Interfaces\SsrGatewayInterface;
use EvoMark\InertiaSymfonyBundle\PageProps\AlwaysProp;
use EvoMark\InertiaSymfonyBundle\PageProps\DeferProp;
use EvoMark\InertiaSymfonyBundle\PageProps\MergeProp;
use EvoMark\InertiaSymfonyBundle\PageProps\OnceProp;
use EvoMark\InertiaSymfonyBundle\PageProps\OptionalProp;
use EvoMark\InertiaSymfonyBundle\PageProps\ScrollProp;
use EvoMark\InertiaSymfonyBundle\Support\Arr;
use EvoMark\InertiaSymfonyBundle\Support\Header;
use EvoMark\InertiaSymfonyBundle\Support\SessionKey;
use EvoMark\InertiaSymfonyBundle\Support\ValidationErrors;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Environment;

/**
 * The Inertia service: renders pages, shares props, and holds the options for the
 * current request. Per-request state is reset between requests (kernel.reset).
 */
class Inertia implements InertiaInterface, ResetInterface
{
    protected string $rootView;
    protected array $sharedProps = [];
    protected array $sharedViewData = [];
    protected string|\Closure|null $version;
    protected ?\Closure $componentTransformer;
    protected ?\Closure $urlResolver;

    /**
     * The exception handler. App-wide configuration, so reset() keeps it.
     */
    protected ?\Closure $exceptionHandler = null;

    /**
     * Per-request override of the `history.encrypt` config; null means use the config.
     */
    protected ?bool $encryptHistory;

    /**
     * @param InertiaConfig            $config       the bundle configuration
     * @param Environment              $engine       renders the root template
     * @param RequestStack             $requestStack provides the current request and session
     * @param SerializerInterface|null $serializer   encodes pages, when installed
     * @param LoggerInterface|null     $logger       reports errors from rescued props
     * @param PageFinder|null          $pageFinder   checks page files exist
     * @param SsrGatewayInterface|null $ssrGateway   receives the SSR settings
     */
    public function __construct(
        private InertiaConfig $config,
        private Environment $engine,
        private RequestStack $requestStack,
        private ?SerializerInterface $serializer = null,
        private ?LoggerInterface $logger = null,
        private ?PageFinder $pageFinder = null,
        private ?SsrGatewayInterface $ssrGateway = null,
    ) {
        $this->pageFinder ??= new PageFinder($config);
        $this->reset();
    }

    /**
     * Restore per-request state between requests (kernel.reset), so long-running
     * workers don't leak a version, root view or view data into the next request.
     */
    public function reset(): void
    {
        $this->rootView = $this->config->getRootView();
        $this->sharedProps = [];
        $this->sharedViewData = [];
        $this->version = $this->config->getVersion() ?? $this->defaultVersion(...);
        $this->transformComponentUsing();
        $this->resolveUrlUsing();
        $this->encryptHistory = null;
    }

    /**
     * Use a different root Twig template for this request.
     */
    public function setRootView(string $rootView): void
    {
        $this->rootView = $rootView;
    }

    /**
     * The root Twig template pages are rendered into.
     */
    public function getRootView(): string
    {
        return $this->rootView;
    }

    /**
     * Set the asset version: a string, a closure returning one, or null for the default.
     */
    public function version(string|\Closure|null $version): void
    {
        $this->version = $version;
    }

    /**
     * The current asset version.
     */
    public function getVersion(): string
    {
        $version = $this->version instanceof \Closure ? ($this->version)() : $this->version;

        return (string) $version;
    }

    /**
     * The default version: a hash of the asset URL if one is configured,
     * otherwise of the build manifest.
     */
    protected function defaultVersion(): ?string
    {
        if (null !== $assetUrl = $this->config->getAssetUrl()) {
            return hash('xxh128', $assetUrl);
        }

        if (is_file($manifest = $this->config->getManifestPath())) {
            return hash_file('xxh128', $manifest);
        }

        return null;
    }

    /**
     * Pass a variable to the root Twig template of every page in this request.
     */
    public function viewData(string $key, mixed $value = null): void
    {
        $this->sharedViewData[$key] = $value;
    }

    /**
     * All root template variables, or the one under the given key.
     */
    public function getViewData(?string $key = null): mixed
    {
        if (null !== $key) {
            return $this->sharedViewData[$key] ?? null;
        }

        return $this->sharedViewData;
    }

    /**
     * Share props with every page: a key (dot notation nests) and value, an array of
     * props, or a props provider.
     */
    public function share(string|array|ProvidesInertiaPropertiesInterface $key, mixed $value = null): void
    {
        if (is_array($key)) {
            $this->sharedProps = array_merge($this->sharedProps, $key);
        } elseif ($key instanceof ProvidesInertiaPropertiesInterface) {
            $this->sharedProps = array_merge($this->sharedProps, [$key]);
        } else {
            Arr::set($this->sharedProps, $key, $value);
        }
    }

    /**
     * Get the shared data for a given key. Returns all shared data if
     * no key is provided, or the value for a specific key with an
     * optional default fallback.
     */
    public function getShared(?string $key = null, mixed $default = null): mixed
    {
        if (null !== $key) {
            return Arr::get($this->sharedProps, $key, $default);
        }

        return $this->sharedProps;
    }

    /**
     * Flush all shared data.
     */
    public function flushShared(): void
    {
        $this->sharedProps = [];
    }

    /**
     * Create and share a once property.
     */
    public function shareOnce(string $key, callable $callback): OnceProp
    {
        $prop = new OnceProp($callback);
        $this->share($key, $prop);

        return $prop;
    }

    /**
     * Set the callback that resolves the page URL; null restores the request URI.
     *
     * @param (\Closure(Request): string)|null $urlResolver
     */
    public function resolveUrlUsing(?\Closure $urlResolver = null): void
    {
        $this->urlResolver = $urlResolver;
    }

    /**
     * Handle thrown exceptions with the given callback. It receives an
     * ExceptionResponse and returns it after calling render() for an Inertia
     * error page, returns another response (a redirect, say), or returns null
     * for Symfony's error page. Register it once, e.g. from a kernel.request
     * listener or the kernel's boot(); it applies to every later request.
     *
     * @param callable(ExceptionResponse): (ExceptionResponse|InertiaResponse|Response|null) $callback
     */
    public function handleExceptionsUsing(callable $callback): void
    {
        $this->exceptionHandler = \Closure::fromCallable($callback);
    }

    /**
     * The callback registered with handleExceptionsUsing(), if any.
     */
    public function getExceptionHandler(): ?\Closure
    {
        return $this->exceptionHandler;
    }

    /**
     * Disable server-side rendering for this request, optionally based on a condition.
     *
     * @throws \LogicException when the SSR gateway can't be disabled
     */
    public function disableSsr(\Closure|bool $condition = true): void
    {
        $this->ssrGateway(DisablesSsrInterface::class, 'disabling server-side rendering conditionally')->disable($condition);
    }

    /**
     * Exclude the given paths (or full URLs, `*` matching anything) from server-side rendering.
     *
     * @param array<int, string>|string $paths
     *
     * @throws \LogicException when the SSR gateway can't exclude paths
     */
    public function withoutSsr(array|string $paths): void
    {
        $this->ssrGateway(ExcludesSsrPathsInterface::class, 'excluding paths from server-side rendering')->except($paths);
    }

    /**
     * Adjust the HTTP request sent to the SSR server; null removes the callback.
     *
     * @throws \LogicException when the SSR gateway can't configure its requests
     */
    public function configureSsrRequestUsing(?\Closure $callback = null): void
    {
        $this->ssrGateway(ConfiguresSsrRequestsInterface::class, 'configuring server-side rendering requests')->configureRequestUsing($callback);
    }

    /**
     * The SSR gateway, if it supports the given capability.
     *
     * @template T of object
     *
     * @param class-string<T> $capability
     *
     * @return T
     */
    private function ssrGateway(string $capability, string $feature): object
    {
        if (!$this->ssrGateway instanceof $capability) {
            throw new \LogicException(\sprintf('The configured SSR gateway does not support %s.', $feature));
        }

        return $this->ssrGateway;
    }

    /**
     * Clear the browser history on the next visit.
     */
    public function clearHistory(): void
    {
        $this->requestStack->getSession()->set(SessionKey::CLEAR_HISTORY, true);
    }

    /**
     * Preserve the URL fragment across the next redirect.
     */
    public function preserveFragment(): void
    {
        $this->requestStack->getSession()->set(SessionKey::PRESERVE_FRAGMENT, true);
    }

    /**
     * Encrypt the browser history for this request, overriding `history.encrypt`.
     */
    public function encryptHistory(bool $encrypt = true): void
    {
        $this->encryptHistory = $encrypt;
    }

    /**
     * Create an optional property: only sent when requested by a partial reload.
     */
    public function optional(callable $callback): OptionalProp
    {
        return new OptionalProp($callback);
    }

    /**
     * Create a deferred property: loaded by the client after the page renders.
     */
    public function defer(callable $callback, string $group = 'default', bool $rescue = false): DeferProp
    {
        return new DeferProp($callback, $group, $rescue);
    }

    /**
     * Create a merge property: appended to the client's existing value.
     */
    public function merge(mixed $value): MergeProp
    {
        return new MergeProp($value);
    }

    /**
     * Create a deep merge property: deep merged with the client's existing value.
     */
    public function deepMerge(mixed $value): MergeProp
    {
        return new MergeProp($value)->deepMerge();
    }

    /**
     * Create an always property: included even when a partial reload doesn't ask for it.
     */
    public function always(mixed $value): AlwaysProp
    {
        return new AlwaysProp($value);
    }

    /**
     * Create a scroll property for infinite scrolling.
     *
     * @template T
     *
     * @param T                                                                                 $value
     * @param ProvidesScrollMetadataInterface|callable(T): ProvidesScrollMetadataInterface|null $metadata
     *
     * @return ScrollProp<T>
     */
    public function scroll(mixed $value, string $wrapper = 'data', ProvidesScrollMetadataInterface|callable|null $metadata = null): ScrollProp
    {
        return new ScrollProp($value, $wrapper, $metadata);
    }

    /**
     * Create a once property: resolved once and remembered by the client.
     */
    public function once(callable $value): OnceProp
    {
        return new OnceProp($value);
    }

    /**
     * Create an Inertia location response: a 409 that makes the client do a
     * full page visit for Inertia requests, otherwise a plain redirect.
     */
    public function location(string|RedirectResponse $url): Response
    {
        if ($this->requestStack->getCurrentRequest()?->headers->has(Header::INERTIA)) {
            return new Response('', Response::HTTP_CONFLICT, [
                Header::LOCATION => $url instanceof RedirectResponse ? $url->getTargetUrl() : $url,
            ]);
        }

        return $url instanceof RedirectResponse ? $url : new RedirectResponse($url);
    }

    /**
     * Redirect to the previous location: the Referer, then the stored previous
     * URL, then the fallback, then `/`.
     *
     * @param array<string, string> $headers
     */
    public function back(int $status = Response::HTTP_FOUND, array $headers = [], string|false $fallback = false): RedirectResponse
    {
        $request = $this->requestStack->getCurrentRequest();

        $url = $request?->headers->get('Referer')
            ?: ($request?->hasPreviousSession() ? $request->getSession()->get(SessionKey::PREVIOUS_URL) : null)
            ?: ($fallback ?: '/');

        return new RedirectResponse($url, $status, $headers);
    }

    /**
     * Flash data to be included with the next response. Unlike props, flash data
     * is not kept in the browser's history state, so it suits one-time toasts.
     *
     * @param \BackedEnum|\UnitEnum|string|array<string, mixed> $key
     */
    public function flash(\BackedEnum|\UnitEnum|string|array $key, mixed $value = null): static
    {
        if (!\is_array($key)) {
            $key = match (true) {
                $key instanceof \BackedEnum => (string) $key->value,
                $key instanceof \UnitEnum => $key->name,
                default => $key,
            };

            $key = [$key => $value];
        }

        $this->requestStack->getSession()->set(SessionKey::FLASH_DATA, [...$this->getFlashed(), ...$key]);

        return $this;
    }

    /**
     * Send errors to the next page as its `errors` prop, e.g. before redirecting
     * back to the form: `return $inertia->withErrors($violations)->back();`.
     * Accepts validator violations, or field => message(s) for errors that don't
     * come from the Validator. A named bag keeps one form's errors apart from
     * another's on the same page.
     *
     * @param ConstraintViolationListInterface|array<string, string|list<string>> $errors
     */
    public function withErrors(ConstraintViolationListInterface|array $errors, string $bag = 'default'): static
    {
        $errors = $errors instanceof ConstraintViolationListInterface
            ? ValidationErrors::fromViolations($errors)
            : ValidationErrors::fromArray($errors);

        $session = $this->requestStack->getSession();
        $bags = (array) $session->get(SessionKey::ERRORS, []);
        $bags[$bag] = array_merge_recursive($bags[$bag] ?? [], $errors);
        $session->set(SessionKey::ERRORS, $bags);

        return $this;
    }

    /**
     * The flashed data in the session.
     *
     * @return array<string, mixed>
     */
    public function getFlashed(?Request $request = null): array
    {
        $request ??= $this->requestStack->getCurrentRequest();

        return $request?->hasSession() ? (array) $request->getSession()->get(SessionKey::FLASH_DATA, []) : [];
    }

    /**
     * The flashed data in the session, removing it.
     *
     * @return array<string, mixed>
     */
    public function pullFlashed(?Request $request = null): array
    {
        $request ??= $this->requestStack->getCurrentRequest();

        return $request?->hasSession() ? (array) ($request->getSession()->remove(SessionKey::FLASH_DATA) ?? []) : [];
    }

    /**
     * Transform the component name.
     */
    protected function transformComponent(mixed $component): mixed
    {
        if (!$this->componentTransformer) {
            return $component;
        }

        return ($this->componentTransformer)($component) ?? $component;
    }

    /**
     * Set the component transformer.
     */
    public function transformComponentUsing(?\Closure $componentTransformer = null): void
    {
        $this->componentTransformer = $componentTransformer;
    }

    /**
     * Create an Inertia response. It is built when the kernel turns it into a
     * Symfony response (see InertiaResponseListener), so it can still be
     * changed with with(), flash(), rootView() and so on.
     *
     * @param array<array-key, mixed>|ProvidesInertiaPropertiesInterface|\JsonSerializable $props
     * @param array<string, mixed>                                                         $viewData variables for the root view
     * @param array<string, mixed>                                                         $context  extra Serializer context
     */
    public function render(string|\BackedEnum|\UnitEnum $component, $props = [], $viewData = [], $context = [], $url = null): InertiaResponse
    {
        $component = $this->transformComponent($component);
        $component = match (true) {
            $component instanceof \BackedEnum => $component->value,
            $component instanceof \UnitEnum => $component->name,
            default => $component,
        };
        if (!is_string($component)) {
            throw new \InvalidArgumentException('Component argument must be of type string, a UnitEnum, or a string BackedEnum');
        }

        if ($this->config->shouldEnsurePagesExist()) {
            $this->findComponentOrFail($component);
        }

        if ($props instanceof \JsonSerializable) {
            $props = (array) $props->jsonSerialize();
        } elseif ($props instanceof ProvidesInertiaPropertiesInterface) {
            // Expanded into keyed props by PropsResolver.
            $props = [$props];
        }

        $response = new InertiaResponse(
            $component,
            $this->sharedProps,
            $props,
            $this->getRootView(),
            $this->getVersion(),
            $this->encryptHistory ?? $this->config->shouldEncryptHistory(),
            null !== $url ? static fn (): string => $url : $this->urlResolver,
            $this->config->shouldPreserveBigIntegers(),
        );

        $response->exposeSharedPropKeys($this->config->shouldExposeSharedPropKeys());

        if (null !== $this->logger) {
            $response->setLogger($this->logger);
        }

        return $response
            ->withServices($this->engine, $this->serializer)
            ->serializationContext($context)
            ->withViewData(array_merge($this->sharedViewData, $viewData));
    }

    /**
     * Find the component or fail.
     *
     * @throws ComponentNotFoundException
     */
    protected function findComponentOrFail(string $component): void
    {
        try {
            $this->pageFinder->find($component);
        } catch (\InvalidArgumentException $e) {
            throw new ComponentNotFoundException("Inertia page component [{$component}] not found.", previous: $e);
        }
    }
}
