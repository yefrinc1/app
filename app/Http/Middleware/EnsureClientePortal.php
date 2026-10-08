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

        $cliente = $user->cliente()->first();
        if (! $cliente) {
            abort(403, 'Esta cuenta no está vinculada con una ficha de cliente. Comunícate con soporte.');
        }

        if ($cliente->portal_requiere_verificacion_email && !$user->hasVerifiedEmail()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Verifica tu correo antes de consultar tus juegos.',
                    'verification_required' => true,
                ], 403);
            }
            return redirect()->route('portal.correo.notice');
        }

        return $next($request);
    }
}
