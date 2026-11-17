<?php

declare(strict_types=1);

namespace EvoMark\InertiaSymfonyBundle\Tests\Http;

use EvoMark\InertiaSymfonyBundle\Http\ExceptionResponse;
use EvoMark\InertiaSymfonyBundle\Tests\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Rendering Inertia error pages for thrown {@see HttpException}s, through the
 * handler registered with `handleExceptionsUsing()`.
 */
final class ExceptionResponseTest extends TestCase
{
    public function testExceptionsAreNotInterceptedWithoutHandler(): void
    {
        $response = $this->makeMockRequest(function () {
            throw new HttpException(500);
        }, ['X-Inertia' => 'true']);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertFalse($response->headers->has('X-Inertia'));
    }

    public function testExceptionsCanRenderInertiaPages(): void
    {
        $this->inertia()->handleExceptionsUsing(function (ExceptionResponse $response) {
            return $response->render('Error', [
                'status' => $response->statusCode(),
                'message' => $response->exception->getMessage(),
            ]);
        });

        $response = $this->makeMockRequest(function () {
            throw new HttpException(500, 'Something went wrong');
        }, ['X-Inertia' => 'true']);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertArrayContains([
            'component' => 'Error',
            'props' => [
                'status' => 500,
                'message' => 'Something went wrong',
            ],
        ], $this->getPage($response));
    }

    public function testExceptionsCanReturnRedirects(): void
    {
        $this->inertia()->handleExceptionsUsing(function (ExceptionResponse $response) {
            if (419 === $response->statusCode()) {
                return new RedirectResponse('/login');
            }

            return $response->render('Error', ['status' => $response->statusCode()]);
        });

        $response = $this->makeMockRequest(function () {
            throw new HttpException(419);
        }, ['X-Inertia' => 'true']);

        $this->assertTrue($response->isRedirect('/login'));
    }

    public function testExceptionsCanFallThroughToDefault(): void
    {
        $this->inertia()->handleExceptionsUsing(function (ExceptionResponse $response) {
            if (500 === $response->statusCode()) {
                return null;
            }

            return $response->render('Error', ['status' => $response->statusCode()]);
        });

        $response = $this->makeMockRequest(function () {
            throw new HttpException(500);
        }, ['X-Inertia' => 'true']);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertFalse($response->headers->has('X-Inertia'));
    }

    public function testExceptionsWithCustomRootView(): void
    {
        $this->inertia()->handleExceptionsUsing(function (ExceptionResponse $response) {
            return $response->render('Error', ['status' => $response->statusCode()])
                ->rootView('welcome.html.twig');
        });

        $response = $this->makeMockRequest(function () {
            throw new HttpException(500);
        }, ['X-Inertia' => 'true']);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertArrayContains(['component' => 'Error'], $this->getPage($response));
    }

    public function testExceptionsOutsideTheControllerAreHandled(): void
    {
        $this->inertia()->handleExceptionsUsing(function (ExceptionResponse $response) {
            return $response->render('Error', ['status' => $response->statusCode()]);
        });

        // No route matches, so the router's NotFoundHttpException is thrown before any controller runs.
        $response = $this->makeMockRequest(null, ['X-Inertia' => 'true'], uri: '/non-existent-page');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertArrayContains([
            'component' => 'Error',
            'props' => [
                'status' => 404,
            ],
        ], $this->getPage($response));
    }

    public function testTheHandlerCanReturnAnInertiaResponseDirectly(): void
    {
        $this->inertia()->handleExceptionsUsing(fn (ExceptionResponse $response) => $this->inertia()->render('Error'));

        $response = $this->makeMockRequest(function () {
            throw new HttpException(503);
        }, ['X-Inertia' => 'true']);

        $this->assertSame(503, $response->getStatusCode());
        $this->assertArrayContains(['component' => 'Error'], $this->getPage($response));
    }

    public function testExceptionsWithoutAStatusRenderAs500(): void
    {
        $this->inertia()->handleExceptionsUsing(fn (ExceptionResponse $response) => $response->render('Error', [
            'status' => $response->statusCode(),
        ]));

        $response = $this->makeMockRequest(function () {
            throw new \RuntimeException('Boom');
        }, ['X-Inertia' => 'true']);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertArrayContains(['props' => ['status' => 500]], $this->getPage($response));
    }

    public function testHttpExceptionHeadersAreKept(): void
    {
        $this->inertia()->handleExceptionsUsing(fn (ExceptionResponse $response) => $response->render('Error'));

        $response = $this->makeMockRequest(function () {
            throw new HttpException(429, headers: ['Retry-After' => '60']);
        }, ['X-Inertia' => 'true']);

        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame('60', $response->headers->get('Retry-After'));
    }

    public function testTheHandlerSurvivesAServiceReset(): void
    {
        $this->inertia()->handleExceptionsUsing(fn (ExceptionResponse $response) => $response->render('Error'));
        static::getContainer()->get('services_resetter')->reset();

        $this->assertNotNull($this->inertia()->getExceptionHandler());
    }
}
