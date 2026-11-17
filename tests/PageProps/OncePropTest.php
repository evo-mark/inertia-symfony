<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\PageProps;

use EvoMark\InertiaSymfonyBundle\PageProps\OnceProp;
use EvoMark\InertiaSymfonyBundle\Tests\Stubs\TestBackedEnum;
use EvoMark\InertiaSymfonyBundle\Tests\Stubs\TestUnitEnum;
use EvoMark\InertiaSymfonyBundle\Tests\TestCase;

final class OncePropTest extends TestCase
{
    public function testCanInvokeWithACallback(): void
    {
        $onceProp = new OnceProp(function () {
            return 'A once prop value';
        });

        $this->assertSame('A once prop value', $onceProp());
    }

    public function testStringFunctionNamesAreNotInvoked(): void
    {
        $onceProp = new OnceProp('date');

        $this->assertSame('date', $onceProp());
    }

    public function testCanSetCustomKey(): void
    {
        $onceProp = new OnceProp(fn () => 'value');

        $result = $onceProp->as('custom-key');
        $this->assertSame($onceProp, $result);
        $this->assertSame('custom-key', $onceProp->getKey());

        $onceProp->as(TestBackedEnum::Foo);
        $this->assertSame('foo-value', $onceProp->getKey());

        $onceProp->as(TestUnitEnum::Baz);
        $this->assertSame('Baz', $onceProp->getKey());
    }

    public function testShouldNotBeRefreshedByDefault(): void
    {
        $onceProp = new OnceProp(fn () => 'value');

        $this->assertFalse($onceProp->shouldBeRefreshed());
    }

    public function testCanForcefullyRefresh(): void
    {
        $onceProp = new OnceProp(fn () => 'value');
        $onceProp->fresh();

        $this->assertTrue($onceProp->shouldBeRefreshed());
    }

    public function testCanDisableForcefulRefresh(): void
    {
        $onceProp = new OnceProp(fn () => 'value');
        $onceProp->fresh();
        $onceProp->fresh(false);

        $this->assertFalse($onceProp->shouldBeRefreshed());
    }
}
