<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\Support;

use EvoMark\InertiaSymfonyBundle\Support\ValidationErrors;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

final class ValidationErrorsTest extends TestCase
{
    private static function violation(string $path, string $message): ConstraintViolation
    {
        return new ConstraintViolation($message, null, [], null, $path, null);
    }

    public function testViolationsAreGroupedByField(): void
    {
        $errors = ValidationErrors::fromViolations(new ConstraintViolationList([
            self::violation('email', 'This value should not be blank.'),
            self::violation('email', 'This value is not a valid email address.'),
            self::violation('name', 'This value is too short.'),
        ]));

        $this->assertSame([
            'email' => ['This value should not be blank.', 'This value is not a valid email address.'],
            'name' => ['This value is too short.'],
        ], $errors);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function paths(): iterable
    {
        yield 'property' => ['email', 'email'];
        yield 'nested object' => ['address.street', 'address.street'];
        yield 'collection item' => ['items[0].name', 'items.0.name'];
        yield 'array keys' => ['[items][0][name]', 'items.0.name'];
        yield 'root array key' => ['[email]', 'email'];
        yield 'whole object' => ['', ''];
    }

    #[DataProvider('paths')]
    public function testPropertyPathsBecomeDotPaths(string $propertyPath, string $field): void
    {
        $this->assertSame($field, ValidationErrors::field($propertyPath));
    }

    public function testArraysAreNormalisedToLists(): void
    {
        $this->assertSame(
            ['email' => ['Taken.'], 'password' => ['Too short.', 'Too common.']],
            ValidationErrors::fromArray(['email' => 'Taken.', 'password' => ['Too short.', 'Too common.']]),
        );
    }
}
