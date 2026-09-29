<?php

namespace App\Http\Controllers;

use App\Http\Requests\RevisarPedidoPagoRequest;
use App\Http\Requests\StorePedidoPagoRequest;
use App\Models\Pedido;
use App\Models\PedidoHistorial;
use App\Models\PedidoPago;
use App\Services\PedidoTotalesService;
use App\Services\ComprobantePagoService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PedidoPagoController extends Controller
{
    public function __construct(
        private PedidoTotalesService $totales,
        private ComprobantePagoService $comprobantes
    )
    {
        $this->middleware('can:pagos.subir')->only('store');
        $this->middleware('can:pedidos.ver')->only('comprobante');
        $this->middleware('can:pagos.revisar')->only(['pendientes', 'aprobar', 'rechazar']);
    }

    public function pendientes()
    {
        $pagos = PedidoPago::query()
            ->with('pedido.cliente')
            ->where('estado', 'pendiente')
            ->latest()
            ->paginate(20);

        return Inertia::render('Pedidos/Pagos/Pendientes', ['pagos' => $pagos]);
    }

    public function store(StorePedidoPagoRequest $request, Pedido $pedido)
    {
        if ($pedido->estado === 'cancelado' || $pedido->estado_financiero === 'reembolsado') {
            throw ValidationException::withMessages([
                'pedido' => 'No se pueden agregar pagos a un pedido cancelado o reembolsado totalmente.',
            ]);
        }

        $datos = $request->validated();
        $path = null;
        $referenciaNormalizada = null;
        $comprobanteHash = null;

        if ($request->hasFile('comprobante')) {
            $archivo = $request->file('comprobante');
            [$referenciaNormalizada, $comprobanteHash] = $this->comprobantes->validarUnico(
                $datos['referencia'] ?? null,
                $archivo
            );
            $path = $archivo->storeAs(
                "comprobantes/pedidos/{$pedido->id}",
                Str::uuid().'.'.$archivo->getClientOriginalExtension(),
                'local'
            );
        }

        try {
            $pago = $pedido->pagos()->create([
                ...$datos,
                'referencia_normalizada' => $referenciaNormalizada,
                'comprobante_path' => $path,
                'comprobante_hash' => $comprobanteHash,
                'estado' => 'pendiente',
                'registrado_por' => $request->user()->id,
            ]);
        } catch (QueryException $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }

            $this->lanzarErrorDuplicado($exception);
            throw $exception;
        }

        PedidoHistorial::create([
            'pedido_id' => $pedido->id,
            'usuario_id' => $request->user()->id,
            'evento' => 'pago_registrado',
            'descripcion' => "Se registró un pago pendiente de {$pago->valor_bruto} {$pago->moneda}.",
            'datos_nuevos' => ['pago_id' => $pago->id],
        ]);

        return back()->with('success', 'Pago registrado y enviado a revisión.');
    }

    public function aprobar(RevisarPedidoPagoRequest $request, PedidoPago $pago)
    {
        DB::transaction(function () use ($request, $pago) {
            $pago = PedidoPago::query()->lockForUpdate()->findOrFail($pago->id);
            abort_if($pago->estado !== 'pendiente', 422, 'Este pago ya fue revisado.');

            $pedido = Pedido::query()->lockForUpdate()->findOrFail($pago->pedido_id);
            if ($pedido->estado === 'cancelado' || $pedido->estado_financiero === 'reembolsado') {
                throw ValidationException::withMessages([
                    'pedido' => 'No puedes aprobar pagos en un pedido cancelado o reembolsado totalmente.',
                ]);
            }

            $pago->update([
                'estado' => 'aprobado',
                'aprobado_por' => $request->user()->id,
                'aprobado_at' => now(),
                'rechazado_por' => null,
                'rechazado_at' => null,
                'motivo_rechazo' => null,
            ]);

            PedidoHistorial::create([
                'pedido_id' => $pago->pedido_id,
                'usuario_id' => $request->user()->id,
                'evento' => 'pago_aprobado',
                'descripcion' => "Se aprobó el pago #{$pago->id} por {$pago->valor_bruto} {$pago->moneda}.",
            ]);

            $this->totales->recalcular($pedido);
        });

        return back()->with('success', 'Pago aprobado correctamente.');
    }

    public function rechazar(RevisarPedidoPagoRequest $request, PedidoPago $pago)
    {
        $request->validate(['motivo_rechazo' => ['required', 'string', 'max:1000']]);

        DB::transaction(function () use ($request, $pago) {
            $pago = PedidoPago::query()->lockForUpdate()->findOrFail($pago->id);
            abort_if($pago->estado !== 'pendiente', 422, 'Este pago ya fue revisado.');

            $pago->update([
                'estado' => 'rechazado',
                'rechazado_por' => $request->user()->id,
                'rechazado_at' => now(),
                'motivo_rechazo' => $request->string('motivo_rechazo')->toString(),
            ]);

            PedidoHistorial::create([
                'pedido_id' => $pago->pedido_id,
                'usuario_id' => $request->user()->id,
                'evento' => 'pago_rechazado',
                'descripcion' => 'Pago rechazado: '.$request->string('motivo_rechazo')->toString(),
            ]);

            $this->totales->recalcular($pago->pedido);
        });

        return back()->with('success', 'Pago rechazado.');
    }

    public function comprobante(PedidoPago $pago)
    {
        abort_unless($pago->comprobante_path && Storage::disk('local')->exists($pago->comprobante_path), 404);

        return Storage::disk('local')->response($pago->comprobante_path);
    }

    private function lanzarErrorDuplicado(QueryException $exception): void
    {
        $mensaje = $exception->getMessage();

        if (str_contains($mensaje, 'pedido_pagos_referencia_normalizada_unique')) {
            throw ValidationException::withMessages([
                'referencia' => 'Esta referencia acaba de ser registrada en otro comprobante.',
            ]);
        }

        if (str_contains($mensaje, 'pedido_pagos_comprobante_hash_unique')) {
            throw ValidationException::withMessages([
                'comprobante' => 'Este mismo comprobante acaba de ser subido anteriormente.',
            ]);
        }
    }
}
