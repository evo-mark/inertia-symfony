<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\Enums;

enum StringBackedEnum: string
{
    case UsersIndex = 'UsersPage/Index';
}
