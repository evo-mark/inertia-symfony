<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\Fixtures\Controller;

use EvoMark\InertiaSymfonyBundle\Attribute\EncryptHistory;
use EvoMark\InertiaSymfonyBundle\Http\Response;
use EvoMark\InertiaSymfonyBundle\Interfaces\InertiaInterface;

/**
 * Every action encrypts the history, unless it opts out.
 */
#[EncryptHistory]
final class EncryptedController
{
    public function __construct(private InertiaInterface $inertia)
    {
    }

    public function show(): Response
    {
        return $this->inertia->render('Account/Show');
    }

    #[EncryptHistory(false)]
    public function public(): Response
    {
        return $this->inertia->render('Account/Public');
    }
}
