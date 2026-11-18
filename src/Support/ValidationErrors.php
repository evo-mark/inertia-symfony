<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Support;

use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Turns validator violations into the `errors` shape the Inertia client reads:
 * field => messages, keyed by the dot paths a form uses (`address.street`,
 * `items.0.name`).
 */
final class ValidationErrors
{
    /**
     * @return array<string, list<string>>
     */
    public static function fromViolations(ConstraintViolationListInterface $violations): array
    {
        $errors = [];

        foreach ($violations as $violation) {
            $errors[self::field($violation->getPropertyPath())][] = (string) $violation->getMessage();
        }

        return $errors;
    }

    /**
     * Normalise errors given as field => message or field => messages.
     *
     * @param array<string, string|list<string>> $errors
     *
     * @return array<string, list<string>>
     */
    public static function fromArray(array $errors): array
    {
        return array_map(static fn (string|array $messages): array => array_values((array) $messages), $errors);
    }

    /**
     * A violation's property path as a dot path: `items[0].name` and
     * `[items][0][name]` (from validating an array) both become `items.0.name`.
     * Violations of the whole object (no path) are keyed by an empty string; give
     * class-level constraints a path with `atPath()` to attach them to a field.
     */
    public static function field(string $propertyPath): string
    {
        return ltrim((string) preg_replace('/\[([^\]]*)\]/', '.$1', $propertyPath), '.');
    }
}
