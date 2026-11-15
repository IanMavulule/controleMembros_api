<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    use ApiResponse;

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('Sessão expirada. Faça login novamente.', 401);
        }

        if (! $user->hasAnyRole($roles)) {
            return $this->forbidden('Não tem permissão para aceder a este recurso.');
        }

        return $next($request);
    }
}
