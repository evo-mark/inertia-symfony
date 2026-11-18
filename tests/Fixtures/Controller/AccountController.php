<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\Fixtures\Controller;

use EvoMark\InertiaSymfonyBundle\Attribute\EncryptHistory;
use EvoMark\InertiaSymfonyBundle\Http\Response;
use EvoMark\InertiaSymfonyBundle\Interfaces\InertiaInterface;

/**
 * Only the attributed action encrypts the history.
 */
final class AccountController
{
    public function __construct(private InertiaInterface $inertia)
    {
    }

    #[EncryptHistory]
    public function billing(): Response
    {
        return $this->inertia->render('Account/Billing');
    }

    public function dashboard(): Response
    {
        return $this->inertia->render('Dashboard');
    }
}
