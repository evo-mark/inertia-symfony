<?php

namespace EvoMark\InertiaSymfonyBundle\Traits;

/**
 * Prop closures are called with no arguments. Capture what a closure needs
 * instead:
 *
 *     $inertia->defer(fn () => $repository->findFor($request->query->get('id')));
 */
trait ResolvesCallables
{
    /**
     * Call the given value if callable, with no arguments.
     */
    protected function resolveCallable(mixed $value): mixed
    {
        return $this->useAsCallable($value) ? $value() : $value;
    }

    /**
     * Determine if the given value is callable, but not a string.
     */
    protected function useAsCallable(mixed $value): bool
    {
        return is_object($value) && is_callable($value);
    }
}
