<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\Stubs;

/**
 * An object that serializes to an array.
 */
final class FakeResource implements \JsonSerializable
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(private array $data)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function make(array $data): self
    {
        return new self($data);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->data;
    }
}
