<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => null);

        $middleware->alias([
            'active.user' => EnsureUserIsActive::class,
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sessão expirada. Faça login novamente.',
                    'data' => null,
                    'errors' => null,
                ], 401);
            }
        });

        // Restantes erros HTTP da API (403, 404, 409, ...) no formato ApiResponse.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $e->getStatusCode();
            $message = $e->getMessage();

            $message = match (true) {
                $status === 403 && ($message === '' || $message === 'This action is unauthorized.') => 'Não tem permissão para aceder a este recurso.',
                $status === 404 => 'Recurso não encontrado.',
                $status === 429 => 'Demasiados pedidos. Aguarde um momento e tente novamente.',
                $message === '' => 'Ocorreu um erro.',
                default => $message,
            };

            return response()->json([
                'success' => false,
                'message' => $message,
                'data' => null,
                'errors' => null,
            ], $status, $e->getHeaders());
        });
    })->create();
