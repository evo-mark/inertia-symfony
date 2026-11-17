<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\Stubs;

/**
 * Paginated user data: a page of users plus pagination fields.
 */
final class Users
{
    /**
     * @return array{data: list<array{id: int, name: string}>}
     */
    public static function page(int $perPage = 15, int $page = 1): array
    {
        $first = ($page - 1) * $perPage + 1;

        return [
            'data' => array_map(
                static fn (int $id) => ['id' => $id, 'name' => "User {$id}"],
                range($first, $first + $perPage - 1),
            ),
        ];
    }
}
