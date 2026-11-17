<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\EventListener;

use EvoMark\InertiaSymfonyBundle\Support\SessionKey;
use EvoMark\InertiaSymfonyBundle\Tests\Fixtures\TestKernel;
use EvoMark\InertiaSymfonyBundle\Tests\TestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Sharing validation errors stored in the {@see SessionKey::ERRORS} session
 * attribute as the `errors` prop.
 */
final class ValidationErrorsListenerTest extends TestCase
{
    /**
     * Submit (storing the given errors and redirecting), then load the page,
     * keeping the session cookie between the two requests like a browser.
     *
     * @param array<string, mixed>  $errors
     * @param array<string, string> $server
     */
    private function submitThenVisit(array $errors, array $server = []): KernelBrowser
    {
        TestKernel::$controller = function (Request $request) use ($errors) {
            if ('submit' === $request->query->get('step')) {
                $request->getSession()->set(SessionKey::ERRORS, $errors);

                return new RedirectResponse('/example-url?step=form');
            }

            return $this->inertia()->render('User/Edit');
        };

        $client = new KernelBrowser(self::bootKernel());
        $client->request('POST', '/example-url?step=submit');
        $client->request('GET', '/example-url?step=form', server: ['HTTP_X_INERTIA' => 'true', ...$server]);

        return $client;
    }

    public function testErrorsAreSharedAsAnEmptyObjectByDefault(): void
    {
        $response = $this->makeMockRequest(fn () => $this->inertia()->render('User/Edit'), ['X-Inertia' => 'true']);

        $this->assertStringContainsString('"errors":{}', (string) $response->getContent());
        $this->assertContains('errors', $this->getPage($response)['sharedProps']);
    }

    public function testTheFirstMessagePerFieldIsShared(): void
    {
        $client = $this->submitThenVisit(['default' => [
            'name' => ['The name field is required.', 'The name must be a string.'],
            'email' => 'The email must be valid.',
        ]]);

        $this->assertSame([
            'name' => 'The name field is required.',
            'email' => 'The email must be valid.',
        ], $this->getPage($client->getResponse())['props']['errors']);
    }

    public function testTheDefaultBagIsNestedUnderTheRequestedErrorBag(): void
    {
        $client = $this->submitThenVisit(
            ['default' => ['name' => ['The name field is required.']]],
            ['HTTP_X_INERTIA_ERROR_BAG' => 'createUser'],
        );

        $this->assertSame(
            ['createUser' => ['name' => 'The name field is required.']],
            $this->getPage($client->getResponse())['props']['errors'],
        );
    }

    public function testNamedBagsAreSharedWhenThereIsNoDefaultBag(): void
    {
        $client = $this->submitThenVisit([
            'login' => ['email' => ['Invalid credentials.']],
            'register' => ['name' => ['Taken.']],
        ]);

        $this->assertSame([
            'login' => ['email' => 'Invalid credentials.'],
            'register' => ['name' => 'Taken.'],
        ], $this->getPage($client->getResponse())['props']['errors']);
    }

    public function testErrorsAreOnlySharedOnce(): void
    {
        $client = $this->submitThenVisit(['default' => ['name' => ['Required.']]]);

        $client->request('GET', '/example-url?step=form', server: ['HTTP_X_INERTIA' => 'true']);

        $this->assertStringContainsString('"errors":{}', (string) $client->getResponse()->getContent());
    }

    public function testErrorsAreIncludedInPartialReloads(): void
    {
        $client = $this->submitThenVisit(['default' => ['name' => ['Required.']]], [
            'HTTP_X_INERTIA_PARTIAL_COMPONENT' => 'User/Edit',
            'HTTP_X_INERTIA_PARTIAL_DATA' => 'user',
        ]);

        $this->assertSame(['name' => 'Required.'], $this->getPage($client->getResponse())['props']['errors']);
    }
}
