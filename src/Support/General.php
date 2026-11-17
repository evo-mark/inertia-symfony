<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Support;

use Closure;

class General
{
    /**
     * Return the default value of the given value.
     *
     * @template TValue
     * @template TArgs
     *
     * @param TValue|\Closure(TArgs): TValue $value
     * @param TArgs                          ...$args
     *
     * @return TValue
     */
    public static function value($value, ...$args)
    {
        return $value instanceof \Closure ? $value(...$args) : $value;
    }
}
