<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Attribute;

/**
 * Encrypts the browser history entries of the pages a controller renders, so
 * their props can't be read back from the history after the user logs out.
 *
 * On a class it applies to every action; on an action it overrides the class.
 * `#[EncryptHistory(false)]` opts out when `history.encrypt` is on globally.
 * For routes without a controller of their own (InertiaController), set the
 * `_encrypt_history` route default instead.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
final readonly class EncryptHistory
{
    public function __construct(public bool $encrypt = true)
    {
    }
}
