<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Http;

use BackedEnum;
use Closure;
use EvoMark\InertiaSymfonyBundle\Interfaces\ProvidesInertiaPropertiesInterface;
use EvoMark\InertiaSymfonyBundle\Support\Header;
use EvoMark\InertiaSymfonyBundle\Support\SessionKey;
use EvoMark\InertiaSymfonyBundle\Traits\PreservesBigIntegers;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Twig\Environment;
use UnitEnum;

/**
 * An Inertia page ready to be turned into an HTTP response: JSON for Inertia
 * visits, or the root Twig view on the initial full page load.
 */
class Response implements LoggerAwareInterface
{
    use LoggerAwareTrait;
    use PreservesBigIntegers;

    /**
     * @var array<string, mixed>
     */
    protected array $viewData = [];

    /**
     * Indicates if the browser history should be cleared. Null until read from
     * the session when the page is built.
     */
    protected ?bool $clearHistory = null;

    /**
     * Indicates if the URL fragment should be preserved across redirects. Null
     * until read from the session when the page is built.
     */
    protected ?bool $preserveFragment = null;

    /**
     * Flash data for this response; merged over the session's flash data.
     *
     * @var array<string, mixed>
     */
    protected array $flash = [];

    /**
     * Whether the top-level shared prop keys are sent as `sharedProps` metadata.
     */
    protected bool $exposeSharedPropKeys = true;

    /**
     * Twig, for rendering the root view on a full page load.
     */
    protected ?Environment $engine = null;

    /**
     * The Serializer for encoding the page; `json_encode` is used without one.
     */
    protected ?SerializerInterface $serializer = null;

    /**
     * Extra Serializer context for encoding the page.
     *
     * @var array<string, mixed>
     */
    protected array $serializationContext = [];

    /**
     * Whether the session data has been pulled into this response yet.
     */
    private bool $sessionPulled = false;

    /**
     * @param array<array-key, mixed>          $sharedProps props shared via Inertia::share()
     * @param array<array-key, mixed>          $props       the page's own props; they override shared props
     * @param (\Closure(Request): string)|null $urlResolver resolves the page URL; defaults to the request URI
     */
    public function __construct(
        protected string $component,
        protected array $sharedProps,
        protected array $props,
        protected string $rootView = 'app',
        protected string $version = '',
        protected bool $encryptHistory = false,
        protected ?\Closure $urlResolver = null,
        protected bool $preserveBigIntegers = false,
    ) {
    }

    /**
     * Give the response the services it needs to build itself, so toResponse()
     * only takes the request. The Inertia service calls this from render().
     */
    public function withServices(Environment $engine, ?SerializerInterface $serializer = null): static
    {
        $this->engine = $engine;
        $this->serializer = $serializer;

        return $this;
    }

    /**
     * Add Serializer context used when encoding the page.
     *
     * @param array<string, mixed> $context
     */
    public function serializationContext(array $context): static
    {
        $this->serializationContext = [...$this->serializationContext, ...$context];

        return $this;
    }

    /**
     * Send (or hide) the top-level shared prop keys as `sharedProps` metadata,
     * the `expose_shared_prop_keys` config.
     */
    public function exposeSharedPropKeys(bool $expose = true): static
    {
        $this->exposeSharedPropKeys = $expose;

        return $this;
    }

    /**
     * Add flash data to the response. Unlike props, flash data is not stored in
     * the browser's history state.
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

        $this->flash = [...$this->flash, ...$key];

        return $this;
    }

    /**
     * Add props to the page: a key and value, an array of props, or a props provider.
     */
    public function with(string|array|ProvidesInertiaPropertiesInterface $key, mixed $value = null): static
    {
        if ($key instanceof ProvidesInertiaPropertiesInterface) {
            $this->props[] = $key;
        } elseif (\is_array($key)) {
            $this->props = array_merge($this->props, $key);
        } else {
            $this->props[$key] = $value;
        }

        return $this;
    }

    /**
     * Add variables to the root view (not sent to the client).
     */
    public function withViewData(string|array $key, mixed $value = null): static
    {
        if (\is_array($key)) {
            $this->viewData = array_merge($this->viewData, $key);
        } else {
            $this->viewData[$key] = $value;
        }

        return $this;
    }

    /**
     * Render this response with a different root view.
     */
    public function rootView(string $rootView): static
    {
        $this->rootView = $rootView;

        return $this;
    }

    /**
     * The page component this response renders.
     */
    public function getComponent(): string
    {
        return $this->component;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getSharedProps(): array
    {
        return $this->sharedProps;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getProps(): array
    {
        return $this->props;
    }

    /**
     * The Twig template the page is rendered into on a full page load.
     */
    public function getRootView(): string
    {
        return $this->rootView;
    }

    /**
     * The asset version sent with the page.
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        return $this->viewData;
    }

    /**
     * Build the page object sent to the client.
     *
     * @return array<string, mixed>
     */
    public function toPage(Request $request): array
    {
        $this->pullSessionData($request);

        $resolver = new PropsResolver($request, $this->component, $this->exposeSharedPropKeys, $this->logger);
        [$props, $metadata] = $resolver->resolve($this->sharedProps, $this->props);

        return [
            'component' => $this->component,
            'props' => $this->encodeBigIntegersWhenEnabled($props),
            'url' => $this->getUrl($request),
            'version' => $this->version,
            ...$metadata,
            ...$this->resolvePreserveBigIntegers(),
            ...$this->resolveClearHistory(),
            ...$this->resolveEncryptHistory(),
            ...$this->resolveFlashData(),
            ...$this->resolvePreserveFragment(),
        ];
    }

    /**
     * Build the Symfony response for the given request: JSON for an Inertia
     * visit, otherwise the root view.
     *
     * @throws \LogicException when a full page load is built without Twig, see withServices()
     */
    public function toResponse(Request $request): SymfonyResponse
    {
        $json = $this->encode($this->toPage($request));

        if ($request->headers->has(Header::INERTIA)) {
            // The encoded page is sent as-is: decoding and re-encoding it would
            // lose keys that a PHP object can't hold, such as "\0*\0protected".
            return JsonResponse::fromJsonString($json, SymfonyResponse::HTTP_OK, [
                Header::INERTIA => 'true',
            ]);
        }

        if (null === $this->engine) {
            throw new \LogicException(\sprintf('Rendering the "%s" root view needs Twig: create Inertia responses with the Inertia service, or call withServices() first.', $this->rootView));
        }

        // Decoded to objects, not arrays, so empty objects stay `{}` when the
        // root view encodes the page again.
        return new SymfonyResponse($this->engine->render($this->rootView, [
            ...$this->viewData,
            'page' => (array) json_decode($json, false, flags: \JSON_THROW_ON_ERROR),
        ]));
    }

    /**
     * Move Inertia's one-time session data into this response, removing it from
     * the session: clear history and preserve fragment flags, plus flash data,
     * which this response's own flash() calls override. Runs once, so
     * building the page twice gives the same result.
     */
    protected function pullSessionData(Request $request): void
    {
        if ($this->sessionPulled) {
            return;
        }

        $this->sessionPulled = true;
        $session = $this->getSession($request);

        $this->clearHistory ??= (bool) $session?->remove(SessionKey::CLEAR_HISTORY);
        $this->preserveFragment ??= (bool) $session?->remove(SessionKey::PRESERVE_FRAGMENT);
        $this->flash = [...(array) ($session?->remove(SessionKey::FLASH_DATA) ?? []), ...$this->flash];
    }

    /**
     * The request's session, but only if it is already in use: reading an
     * unstarted session would start one (and set a cookie) on every page.
     */
    protected function getSession(Request $request): ?SessionInterface
    {
        if (!$request->hasSession()) {
            return null;
        }

        $session = $request->getSession();

        return $session->isStarted() || $request->hasPreviousSession() ? $session : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveClearHistory(): array
    {
        return $this->clearHistory ? ['clearHistory' => true] : [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveEncryptHistory(): array
    {
        return $this->encryptHistory ? ['encryptHistory' => true] : [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveFlashData(): array
    {
        return $this->flash ? ['flash' => $this->encodeBigIntegersWhenEnabled($this->flash)] : [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolvePreserveBigIntegers(): array
    {
        return $this->preserveBigIntegers ? ['preserveBigIntegers' => true] : [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolvePreserveFragment(): array
    {
        return $this->preserveFragment ? ['preserveFragment' => true] : [];
    }

    /**
     * The page URL, from the URL resolver if one is set.
     */
    protected function getUrl(Request $request): string
    {
        if (null !== $this->urlResolver) {
            return ($this->urlResolver)($request);
        }

        // The full URL minus the scheme and host. getUri() includes the base path
        // and any trusted X-Forwarded-Prefix, which getRequestUri() does not.
        return substr($request->getUri(), \strlen($request->getSchemeAndHttpHost()));
    }

    /**
     * Encode the page with the Symfony Serializer if available, otherwise `json_encode`.
     *
     * @see https://github.com/OWASP/CheatSheetSeries/blob/master/cheatsheets/AJAX_Security_Cheat_Sheet.md#always-return-json-with-an-object-on-the-outside
     *
     * @param array<string, mixed> $page
     */
    private function encode(array $page): string
    {
        if (null !== $this->serializer) {
            return $this->serializer->serialize($page, 'json', array_merge([
                'json_encode_options' => JsonResponse::DEFAULT_ENCODING_OPTIONS,
                AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => static fn () => null,
                AbstractObjectNormalizer::PRESERVE_EMPTY_OBJECTS => true,
                AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
            ], $this->serializationContext));
        }

        return json_encode($page, JsonResponse::DEFAULT_ENCODING_OPTIONS | \JSON_THROW_ON_ERROR);
    }
}
