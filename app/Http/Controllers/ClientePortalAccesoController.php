<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Services\VincularVentasAntiguasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClientePortalAccesoController extends Controller
{
    public function __construct(private VincularVentasAntiguasService $vinculador)
    {
        $this->middleware('can:clientes.editar');
    }

    public function generar(Cliente $cliente): JsonResponse
    {
        if ($cliente->user_id) {
            throw ValidationException::withMessages([
                'cliente' => 'Este cliente ya tiene una cuenta activa en el portal.',
            ]);
        }

        $token = Str::random(64);
        $vence = now()->addHours(max(1, (int) config('portal_clientes.token_hours', 168)));

        DB::transaction(function () use ($cliente, $token, $vence) {
            $cliente->forceFill([
                'portal_token_hash' => hash('sha256', $token),
                'portal_token_expires_at' => $vence,
            ])->save();

            $this->vinculador->vincularCliente($cliente);
        });

        return response()->json([
            'url' => route('portal.activar.show', ['token' => $token]),
            'expires_at' => $vence->toIso8601String(),
            'message' => 'Enlace de activación generado correctamente.',
        ]);
    }
}
