<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\InstalacionEvidencia;
use App\Models\Notificaciones;
use App\Models\PedidoEntrega;
use App\Models\PortalJuegoAcceso;
use App\Models\Ventas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PortalGarantiaController extends Controller
{
    private const ACCIONES = [
        'ver_datos', 'copiar_usuario', 'copiar_contrasena',
        'copiar_codigo', 'copiar_todos', 'abrir_tutorial',
    ];

    public function store(Request $request, string $origen, int $id): RedirectResponse
    {
        $cliente = $request->user()->cliente()->firstOrFail();
        $juego = $this->resolverJuego($cliente, $origen, $id);
        $esPrimaria = mb_strtolower((string) $juego['tipo_cuenta']) === 'primaria';

        if ($this->evidencias($cliente, $juego)->where('estado', 'aprobada')->exists()) {
            throw ValidationException::withMessages([
                'archivo' => 'La instalación de este juego ya fue aprobada y su garantía está activa.',
            ]);
        }

        if ($this->evidencias($cliente, $juego)->where('estado', 'en_revision')->exists()) {
            throw ValidationException::withMessages([
                'archivo' => 'Ya existe una evidencia pendiente de revisión para este juego.',
            ]);
        }

        $reglasArchivo = $esPrimaria
            ? ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240']
            : ['required', 'file', 'mimes:mp4,mov,webm', 'max:51200'];

        $datos = $request->validate([
            'archivo' => $reglasArchivo,
            'declaracion' => ['accepted'],
        ], [
            'archivo.required' => 'Debes adjuntar la evidencia de la instalación.',
            'archivo.mimes' => $esPrimaria
                ? 'Para una cuenta primaria debes enviar una foto JPG, PNG o WEBP.'
                : 'Para una cuenta secundaria debes enviar un video MP4, MOV o WEBM.',
            'archivo.max' => $esPrimaria
                ? 'La foto no puede superar los 10 MB.'
                : 'El video no puede superar los 50 MB.',
            'declaracion.accepted' => 'Debes confirmar que no modificaste los datos ni la seguridad de la cuenta.',
        ]);

        $archivo = $datos['archivo'];
        $path = $archivo->store("garantias/{$cliente->id}", 'local');
        $nombreOriginal = preg_replace('/[\r\n"]+/', '', basename($archivo->getClientOriginalName()));

        try {
            InstalacionEvidencia::create([
                'cliente_id' => $cliente->id,
                'pedido_entrega_id' => $juego['pedido_entrega_id'],
                'venta_id' => $juego['venta_id'],
                'tipo_archivo' => $esPrimaria ? 'foto' : 'video',
                'archivo_path' => $path,
                'nombre_original' => mb_substr($nombreOriginal ?: 'evidencia', 0, 255),
                'mime_type' => $archivo->getMimeType() ?: 'application/octet-stream',
                'tamano_bytes' => $archivo->getSize(),
                'estado' => 'en_revision',
                'declaracion_aceptada' => true,
                'enviado_at' => now(),
            ]);
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }

        try {
            Notificaciones::create([
                'tipo' => 'evidencia_instalacion',
                'juego' => $juego['juego'],
                'mensaje' => "Nueva evidencia de instalación de {$cliente->nombre} para {$juego['juego']} ({$juego['tipo_cuenta']} {$juego['consola']}).",
            ]);
        } catch (\Throwable) {
            // La evidencia no debe perderse si el módulo de notificaciones no está disponible.
        }

        return back()->with('success', 'Evidencia enviada correctamente. La instalación quedó pendiente de revisión.');
    }

    public function acceso(Request $request, string $origen, int $id)
    {
        $datos = $request->validate([
            'accion' => ['required', Rule::in(self::ACCIONES)],
        ]);

        $cliente = $request->user()->cliente()->firstOrFail();
        $juego = $this->resolverJuego($cliente, $origen, $id);

        PortalJuegoAcceso::create([
            'cliente_id' => $cliente->id,
            'user_id' => $request->user()->id,
            'pedido_entrega_id' => $juego['pedido_entrega_id'],
            'venta_id' => $juego['venta_id'],
            'accion' => $datos['accion'],
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
        ]);

        return response()->noContent();
    }

    public function archivo(Request $request, InstalacionEvidencia $evidencia): BinaryFileResponse
    {
        $cliente = $request->user()->cliente()->firstOrFail();
        abort_unless($evidencia->cliente_id === $cliente->id, 403);
        abort_unless(Storage::disk('local')->exists($evidencia->archivo_path), 404);

        return response()->file(Storage::disk('local')->path($evidencia->archivo_path), [
            'Content-Type' => $evidencia->mime_type,
            'Content-Disposition' => 'inline; filename="evidencia-'.$evidencia->id.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    private function resolverJuego(Cliente $cliente, string $origen, int $id): array
    {
        if ($origen === 'pedido') {
            $entrega = PedidoEntrega::query()
                ->whereKey($id)
                ->where('estado', '!=', 'anulada')
                ->whereHas('detalle.pedido', fn ($query) => $query->where('cliente_id', $cliente->id))
                ->with(['detalle.pedido:id,codigo,cliente_id', 'correoJuego:id,juego'])
                ->firstOrFail();

            return [
                'pedido_entrega_id' => $entrega->id,
                'venta_id' => null,
                'juego' => $entrega->detalle?->juego ?: $entrega->correoJuego?->juego ?: 'Juego digital',
                'tipo_cuenta' => $entrega->detalle?->tipo_cuenta ?: 'Primaria',
                'consola' => $entrega->detalle?->consola ?: 'PlayStation',
            ];
        }

        if ($origen === 'historica') {
            $venta = Ventas::query()
                ->whereKey($id)
                ->where('cliente_id', $cliente->id)
                ->whereNull('pedido_detalle_id')
                ->where(fn ($query) => $query->whereNull('estado')->orWhere('estado', 'activa'))
                ->with('correoJuego:id,juego')
                ->firstOrFail();

            return [
                'pedido_entrega_id' => null,
                'venta_id' => $venta->id,
                'juego' => $venta->correoJuego?->juego ?: 'Juego digital',
                'tipo_cuenta' => $venta->tipo_cuenta,
                'consola' => $venta->consola,
            ];
        }

        abort(404);
    }

    private function evidencias(Cliente $cliente, array $juego)
    {
        return InstalacionEvidencia::query()
            ->where('cliente_id', $cliente->id)
            ->when($juego['pedido_entrega_id'], fn ($query, $id) => $query->where('pedido_entrega_id', $id))
            ->when($juego['venta_id'], fn ($query, $id) => $query->where('venta_id', $id));
    }
}
