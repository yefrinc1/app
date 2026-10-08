<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\PedidoPago;
use App\Models\PedidoHistorial;
use App\Services\ComprobantePagoService;
use App\Services\PedidoTotalesService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PedidoPagoEdicionController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'can:pagos.subir', 'can:pedidos.ver']);
    }

    private function revisar(Request $request, Pedido $pedido, PedidoPago $pago): void
    {
        if (!in_array($pago->estado, ['pendiente', 'rechazado', 'aprobado'], true)) {
            throw ValidationException::withMessages(['pago' => 'Este estado de pago no admite edición.']);
        }
        if ($pago->estado === 'aprobado') {
            abort_unless($request->user()->can('pagos.revisar'), 403);
        }
        if (in_array($pedido->estado, ['completado', 'cancelado', 'reembolsado'], true)
            || $pedido->estado_financiero === 'reembolsado') {
            throw ValidationException::withMessages(['pago' => 'No se pueden editar pagos de un pedido cerrado.']);
        }
        if ($pedido->detalles()->where(function ($q) {
            $q->where('cantidad_generada', '>', 0)->orWhereHas('entregas')->orWhereHas('ventas');
        })->exists()) {
            throw ValidationException::withMessages(['pago' => 'El pedido ya tiene ventas o entregas generadas. No se puede modificar directamente un pago del que dependen esas ventas.']);
        }
        if ($pago->liquidaciones()->exists()) {
            throw ValidationException::withMessages(['pago' => 'Este pago tiene una liquidación registrada. No puede editarse directamente.']);
        }
        if ($pedido->reembolsos()->whereIn('estado', ['pendiente', 'aprobado'])->exists()) {
            throw ValidationException::withMessages(['pago' => 'El pedido tiene reembolsos pendientes o aprobados. No puede editarse su pago directamente.']);
        }
    }

    private function firma(Pedido $pedido, PedidoPago $pago): string
    {
        return hash('sha256', json_encode([$pedido->getAttributes(), $pago->getAttributes()], JSON_THROW_ON_ERROR));
    }

    public function edit(Request $request, PedidoPago $pago)
    {
        $pedido = $pago->pedido;
        $this->revisar($request, $pedido, $pago);
        return response()->json([
            'pago' => $pago->only(['id', 'metodo_pago', 'valor_bruto', 'moneda', 'fecha_pago', 'referencia', 'observaciones', 'estado']),
            'fecha_pago_formulario' => $pago->fecha_pago?->format('Y-m-d\TH:i:s'),
            'tiene_comprobante' => (bool) ($pago->comprobante_path && Storage::disk('local')->exists($pago->comprobante_path)),
            'firma' => $this->firma($pedido, $pago),
        ]);
    }

    public function update(Request $request, PedidoPago $pago, ComprobantePagoService $comprobantes, PedidoTotalesService $totales)
    {
        if ($pago->moneda === 'COP' && is_string($request->input('valor_bruto'))) {
            $request->merge(['valor_bruto' => str_replace(['.', ','], '', $request->input('valor_bruto'))]);
        }
        $datos = $request->validate([
            'metodo_pago' => ['required', 'string', 'max:50'],
            'valor_bruto' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'fecha_pago' => ['nullable', 'date'],
            'referencia' => ['nullable', 'string', 'max:150'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'comprobante' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'motivo' => ['required', 'string', 'min:5', 'max:1000'],
            'firma' => ['required', 'string', 'size:64'],
        ]);

        $pathNuevo = null;
        try {
            DB::transaction(function () use ($request, $pago, $datos, $comprobantes, $totales, &$pathNuevo) {
                $pedido = Pedido::query()->lockForUpdate()->findOrFail($pago->pedido_id);
                $pago = PedidoPago::query()->lockForUpdate()->findOrFail($pago->id);
                $this->revisar($request, $pedido, $pago);
                if (!hash_equals($this->firma($pedido, $pago), $datos['firma'])) {
                    throw ValidationException::withMessages(['pago' => 'El pago o pedido cambió. Cierra el formulario y vuelve a abrirlo antes de guardar.']);
                }
                if ($pago->moneda === 'COP' && floor((float) $datos['valor_bruto']) !== (float) $datos['valor_bruto']) {
                    throw ValidationException::withMessages(['valor_bruto' => 'El valor en COP debe ser entero.']);
                }

                $archivo = $request->file('comprobante');
                if (!$archivo && (!$pago->comprobante_path || !Storage::disk('local')->exists($pago->comprobante_path))) {
                    throw ValidationException::withMessages(['comprobante' => 'Adjunta un comprobante para guardar la corrección.']);
                }
                $referencia = $comprobantes->normalizarReferencia($datos['referencia'] ?? null);
                $hash = $archivo ? $comprobantes->hashArchivo($archivo) : $pago->comprobante_hash;

                if ($referencia && PedidoPago::where('id', '!=', $pago->id)->where('referencia_normalizada', $referencia)->exists()) {
                    throw ValidationException::withMessages(['referencia' => 'Esta referencia ya pertenece a otro pago.']);
                }
                if ($hash && PedidoPago::where('id', '!=', $pago->id)->where('comprobante_hash', $hash)->exists()) {
                    throw ValidationException::withMessages(['comprobante' => 'Este comprobante ya pertenece a otro pago.']);
                }

                $antes = $pago->only(['metodo_pago', 'valor_bruto', 'moneda', 'fecha_pago', 'referencia',
                    'referencia_normalizada', 'comprobante_path', 'comprobante_hash', 'observaciones', 'estado',
                    'aprobado_por', 'aprobado_at', 'rechazado_por', 'rechazado_at', 'motivo_rechazo']);
                if ($archivo) {
                    $pathNuevo = $archivo->storeAs(
                        'comprobantes/pedidos/'.$pedido->id,
                        Str::uuid().'.'.$archivo->extension(),
                        'local'
                    );
                    if (!$pathNuevo) {
                        throw ValidationException::withMessages(['comprobante' => 'No se pudo guardar el nuevo comprobante. Intenta nuevamente.']);
                    }
                }
                $pago->update([
                    'metodo_pago' => $datos['metodo_pago'],
                    'valor_bruto' => $datos['valor_bruto'],
                    'fecha_pago' => $datos['fecha_pago'] ?? null,
                    'referencia' => $datos['referencia'] ?? null,
                    'referencia_normalizada' => $referencia,
                    'comprobante_path' => $pathNuevo ?: $pago->comprobante_path,
                    'comprobante_hash' => $hash,
                    'observaciones' => $datos['observaciones'] ?? null,
                    'estado' => 'pendiente',
                    'aprobado_por' => null, 'aprobado_at' => null,
                    'rechazado_por' => null, 'rechazado_at' => null, 'motivo_rechazo' => null,
                ]);
                PedidoHistorial::create([
                    'pedido_id' => $pedido->id, 'usuario_id' => $request->user()->id,
                    'evento' => 'pago_corregido',
                    'descripcion' => 'Pago #'.$pago->id.' corregido y enviado nuevamente a revisión. Valor anterior: '
                        .$antes['valor_bruto'].'; nuevo: '.$pago->valor_bruto.' '.$pago->moneda.'. Motivo: '.$datos['motivo'],
                    'datos_anteriores' => $antes,
                    'datos_nuevos' => $pago->fresh()->only(array_keys($antes)),
                ]);
                $totales->recalcular($pedido);
                // El comprobante anterior se conserva privado como parte de la auditoría.
            });
        } catch (\Throwable $error) {
            if ($pathNuevo) {
                Storage::disk('local')->delete($pathNuevo);
            }
            if ($error instanceof QueryException) {
                if (str_contains($error->getMessage(), 'referencia_normalizada_unique')) {
                    throw ValidationException::withMessages(['referencia' => 'Esta referencia acaba de ser registrada en otro pago.']);
                }
                if (str_contains($error->getMessage(), 'comprobante_hash_unique')) {
                    throw ValidationException::withMessages(['comprobante' => 'Este comprobante acaba de ser registrado en otro pago.']);
                }
            }
            throw $error;
        }
        return back()->with('success', 'Pago corregido y enviado a revisión. Debe aprobarse nuevamente antes de contar como pago aprobado.');
    }
}
