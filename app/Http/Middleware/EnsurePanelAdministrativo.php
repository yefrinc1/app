<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePanelAdministrativo
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->hasRole('cliente')) {
            return redirect()->route('portal.index');
        }

        return $next($request);
    }
}
