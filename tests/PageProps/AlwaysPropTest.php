<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\PageProps;

use EvoMark\InertiaSymfonyBundle\PageProps\AlwaysProp;
use EvoMark\InertiaSymfonyBundle\Tests\TestCase;

final class AlwaysPropTest extends TestCase
{
    public function testCanInvoke(): void
    {
        $alwaysProp = new AlwaysProp(function () {
            return 'An always value';
        });

        $this->assertSame('An always value', $alwaysProp());
    }

    public function testCanAcceptScalarValues(): void
    {
        $alwaysProp = new AlwaysProp('An always value');

        $this->assertSame('An always value', $alwaysProp());
    }

    public function testStringFunctionNamesAreNotInvoked(): void
    {
        $alwaysProp = new AlwaysProp('date');

        $this->assertSame('date', $alwaysProp());
    }

    public function testCanAcceptCallables(): void
    {
        $callable = new class {
            public function __invoke(): string
            {
                return 'An always value';
            }
        };

        $alwaysProp = new AlwaysProp($callable);

        $this->assertSame('An always value', $alwaysProp());
    }
}
