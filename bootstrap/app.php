<?php

use App\Http\Middleware\EnsurePasswordIsChanged;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [EnsurePasswordIsChanged::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request): ?Response {
            if ($request->routeIs('fit-proper.link*') && $exception->getStatusCode() === 404 && ! $request->expectsJson()) {
                return response()->view('fit-proper.link-error', ['message' => $exception->getMessage() ?: 'Halaman penilaian tidak ditemukan.'], 404, ['Cache-Control' => 'private, no-store']);
            }

            return null;
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
