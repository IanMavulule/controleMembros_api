<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    use ApiResponse;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('Sessão expirada. Faça login novamente.', 401);
        }

        if (! $user->is_active) {
            $user->currentAccessToken()?->delete();

            return $this->forbidden('A sua conta está desactivada. Contacte o administrador.');
        }

        return $next($request);
    }
}
