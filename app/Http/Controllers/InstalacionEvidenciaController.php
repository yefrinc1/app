<?php

namespace App\Http\Controllers;

use App\Models\InstalacionEvidencia;
use App\Models\PortalJuegoAcceso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InstalacionEvidenciaController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:garantias.revisar');
    }

    public function index(Request $request): Response
    {
        $filtros = $request->validate([
            'estado' => ['nullable', 'in:en_revision,aprobada,rechazada'],
            'buscar' => ['nullable', 'string', 'max:100'],
        ]);

        $consulta = InstalacionEvidencia::query()
            ->with([
                'cliente:id,nombre,codigo_pais,telefono,usuario,email',
                'pedidoEntrega.detalle.pedido:id,codigo,cliente_id',
                'venta.correoJuego:id,juego',
                'revisor:id,name',
            ])
            ->when($filtros['estado'] ?? null, fn ($query, $estado) => $query->where('estado', $estado))
            ->when($filtros['buscar'] ?? null, function ($query, $buscar) {
                $query->where(function ($grupo) use ($buscar) {
                    $grupo->whereHas('cliente', function ($cliente) use ($buscar) {
                        $cliente->where('nombre', 'like', "%{$buscar}%")
                            ->orWhere('telefono', 'like', "%{$buscar}%")
                            ->orWhere('usuario', 'like', "%{$buscar}%")
                            ->orWhere('email', 'like', "%{$buscar}%");
                    })->orWhereHas('pedidoEntrega.detalle', fn ($detalle) => $detalle->where('juego', 'like', "%{$buscar}%"))
                        ->orWhereHas('venta.correoJuego', fn ($correo) => $correo->where('juego', 'like', "%{$buscar}%"));
                });
            });

        $conteos = InstalacionEvidencia::query()
            ->selectRaw('estado, COUNT(*) AS total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $evidencias = $consulta->latest('enviado_at')->paginate(15)->withQueryString()
            ->through(fn (InstalacionEvidencia $evidencia) => $this->presentar($evidencia));

        return Inertia::render('Garantias/Index', [
            'evidencias' => $evidencias,
            'filtros' => $filtros,
            'conteos' => [
                'en_revision' => (int) ($conteos['en_revision'] ?? 0),
                'aprobada' => (int) ($conteos['aprobada'] ?? 0),
                'rechazada' => (int) ($conteos['rechazada'] ?? 0),
            ],
        ]);
    }

    public function aprobar(Request $request, InstalacionEvidencia $evidencia)
    {
        DB::transaction(function () use ($request, $evidencia) {
            $evidencia = InstalacionEvidencia::query()->lockForUpdate()->findOrFail($evidencia->id);

            if ($evidencia->estado !== 'en_revision') {
                throw ValidationException::withMessages(['evidencia' => 'Esta evidencia ya fue revisada.']);
            }

            $evidencia->update([
                'estado' => 'aprobada',
                'revisado_por' => $request->user()->id,
                'revisado_at' => now(),
                'motivo_rechazo' => null,
            ]);
        });

        return back()->with('success', 'Evidencia aprobada. La garantía del juego quedó activa.');
    }

    public function rechazar(Request $request, InstalacionEvidencia $evidencia)
    {
        $datos = $request->validate([
            'motivo_rechazo' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $evidencia, $datos) {
            $evidencia = InstalacionEvidencia::query()->lockForUpdate()->findOrFail($evidencia->id);

            if ($evidencia->estado !== 'en_revision') {
                throw ValidationException::withMessages(['evidencia' => 'Esta evidencia ya fue revisada.']);
            }

            $evidencia->update([
                'estado' => 'rechazada',
                'revisado_por' => $request->user()->id,
                'revisado_at' => now(),
                'motivo_rechazo' => $datos['motivo_rechazo'],
            ]);
        });

        return back()->with('success', 'La evidencia fue rechazada y el cliente podrá enviarla nuevamente.');
    }

    public function archivo(InstalacionEvidencia $evidencia): BinaryFileResponse
    {
        abort_unless(Storage::disk('local')->exists($evidencia->archivo_path), 404);

        return response()->file(Storage::disk('local')->path($evidencia->archivo_path), [
            'Content-Type' => $evidencia->mime_type,
            'Content-Disposition' => 'inline; filename="evidencia-'.$evidencia->id.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    private function presentar(InstalacionEvidencia $evidencia): array
    {
        $entrega = $evidencia->pedidoEntrega;
        $detalle = $entrega?->detalle;
        $venta = $evidencia->venta;
        $accesos = PortalJuegoAcceso::query()
            ->where('cliente_id', $evidencia->cliente_id)
            ->when($evidencia->pedido_entrega_id, fn ($query, $id) => $query->where('pedido_entrega_id', $id))
            ->when($evidencia->venta_id, fn ($query, $id) => $query->where('venta_id', $id))
            ->latest()
            ->limit(10)
            ->get(['accion', 'ip', 'user_agent', 'created_at']);

        return [
            'id' => $evidencia->id,
            'cliente' => $evidencia->cliente,
            'juego' => $detalle?->juego ?: $venta?->correoJuego?->juego ?: 'Juego digital',
            'tipo_cuenta' => $detalle?->tipo_cuenta ?: $venta?->tipo_cuenta,
            'consola' => $detalle?->consola ?: $venta?->consola,
            'pedido' => $detalle?->pedido?->codigo,
            'origen' => $entrega ? 'Pedido' : 'Venta anterior',
            'tipo_archivo' => $evidencia->tipo_archivo,
            'mime_type' => $evidencia->mime_type,
            'tamano_bytes' => $evidencia->tamano_bytes,
            'estado' => $evidencia->estado,
            'enviado_at' => $evidencia->enviado_at?->toIso8601String(),
            'revisado_at' => $evidencia->revisado_at?->toIso8601String(),
            'revisor' => $evidencia->revisor?->name,
            'motivo_rechazo' => $evidencia->motivo_rechazo,
            'archivo_url' => route('garantias.evidencias.archivo', $evidencia),
            'accesos' => $accesos->map(fn (PortalJuegoAcceso $acceso) => [
                'accion' => $acceso->accion,
                'ip' => $acceso->ip,
                'user_agent' => $acceso->user_agent,
                'created_at' => $acceso->created_at?->toIso8601String(),
            ])->values(),
        ];
    }
}
