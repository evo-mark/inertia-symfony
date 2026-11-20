<?php

namespace EvoMark\InertiaSymfonyBundle\Command;

use EvoMark\InertiaSymfonyBundle\Ssr\HttpGateway;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Asks the SSR server to shut down through its `/shutdown` endpoint.
 *
 * The server shuts down by closing the connection without a reply. curl reports
 * that (CURLE_GOT_NOTHING) separately from "nothing is listening", which the
 * HttpClient abstraction does not, so this uses ext-curl directly.
 */
#[AsCommand(name: 'inertia:stop-ssr', description: 'Stop the Inertia SSR server')]
final class StopSsrCommand
{
    /**
     * @param HttpGateway $gateway provides the SSR server URL
     */
    public function __construct(private HttpGateway $gateway)
    {
    }

    /**
     * Ask the SSR server to shut down.
     *
     * @param bool $graceful succeed when no server is running
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Option('Return a successful exit code when the SSR server is not running')] bool $graceful = false,
    ): int {
        $curl = curl_init($this->gateway->getProductionUrl('/shutdown'));
        curl_setopt_array($curl, [
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_TIMEOUT => 10,
        ]);
        curl_exec($curl);
        $errno = curl_errno($curl);

        if (\CURLE_GOT_NOTHING === $errno) {
            $io->success('Inertia SSR server stopped.');

            return Command::SUCCESS;
        }

        if ($graceful && \CURLE_OK !== $errno) {
            $io->comment('Inertia SSR server is not running.');

            return Command::SUCCESS;
        }

        $io->error('Unable to connect to Inertia SSR server.');

        return Command::FAILURE;
    }
}
