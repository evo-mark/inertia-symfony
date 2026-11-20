<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Support;

use ArrayAccess;

/**
 * Array helpers for reading and writing nested values with dot notation.
 */
class Arr
{
    /**
     * Set an array item to a given value using "dot" notation.
     *
     * If no key is given to the method, the entire array will be replaced.
     */
    public static function set(array &$array, string|int|null $key, mixed $value): array
    {
        if (is_null($key)) {
            return $array = $value;
        }

        $keys = explode('.', $key);

        foreach ($keys as $i => $loopKey) {
            if (1 === count($keys)) {
                break;
            }

            unset($keys[$i]);

            // If the key doesn't exist at this depth, we will just create an empty array
            // to hold the next value, allowing us to create the arrays to hold final
            // values at the correct depth. Then we'll keep digging into the array.
            if (!isset($array[$loopKey]) || !is_array($array[$loopKey])) {
                $array[$loopKey] = [];
            }

            $array = &$array[$loopKey];
        }

        $array[array_shift($keys)] = $value;

        return $array;
    }

    /**
     * Get an item from an array using "dot" notation.
     *
     * @param \ArrayAccess|array $array
     * @param string|int|null    $key
     */
    public static function get($array, $key, $default = null)
    {
        if (!static::accessible($array)) {
            return General::value($default);
        }

        if (is_null($key)) {
            return $array;
        }

        if (static::exists($array, $key)) {
            return $array[$key];
        }

        if (!str_contains($key, '.')) {
            return General::value($default);
        }

        foreach (explode('.', $key) as $segment) {
            if (static::accessible($array) && static::exists($array, $segment)) {
                $array = $array[$segment];
            } else {
                return General::value($default);
            }
        }

        return $array;
    }

    /**
     * Determine if the given key exists in the provided array.
     *
     * @param array                 $array
     * @param string|int|float|null $key
     *
     * @return bool
     */
    public static function exists($array, $key)
    {
        if ($array instanceof \ArrayAccess) {
            return $array->offsetExists($key);
        }

        if (is_float($key) || is_null($key)) {
            $key = (string) $key;
        }

        return array_key_exists($key, $array);
    }

    /**
     * Determine whether the given value is array accessible.
     */
    public static function accessible(mixed $value): bool
    {
        return is_array($value) || $value instanceof \ArrayAccess;
    }
}
