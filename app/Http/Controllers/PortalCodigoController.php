<?php

namespace App\Http\Controllers;

use App\Services\PortalCodigoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PortalCodigoController extends Controller
{
    public function store(Request $request, string $origen, int $id, PortalCodigoService $codigos): RedirectResponse
    {
        $cliente = $request->user()->cliente()->firstOrFail();

        $codigos->solicitar(
            $cliente->id,
            $request->user()->id,
            $origen,
            $id,
            $request->ip(),
            $request->userAgent()
        );

        return back()->with('success', 'Mira el video y usa este código para quitar el candado. Es de un solo uso.');
    }
}
