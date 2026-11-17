<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\Twig;

use EvoMark\InertiaSymfonyBundle\Interfaces\SsrGatewayInterface;
use EvoMark\InertiaSymfonyBundle\Tests\Stubs\FakeGateway;
use EvoMark\InertiaSymfonyBundle\Tests\TestCase;
use Twig\Environment;

/**
 * The `inertiaApp()` and `inertiaHead()` Twig functions, called with no id, the
 * default id or an empty one, with and without server-side rendering.
 */
final class InertiaExtensionTest extends TestCase
{
    private FakeGateway $gateway;

    /**
     * Boot with SSR on or off and swap in the fake gateway.
     */
    private function boot(bool $ssr): void
    {
        $this->configure(['ssr' => ['enabled' => $ssr]]);
        self::bootKernel();

        $this->gateway = new FakeGateway($ssr);
        static::getContainer()->set(SsrGatewayInterface::class, $this->gateway);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderView(string $contents, array $data = []): string
    {
        /** @var Environment $twig */
        $twig = static::getContainer()->get('twig');

        return $twig->createTemplate($contents)->render($data);
    }

    public function testInertiaFunctionRendersTheRootElement(): void
    {
        $this->boot(false);

        $html = '<script data-page="app" type="application/json">{"component":"Foo\/Bar","props":{"foo":"bar"},"url":"\/test","version":""}</script><div id="app"></div>';

        $this->assertSame($html, $this->renderView('{{ inertiaApp(page) }}', ['page' => self::EXAMPLE_PAGE_OBJECT]));
        $this->assertSame($html, $this->renderView('{{ inertiaApp(page, "") }}', ['page' => self::EXAMPLE_PAGE_OBJECT]));
        $this->assertSame($html, $this->renderView("{{ inertiaApp(page, '') }}", ['page' => self::EXAMPLE_PAGE_OBJECT]));
    }

    public function testInertiaFunctionRendersServerSideRenderedContentWhenEnabled(): void
    {
        $this->boot(true);

        $this->assertSame(
            '<p>This is some example SSR content</p>',
            $this->renderView('{{ inertiaApp(page) }}', ['page' => self::EXAMPLE_PAGE_OBJECT]),
        );
    }

    public function testInertiaFunctionCanUseADifferentRootElementId(): void
    {
        $this->boot(false);

        $html = '<script data-page="foo" type="application/json">{"component":"Foo\/Bar","props":{"foo":"bar"},"url":"\/test","version":""}</script><div id="foo"></div>';

        $this->assertSame($html, $this->renderView("{{ inertiaApp(page, 'foo') }}", ['page' => self::EXAMPLE_PAGE_OBJECT]));
        $this->assertSame($html, $this->renderView('{{ inertiaApp(page, "foo") }}', ['page' => self::EXAMPLE_PAGE_OBJECT]));
        $this->assertSame($html, $this->renderView('{{ inertiaApp(page, id="foo") }}', ['page' => self::EXAMPLE_PAGE_OBJECT]));
    }

    public function testInertiaFunctionEscapesHtmlTagsInThePageData(): void
    {
        $this->boot(false);

        $page = ['component' => 'Foo/Bar', 'props' => ['foo' => '</script><!--<script>'], 'url' => '/test', 'version' => ''];
        $html = $this->renderView('{{ inertiaApp(page) }}', ['page' => $page]);

        $this->assertStringContainsString('\u003C\/script\u003E\u003C!--\u003Cscript\u003E', $html);
        $this->assertStringNotContainsString('<!--', $html);
        $this->assertSame(1, substr_count($html, '</script>'));
    }

    public function testInertiaHeadFunctionRendersNothing(): void
    {
        $this->boot(false);

        $this->assertEmpty($this->renderView('{{ inertiaHead(page) }}', ['page' => self::EXAMPLE_PAGE_OBJECT]));
    }

    public function testInertiaHeadFunctionRendersServerSideRenderedHeadElementsWhenEnabled(): void
    {
        $this->boot(true);

        $this->assertSame(
            "<meta charset=\"UTF-8\" />\n<title inertia>Example SSR Title</title>\n",
            $this->renderView('{{ inertiaHead(page) }}', ['page' => self::EXAMPLE_PAGE_OBJECT]),
        );
    }

    public function testTheServerSideRenderingRequestIsDispatchedOnlyOncePerRequest(): void
    {
        $this->boot(true);

        $view = "<!DOCTYPE html>\n<html>\n<head>\n{{ inertiaHead(page) }}\n</head>\n<body>\n{{ inertiaApp(page) }}\n</body>\n</html>";
        $expected = "<!DOCTYPE html>\n<html>\n<head>\n<meta charset=\"UTF-8\" />\n<title inertia>Example SSR Title</title>\n\n</head>\n<body>\n<p>This is some example SSR content</p>\n</body>\n</html>";

        $this->assertSame(
            $expected,
            $this->renderView($view, ['page' => self::EXAMPLE_PAGE_OBJECT]),
        );

        $this->assertSame(1, $this->gateway->times);
    }
}
