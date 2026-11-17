<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Http;

use Closure;
use EvoMark\InertiaSymfonyBundle\Interfaces\DeferrableInterface;
use EvoMark\InertiaSymfonyBundle\Interfaces\IgnoreFirstLoadInterface;
use EvoMark\InertiaSymfonyBundle\Interfaces\MergeableInterface;
use EvoMark\InertiaSymfonyBundle\Interfaces\OnceableInterface;
use EvoMark\InertiaSymfonyBundle\Interfaces\ProvidesInertiaPropertiesInterface;
use EvoMark\InertiaSymfonyBundle\Interfaces\ProvidesInertiaPropertyInterface;
use EvoMark\InertiaSymfonyBundle\Interfaces\RescuableInterface;
use EvoMark\InertiaSymfonyBundle\PageProps\AlwaysProp;
use EvoMark\InertiaSymfonyBundle\PageProps\ScrollProp;
use EvoMark\InertiaSymfonyBundle\Support\Arr;
use EvoMark\InertiaSymfonyBundle\Support\Header;
use EvoMark\InertiaSymfonyBundle\Support\PropertyContext;
use EvoMark\InertiaSymfonyBundle\Support\RenderContext;
use EvoMark\InertiaSymfonyBundle\Traits\ResolvesCallables;
use JsonSerializable;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

/**
 * Resolves a page's shared and own props for one request: unpacks dot keys,
 * expands props providers, calls closures, applies partial reload filters, and
 * collects the merge, deferred, scroll and once metadata the client acts on.
 */
class PropsResolver
{
    use ResolvesCallables;

    /**
     * Whether this is a partial request for the given component.
     */
    protected bool $isPartial;

    /**
     * Whether this is an Inertia request.
     */
    protected bool $isInertia;

    /**
     * The props to include in the partial response.
     *
     * @var array<int, string>|null
     */
    protected ?array $only;

    /**
     * The props to exclude from the partial response.
     *
     * @var array<int, string>|null
     */
    protected ?array $except;

    /**
     * The props that should have their merge state reset.
     *
     * @var array<int, string>
     */
    protected array $resetProps;

    /**
     * The once-props that the client has already loaded.
     *
     * @var array<int, string>
     */
    protected array $loadedOnceProps;

    /**
     * The deferred props grouped by their defer group.
     *
     * @var array<string, array<int, string>>
     */
    protected array $deferredProps = [];

    /**
     * The deferred props that were rescued during resolution.
     *
     * @var array<int, string>
     */
    protected array $rescuedProps = [];

    /**
     * The props that should be appended to existing client-side data.
     *
     * @var array<int, string>
     */
    protected array $mergeProps = [];

    /**
     * The props that should be prepended to existing client-side data.
     *
     * @var array<int, string>
     */
    protected array $prependProps = [];

    /**
     * The props that should be deep merged with existing client-side data.
     *
     * @var array<int, string>
     */
    protected array $deepMergeProps = [];

    /**
     * The key matching strategies for mergeable props.
     *
     * @var array<int, string>
     */
    protected array $matchPropsOn = [];

    /**
     * The scroll pagination metadata for each scroll prop.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $scrollProps = [];

    /**
     * The once-prop metadata for each once prop.
     *
     * @var array<string, array{prop: string, expiresAt: int|null}>
     */
    protected array $onceProps = [];

    /**
     * The top-level keys of shared props.
     *
     * @var array<int, string>
     */
    protected array $sharedPropKeys = [];

    /**
     * @param bool                 $exposeSharedPropKeys send the top-level shared prop keys as `sharedProps` metadata
     * @param LoggerInterface|null $logger               reports errors from rescued props
     */
    public function __construct(
        protected Request $request,
        protected string $component,
        protected bool $exposeSharedPropKeys = true,
        protected ?LoggerInterface $logger = null,
    ) {
        $this->isPartial = $request->headers->get(Header::PARTIAL_COMPONENT) === $component;
        $this->isInertia = (bool) $request->headers->get(Header::INERTIA);
        $this->only = $this->parseHeader(Header::PARTIAL_ONLY);
        $this->except = $this->parseHeader(Header::PARTIAL_EXCEPT);
        $this->resetProps = $this->parseHeader(Header::RESET) ?? [];
        $this->loadedOnceProps = $this->parseHeader(Header::EXCEPT_ONCE_PROPS) ?? [];
    }

    /**
     * Resolve the given shared and page props, collecting their metadata.
     *
     * @param array<array-key, mixed> $shared
     * @param array<array-key, mixed> $props
     *
     * @return array{array<array-key, mixed>, array<string, mixed>} the resolved props and the page metadata
     */
    public function resolve(array $shared, array $props): array
    {
        $props = array_merge($this->resolveSharedProps($shared), $props);

        return [
            $this->resolveProps($this->unpackDotProps($props)),
            $this->buildMetadata(),
        ];
    }

    /**
     * Resolve shared property providers and collect shared prop keys.
     *
     * @param array<array-key, mixed> $shared
     *
     * @return array<array-key, mixed>
     */
    protected function resolveSharedProps(array $shared): array
    {
        $resolved = $this->resolvePropertyProviders($shared);

        if (!$this->exposeSharedPropKeys) {
            return $resolved;
        }

        foreach (array_keys($resolved) as $key) {
            $key = (string) $key;
            $this->sharedPropKeys[] = str_contains($key, '.') ? strstr($key, '.', true) : $key;
        }

        $this->sharedPropKeys = array_values(array_unique($this->sharedPropKeys));

        return $resolved;
    }

    /**
     * Expand props providers without a key into keyed props, in place.
     *
     * @param array<array-key, mixed> $props
     *
     * @return array<array-key, mixed>
     */
    protected function resolvePropertyProviders(array $props): array
    {
        $context = null;
        $result = [];

        foreach ($props as $key => $value) {
            if (\is_int($key) && $value instanceof ProvidesInertiaPropertiesInterface) {
                $context ??= new RenderContext($this->component, $this->request);

                $provided = $value->toInertiaProperties($context);
                $result = array_merge($result, \is_array($provided) ? $provided : iterator_to_array($provided));
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Build the non-empty metadata arrays for the page response.
     *
     * @return array<string, mixed>
     */
    protected function buildMetadata(): array
    {
        return array_filter([
            'sharedProps' => $this->sharedPropKeys,
            'mergeProps' => $this->mergeProps,
            'prependProps' => $this->prependProps,
            'deepMergeProps' => $this->deepMergeProps,
            'matchPropsOn' => $this->matchPropsOn,
            'deferredProps' => $this->deferredProps,
            'rescuedProps' => $this->rescuedProps,
            'scrollProps' => $this->scrollProps,
            'onceProps' => $this->onceProps,
        ], static fn (array $value): bool => [] !== $value);
    }

    /**
     * Recursively resolve the props tree, collecting metadata along the way.
     *
     * @param array<array-key, mixed> $props
     *
     * @return array<array-key, mixed>
     */
    protected function resolveProps(array $props, string $prefix = '', bool $parentWasResolved = false): array
    {
        $props = $this->resolvePropertyProviders($props);
        $result = [];

        foreach ($props as $key => $value) {
            $path = '' === $prefix ? (string) $key : "{$prefix}.{$key}";
            $prop = $value;

            // On partial requests, only props matching the requested paths are
            // included. AlwaysProp instances and the children of already-resolved
            // values bypass this filter.
            if (!$this->shouldIncludeInPartialResponse($prop, $path, $parentWasResolved)) {
                continue;
            }

            // On initial page loads, some prop types (e.g. DeferProp, OptionalProp)
            // are excluded before resolution so their closures never run.
            if (!$this->isPartial && $this->excludeFromInitialResponse($prop, $path)) {
                continue;
            }

            $value = $this->resolveValue($prop, $path, $props);

            if (\in_array($path, $this->rescuedProps, true)) {
                continue;
            }

            // A closure may return a prop type instead of a plain value. Unwrap it
            // one more level so the prop type takes part in filtering and metadata.
            if ($value !== $prop && $this->isPropType($value)) {
                $prop = $value;

                // The unwrapped prop type may itself be excluded from the initial response.
                if (!$this->isPartial && $this->excludeFromInitialResponse($prop, $path)) {
                    continue;
                }

                $value = $this->resolveValue($prop, $path, $props);
            }

            $this->collectMetadata($prop, $path);

            // Recurse into arrays. If the original prop was not already an array
            // (e.g. a closure that returned one), its children bypass partial filtering.
            $result[$key] = \is_array($value)
                ? $this->resolveProps($value, $path, $parentWasResolved || !\is_array($prop))
                : $value;
        }

        return $result;
    }

    /**
     * Determine if a prop should be included in a partial response.
     */
    protected function shouldIncludeInPartialResponse(mixed $prop, string $path, bool $parentWasResolved): bool
    {
        if (!$this->isPartial || $this->bypassesPartialFiltering($prop, $parentWasResolved)) {
            return true;
        }

        return $this->pathMatchesPartialRequest($path);
    }

    /**
     * Determine if a prop is resolved independently of partial request filters.
     */
    protected function bypassesPartialFiltering(mixed $prop, bool $parentWasResolved): bool
    {
        return $prop instanceof AlwaysProp || $parentWasResolved;
    }

    /**
     * Determine if the given path matches the current partial request using
     * bidirectional prefix matching on the only/except headers.
     */
    protected function pathMatchesPartialRequest(string $path): bool
    {
        if (null !== $this->only && !$this->matchesOnly($path) && !$this->leadsToOnly($path)) {
            return false;
        }

        if (null !== $this->except && $this->matchesExcept($path)) {
            return false;
        }

        return true;
    }

    /**
     * Determine if a prop should be excluded from the initial page response.
     * Each exclusion type collects its metadata before the prop is removed.
     */
    protected function excludeFromInitialResponse(mixed $prop, string $path): bool
    {
        // OptionalProp and DeferProp are never sent on the initial page load, but
        // still contribute deferred, merge and once metadata for the client.
        if ($prop instanceof IgnoreFirstLoadInterface) {
            return $this->excludeIgnoredProp($prop, $path);
        }

        // ScrollProp and other deferrable types may defer their initial load, and
        // contribute deferred-group and merge metadata so the client requests them.
        if ($prop instanceof DeferrableInterface && $prop->shouldDefer()) {
            return $this->excludeDeferredProp($prop, $path);
        }

        // Once-props the client already has (sent in the except-once header) are
        // left out of subsequent Inertia visits.
        if ($this->isInertia && $this->wasAlreadyLoadedByClient($prop, $path)) {
            return $this->excludeAlreadyLoadedProp($prop, $path);
        }

        return false;
    }

    /**
     * Exclude an IgnoreFirstLoad prop while collecting its deferred, merge and once metadata.
     */
    protected function excludeIgnoredProp(mixed $prop, string $path): bool
    {
        if ($prop instanceof DeferrableInterface && $prop->shouldDefer()
            && !$this->wasAlreadyLoadedByClient($prop, $path)) {
            $this->collectDeferredPropMetadata($path, $prop);
        }

        if ($prop instanceof MergeableInterface && $prop->shouldMerge()) {
            $this->collectMergeableMetadata($path, $prop);
        }

        if ($prop instanceof OnceableInterface && $prop->shouldResolveOnce()) {
            $this->collectOnceMetadata($path, $prop);
        }

        return true;
    }

    /**
     * Exclude a deferred prop while collecting its deferred-group and merge metadata.
     */
    protected function excludeDeferredProp(DeferrableInterface $prop, string $path): bool
    {
        $this->collectDeferredPropMetadata($path, $prop);

        if ($prop instanceof MergeableInterface && $prop->shouldMerge()) {
            $this->collectMergeableMetadata($path, $prop);
        }

        return true;
    }

    /**
     * Exclude a once-prop the client already has, keeping its once metadata.
     */
    protected function excludeAlreadyLoadedProp(mixed $prop, string $path): bool
    {
        $this->collectOnceMetadata($path, $prop);

        return true;
    }

    /**
     * Determine if a once-prop has already been loaded by the client.
     */
    protected function wasAlreadyLoadedByClient(mixed $prop, string $path): bool
    {
        return $prop instanceof OnceableInterface
            && $prop->shouldResolveOnce()
            && !$prop->shouldBeRefreshed()
            && \in_array($prop->getKey() ?? $path, $this->loadedOnceProps, true);
    }

    /**
     * Resolve a single prop value through the resolution pipeline.
     *
     * @param array<array-key, mixed> $siblings
     */
    protected function resolveValue(mixed $value, string $path, array $siblings): mixed
    {
        if ($value instanceof ScrollProp) {
            $value->configureMergeIntent($this->request);
        }

        $shouldRescue = $value instanceof RescuableInterface && $value->shouldRescue();

        try {
            $value = $this->resolveCallable($value);

            if ($value instanceof ProvidesInertiaPropertyInterface) {
                $value = $value->toInertiaProperty(new PropertyContext($path, $siblings, $this->request));
            }

            if ($value instanceof \JsonSerializable) {
                $value = $value->jsonSerialize();
            }

            return $value;
        } catch (\Throwable $e) {
            if (!$shouldRescue) {
                throw $e;
            }

            $this->logger?->error('Inertia prop "{prop}" failed to resolve and was rescued: {message}', [
                'prop' => $path,
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            $this->rescuedProps[] = $path;

            return null;
        }
    }

    /**
     * Determine if the value is a prop type that needs further filtering or metadata collection.
     */
    protected function isPropType(mixed $value): bool
    {
        return $value instanceof AlwaysProp
            || $value instanceof DeferrableInterface
            || $value instanceof IgnoreFirstLoadInterface
            || $value instanceof MergeableInterface
            || $value instanceof OnceableInterface;
    }

    /**
     * Collect metadata for a prop that will be included in the response.
     */
    protected function collectMetadata(mixed $prop, string $path): void
    {
        if ($prop instanceof MergeableInterface && $prop->shouldMerge()) {
            $this->collectMergeableMetadata($path, $prop);
        }

        if ($prop instanceof ScrollProp) {
            $this->collectScrollMetadata($path, $prop);
        }

        if ($prop instanceof OnceableInterface && $prop->shouldResolveOnce()) {
            $this->collectOnceMetadata($path, $prop);
        }
    }

    /**
     * Collect the deferred prop and its group.
     */
    protected function collectDeferredPropMetadata(string $path, DeferrableInterface $prop): void
    {
        $this->deferredProps[$prop->group()][] = $path;
    }

    /**
     * Collect the merge strategy for a mergeable prop.
     */
    protected function collectMergeableMetadata(string $path, MergeableInterface $prop): void
    {
        if (\in_array($path, $this->resetProps, true)) {
            return;
        }

        if ($this->isPartial && !$this->isIncludedInPartialMetadata($path)) {
            return;
        }

        if ($prop->shouldDeepMerge()) {
            $this->deepMergeProps[] = $path;
        } elseif ($prop->appendsAtRoot()) {
            $this->mergeProps[] = $path;
        } elseif ($prop->prependsAtRoot()) {
            $this->prependProps[] = $path;
        } else {
            foreach ($prop->appendsAtPaths() as $appendPath) {
                $this->mergeProps[] = "{$path}.{$appendPath}";
            }

            foreach ($prop->prependsAtPaths() as $prependPath) {
                $this->prependProps[] = "{$path}.{$prependPath}";
            }
        }

        foreach ($prop->matchesOn() as $strategy) {
            $this->matchPropsOn[] = "{$path}.{$strategy}";
        }
    }

    /**
     * Collect scroll pagination metadata.
     *
     * @param ScrollProp<mixed> $prop
     */
    protected function collectScrollMetadata(string $path, ScrollProp $prop): void
    {
        $this->scrollProps[$path] = [
            ...$prop->metadata(),
            'reset' => \in_array($path, $this->resetProps, true),
        ];
    }

    /**
     * Collect once-prop metadata.
     */
    protected function collectOnceMetadata(string $path, mixed $prop): void
    {
        if (!$prop instanceof OnceableInterface || !$prop->shouldResolveOnce()) {
            return;
        }

        if ($this->isPartial && !$this->isIncludedInPartialMetadata($path)) {
            return;
        }

        $this->onceProps[$prop->getKey() ?? $path] = [
            'prop' => $path,
            'expiresAt' => $prop->expiresAt(),
        ];
    }

    /**
     * Determine if the path should contribute metadata during a partial request.
     */
    protected function isIncludedInPartialMetadata(string $path): bool
    {
        if (null !== $this->only && !$this->matchesOnly($path)) {
            return false;
        }

        if (null !== $this->except && $this->matchesExcept($path)) {
            return false;
        }

        return true;
    }

    /**
     * Determine if the path matches or is a descendant of an "only" filter path.
     */
    protected function matchesOnly(string $path): bool
    {
        foreach ($this->only ?? [] as $onlyPath) {
            if ($this->pathIsSameOrDescendant($path, $onlyPath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the path is an ancestor of an "only" filter path.
     */
    protected function leadsToOnly(string $path): bool
    {
        foreach ($this->only ?? [] as $onlyPath) {
            if (str_starts_with($onlyPath, "{$path}.")) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the path matches or is a descendant of an "except" filter path.
     */
    protected function matchesExcept(string $path): bool
    {
        foreach ($this->except ?? [] as $exceptPath) {
            if ($this->pathIsSameOrDescendant($path, $exceptPath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if a dot path matches another path or belongs to its subtree.
     */
    protected function pathIsSameOrDescendant(string $path, string $parent): bool
    {
        return $path === $parent || str_starts_with($path, "{$parent}.");
    }

    /**
     * Unpack top-level dot-notation keys into nested arrays.
     *
     * @param array<array-key, mixed> $props
     *
     * @return array<array-key, mixed>
     */
    protected function unpackDotProps(array $props): array
    {
        foreach ($props as $key => $value) {
            if (!\is_string($key) || !str_contains($key, '.')) {
                continue;
            }

            if ($value instanceof \Closure) {
                $value = $value();
            }

            $this->ensurePathIsTraversable($props, $key);
            Arr::set($props, $key, $value);
            unset($props[$key]);
        }

        return $props;
    }

    /**
     * Resolve closures along the intermediate segments of a dot-notation path
     * so that Arr::set() can nest into them.
     *
     * @param array<array-key, mixed> $props
     */
    protected function ensurePathIsTraversable(array &$props, string $dotKey): void
    {
        $segments = explode('.', $dotKey);
        array_pop($segments);

        $current = &$props;

        foreach ($segments as $segment) {
            if (!isset($current[$segment])) {
                return;
            }

            if ($current[$segment] instanceof \Closure) {
                $current[$segment] = $current[$segment]();
            }

            if (!\is_array($current[$segment])) {
                return;
            }

            $current = &$current[$segment];
        }
    }

    /**
     * Parse a comma-separated header value into an array.
     *
     * @return array<int, string>|null
     */
    protected function parseHeader(string $key): ?array
    {
        return array_values(array_filter(explode(',', (string) $this->request->headers->get($key, '')))) ?: null;
    }
}
