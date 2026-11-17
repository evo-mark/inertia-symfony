<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\PageProps;

use EvoMark\InertiaSymfonyBundle\PageProps\MergeProp;
use EvoMark\InertiaSymfonyBundle\Tests\TestCase;

final class DeepMergePropTest extends TestCase
{
    public function testCanInvokeWithACallback(): void
    {
        $mergeProp = new MergeProp(fn () => 'A merge prop value')->deepMerge();

        $this->assertSame('A merge prop value', $mergeProp());
    }

    public function testCanInvokeWithANonCallback(): void
    {
        $mergeProp = new MergeProp(['key' => 'value'])->deepMerge();

        $this->assertSame(['key' => 'value'], $mergeProp());
    }

    public function testCanUseSingleStringAsKeyToMatchOn(): void
    {
        $mergeProp = new MergeProp(['key' => 'value'])->matchOn('key');

        $this->assertEquals(['key'], $mergeProp->matchesOn());
    }

    public function testCanUseAnArrayOfStringsAsKeysToMatchOn(): void
    {
        $mergeProp = new MergeProp(['key' => 'value'])->matchOn(['key', 'anotherKey']);

        $this->assertEquals(['key', 'anotherKey'], $mergeProp->matchesOn());
    }
}
