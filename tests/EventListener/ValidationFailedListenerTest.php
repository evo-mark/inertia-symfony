<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\EventListener;

use EvoMark\InertiaSymfonyBundle\Tests\Fixtures\TestKernel;
use EvoMark\InertiaSymfonyBundle\Tests\TestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * The form submission throws what #[MapRequestPayload] throws when the payload
 * is invalid: an HTTP exception wrapping a ValidationFailedException.
 */
final class ValidationFailedListenerTest extends TestCase
{
    private const array INERTIA = ['HTTP_X_INERTIA' => 'true', 'HTTP_REFERER' => '/example-url?step=form'];

    private static function invalidPayload(): HttpException
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('This value should not be blank.', null, [], null, 'name', ''),
            new ConstraintViolation('This value is not a valid email address.', null, [], null, 'email', 'nope'),
            new ConstraintViolation('This value is too short.', null, [], null, 'email', 'nope'),
        ]);

        return HttpException::fromStatusCode(422, 'Invalid payload.', new ValidationFailedException([], $violations));
    }

    /**
     * `?step=submit` throws the validation failure; anything else renders the form.
     */
    private function client(): KernelBrowser
    {
        TestKernel::$controller = function (Request $request) {
            if ('submit' === $request->query->get('step')) {
                throw self::invalidPayload();
            }

            return $this->inertia()->render('Form');
        };

        return new KernelBrowser(self::bootKernel());
    }

    public function testAnInvalidSubmissionRedirectsBack(): void
    {
        $client = $this->client();
        $client->request('POST', '/example-url?step=submit', server: self::INERTIA);

        $this->assertTrue($client->getResponse()->isRedirect('/example-url?step=form'));
    }

    public function testTheNextPageReceivesTheFirstErrorPerField(): void
    {
        $client = $this->client();
        $client->request('POST', '/example-url?step=submit', server: self::INERTIA);
        $client->request('GET', '/example-url?step=form', server: ['HTTP_X_INERTIA' => 'true']);

        $this->assertSame([
            'name' => 'This value should not be blank.',
            'email' => 'This value is not a valid email address.',
        ], $this->getPage($client->getResponse())['props']['errors']);
    }

    public function testEveryErrorPerFieldCanBeSent(): void
    {
        $this->configure(['with_all_errors' => true]);

        $client = $this->client();
        $client->request('POST', '/example-url?step=submit', server: self::INERTIA);
        $client->request('GET', '/example-url?step=form', server: ['HTTP_X_INERTIA' => 'true']);

        $this->assertSame(
            ['This value is not a valid email address.', 'This value is too short.'],
            $this->getPage($client->getResponse())['props']['errors']['email'],
        );
    }

    public function testErrorsAreNestedUnderTheRequestedErrorBag(): void
    {
        $client = $this->client();
        $client->request('POST', '/example-url?step=submit', server: self::INERTIA);
        $client->request('GET', '/example-url?step=form', server: ['HTTP_X_INERTIA' => 'true', 'HTTP_X_INERTIA_ERROR_BAG' => 'contact']);

        $this->assertArrayHasKey('name', $this->getPage($client->getResponse())['props']['errors']['contact']);
    }

    public function testInvalidPutRequestsRedirectWithA303(): void
    {
        $client = $this->client();
        $client->request('PUT', '/example-url?step=submit', server: self::INERTIA);

        $this->assertSame(303, $client->getResponse()->getStatusCode());
    }

    public function testNonInertiaRequestsKeepSymfonysResponse(): void
    {
        $client = $this->client();
        $client->request('POST', '/example-url?step=submit', server: ['HTTP_REFERER' => '/example-url?step=form']);

        $this->assertSame(422, $client->getResponse()->getStatusCode());
    }

    public function testOtherExceptionsAreNotTouched(): void
    {
        $response = $this->makeMockRequest(function () {
            throw HttpException::fromStatusCode(422, 'Not a validation failure.');
        }, ['X-Inertia' => 'true', 'Referer' => '/somewhere'], 'POST');

        $this->assertSame(422, $response->getStatusCode());
    }

    public function testErrorsCanBeSentByHandWithWithErrors(): void
    {
        TestKernel::$controller = function (Request $request) {
            if ('submit' === $request->query->get('step')) {
                return $this->inertia()->withErrors(['email' => 'These credentials do not match our records.'])->back();
            }

            return $this->inertia()->render('Form');
        };
        $client = new KernelBrowser(self::bootKernel());

        $client->request('POST', '/example-url?step=submit', server: self::INERTIA);
        $client->request('GET', '/example-url?step=form', server: ['HTTP_X_INERTIA' => 'true']);

        $this->assertSame(
            ['email' => 'These credentials do not match our records.'],
            $this->getPage($client->getResponse())['props']['errors'],
        );
    }
}
