<?php

namespace EvoMark\InertiaSymfonyBundle\Command;

use EvoMark\InertiaSymfonyBundle\Interfaces\SsrGatewayInterface;
use EvoMark\InertiaSymfonyBundle\Interfaces\SsrHasHealthCheckInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Asks the SSR gateway whether the SSR server is up, for deploy scripts and
 * health checks: exits 0 when it is running, 1 when it is not.
 */
#[AsCommand(name: 'inertia:check-ssr', description: 'Check the Inertia SSR server health status')]
final class CheckSsrCommand
{
    public function __construct(private SsrGatewayInterface $gateway)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        if (!$this->gateway instanceof SsrHasHealthCheckInterface) {
            $io->error('The SSR gateway does not support health checks.');

            return Command::FAILURE;
        }

        if (!$this->gateway->isHealthy()) {
            $io->error('Inertia SSR server is not running.');

            return Command::FAILURE;
        }

        $io->success('Inertia SSR server is running.');

        return Command::SUCCESS;
    }
}
