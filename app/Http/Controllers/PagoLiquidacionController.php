<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePagoLiquidacionRequest;
use App\Models\PedidoHistorial;
use App\Models\PedidoPago;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PagoLiquidacionController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:pagos.revisar');
    }

    public function store(StorePagoLiquidacionRequest $request, PedidoPago $pago)
    {
        abort_if($pago->estado !== 'aprobado', 422, 'Primero debes aprobar el pago.');

        $pago->loadMissing('pedido');
        if (in_array($pago->pedido->estado, ['completado', 'cancelado', 'reembolsado'], true)) {
            throw ValidationException::withMessages([
                'pedido' => 'No puedes registrar el neto porque el pedido ya está cerrado.',
            ]);
        }

        $datos = $request->validated();
        $comision = (float) ($datos['comision'] ?? 0);
        $retencion = (float) ($datos['retencion'] ?? 0);
        $otros = (float) ($datos['otros_descuentos'] ?? 0);
        $neto = (float) $datos['valor_bruto'] - $comision - $retencion - $otros;

        abort_if($neto < 0, 422, 'Los descuentos no pueden superar el valor bruto.');
        $brutoLiquidado = (float) $pago->liquidaciones()->sum('valor_bruto');
        abort_if(
            $brutoLiquidado + (float) $datos['valor_bruto'] > (float) $pago->valor_bruto,
            422,
            'Las liquidaciones no pueden superar el valor bruto del pago.'
        );

        $path = null;
        if ($request->hasFile('comprobante')) {
            $archivo = $request->file('comprobante');
            $path = $archivo->storeAs(
                "comprobantes/pedidos/{$pago->pedido_id}/liquidaciones",
                Str::uuid().'.'.$archivo->getClientOriginalExtension(),
                'local'
            );
        }

        $liquidacion = $pago->liquidaciones()->create([
            ...$datos,
            'comision' => $comision,
            'retencion' => $retencion,
            'otros_descuentos' => $otros,
            'valor_neto' => $neto,
            'comprobante_path' => $path,
            'registrado_por' => $request->user()->id,
        ]);

        PedidoHistorial::create([
            'pedido_id' => $pago->pedido_id,
            'usuario_id' => $request->user()->id,
            'evento' => 'pago_liquidado',
            'descripcion' => "Se registró una liquidación neta de {$neto}.",
            'datos_nuevos' => ['liquidacion_id' => $liquidacion->id],
        ]);

        return back()->with('success', 'Liquidación registrada correctamente.');
    }

    public function comprobante(PedidoPago $pago, int $liquidacion)
    {
        $registro = $pago->liquidaciones()->findOrFail($liquidacion);
        abort_unless($registro->comprobante_path && Storage::disk('local')->exists($registro->comprobante_path), 404);

        return Storage::disk('local')->response($registro->comprobante_path);
    }
}
