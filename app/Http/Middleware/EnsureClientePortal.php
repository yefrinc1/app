<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientePortal
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->hasRole('cliente')) {
            return redirect()->route('dashboard');
        }

        if (! $user->cliente()->exists()) {
            abort(403, 'Esta cuenta no está vinculada con una ficha de cliente. Comunícate con soporte.');
        }

        return $next($request);
    }
}
