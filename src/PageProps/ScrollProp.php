<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\PageProps;

use Closure;
use EvoMark\InertiaSymfonyBundle\Interfaces\DeferrableInterface;
use EvoMark\InertiaSymfonyBundle\Interfaces\MergeableInterface;
use EvoMark\InertiaSymfonyBundle\Interfaces\ProvidesScrollMetadataInterface;
use EvoMark\InertiaSymfonyBundle\Support\Header;
use EvoMark\InertiaSymfonyBundle\Traits\DefersProps;
use EvoMark\InertiaSymfonyBundle\Traits\MergesProps;
use EvoMark\InertiaSymfonyBundle\Traits\ResolvesCallables;
use LogicException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Represents a paginated property that can be merged during partial reloads.
 *
 * This class provides functionality for handling pagination data with merge capabilities,
 * allowing paginated content to be appended or prepended during client-side navigation.
 *
 * @template T
 */
class ScrollProp implements DeferrableInterface, MergeableInterface
{
    use DefersProps;
    use MergesProps;
    use ResolvesCallables;

    /**
     * The resolved property value.
     *
     * @var T
     */
    protected mixed $resolved;

    /**
     * The scroll metadata provider, or a callable that builds one from the value.
     *
     * @var ProvidesScrollMetadataInterface|\Closure(T): ProvidesScrollMetadataInterface|null
     */
    protected ProvidesScrollMetadataInterface|\Closure|null $metadata;

    /**
     * Create a new merge property instance. Merge properties are combined
     * with existing client-side data during partial reloads instead of
     * completely replacing the property value.
     *
     * @param T                                                                                 $value
     * @param ProvidesScrollMetadataInterface|callable(T): ProvidesScrollMetadataInterface|null $metadata
     */
    public function __construct(
        protected mixed $value,
        protected string $wrapper = 'data',
        ProvidesScrollMetadataInterface|callable|null $metadata = null,
    ) {
        $this->metadata = $metadata instanceof ProvidesScrollMetadataInterface || null === $metadata
            ? $metadata
            : $metadata(...);
        $this->merge = true;
    }

    /**
     * Configure the merge strategy based on the infinite scroll merge intent header.
     *
     * The frontend InfiniteScroll component sends its merge intent directly,
     * eliminating the need for direction-based logic on the backend.
     */
    public function configureMergeIntent(Request $request): static
    {
        return 'prepend' === $request->headers->get(Header::INFINITE_SCROLL_MERGE_INTENT)
            ? $this->prepend($this->wrapper)
            : $this->append($this->wrapper);
    }

    /**
     * Resolve the scroll metadata provider.
     */
    protected function resolveMetadataProvider(): ProvidesScrollMetadataInterface
    {
        if ($this->metadata instanceof ProvidesScrollMetadataInterface) {
            return $this->metadata;
        }

        if (null === $this->metadata) {
            throw new \LogicException(\sprintf('A scroll prop needs a metadata provider: pass a "%s" or a callable returning one.', ProvidesScrollMetadataInterface::class));
        }

        return ($this->metadata)($this());
    }

    /**
     * Get the pagination meta information.
     *
     * @return array{pageName: string, previousPage: int|string|null, nextPage: int|string|null, currentPage: int|string|null}
     */
    public function metadata(): array
    {
        $metadataProvider = $this->resolveMetadataProvider();

        return [
            'pageName' => $metadataProvider->getPageName(),
            'previousPage' => $metadataProvider->getPreviousPage(),
            'nextPage' => $metadataProvider->getNextPage(),
            'currentPage' => $metadataProvider->getCurrentPage(),
        ];
    }

    /**
     * Resolve the property value.
     *
     * @return T
     */
    public function __invoke(): mixed
    {
        if (isset($this->resolved)) {
            return $this->resolved;
        }

        return $this->resolved = $this->resolveCallable($this->value);
    }
}
