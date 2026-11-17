<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\Http;

use EvoMark\InertiaSymfonyBundle\Http\Response;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Big integer wrapping on {@see Response}, through toPage() directly. The
 * render()-based coverage lives in PropsResolverTest and ResponseFactoryTest.
 */
final class ResponseBigIntegersTest extends TestCase
{
    private const int BIG = 900719925474099988;

    private const array MARKER = ['$bigint' => '900719925474099988'];

    private function page(Response $response): array
    {
        return $response->toPage(Request::create('/'));
    }

    public function testBigIntegersAreUntouchedByDefault(): void
    {
        $page = $this->page(new Response('Page', [], ['id' => self::BIG]));

        $this->assertSame(self::BIG, $page['props']['id']);
        $this->assertArrayNotHasKey('preserveBigIntegers', $page);
    }

    public function testTheConstructorFlagWrapsBigIntegers(): void
    {
        $page = $this->page(new Response('Page', [], ['id' => self::BIG, 'safe' => 42], preserveBigIntegers: true));

        $this->assertSame(self::MARKER, $page['props']['id']);
        $this->assertSame(42, $page['props']['safe']);
        $this->assertTrue($page['preserveBigIntegers']);
    }

    public function testASingleResponseCanOptInAndOut(): void
    {
        $in = $this->page(new Response('Page', [], ['id' => self::BIG])->preserveBigIntegers());
        $out = $this->page(new Response('Page', [], ['id' => self::BIG], preserveBigIntegers: true)->preserveBigIntegers(false));

        $this->assertSame(self::MARKER, $in['props']['id']);
        $this->assertSame(self::BIG, $out['props']['id']);
        $this->assertArrayNotHasKey('preserveBigIntegers', $out);
    }

    public function testNegativeBigIntegersAndSharedPropsAreWrapped(): void
    {
        $page = $this->page(new Response('Page', ['shared' => -self::BIG], ['nested' => ['id' => self::BIG]], preserveBigIntegers: true));

        $this->assertSame(['$bigint' => '-900719925474099988'], $page['props']['shared']);
        $this->assertSame(self::MARKER, $page['props']['nested']['id']);
    }

    public function testFlashDataIsWrapped(): void
    {
        $page = $this->page(new Response('Page', [], [], preserveBigIntegers: true)->flash('id', self::BIG));

        $this->assertSame(self::MARKER, $page['flash']['id']);
    }

    public function testArrayObjectsKeepTheirShape(): void
    {
        $page = $this->page(new Response('Page', [], ['list' => new \ArrayObject(['id' => self::BIG])], preserveBigIntegers: true));

        $this->assertEquals((object) ['id' => self::MARKER], $page['props']['list']);
    }
}
