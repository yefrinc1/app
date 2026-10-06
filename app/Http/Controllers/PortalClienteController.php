<?php

namespace App\Http\Controllers;

use App\Models\Juego;
use App\Models\PedidoEntrega;
use App\Models\Ventas;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PortalClienteController extends Controller
{
    public function index(Request $request): Response
    {
        $cliente = $request->user()->cliente()->firstOrFail();

        $entregas = PedidoEntrega::query()
            ->where('estado', '!=', 'anulada')
            ->whereHas('detalle.pedido', fn ($query) => $query->where('cliente_id', $cliente->id))
            ->with(['detalle:id,pedido_id,juego,tipo_cuenta,consola', 'detalle.pedido:id,codigo,cliente_id', 'correoJuego:id,juego'])
            ->latest('asignado_at')
            ->get();

        $ventas = Ventas::query()
            ->where('cliente_id', $cliente->id)
            ->whereNull('pedido_detalle_id')
            ->where(function ($query) {
                $query->whereNull('estado')->orWhere('estado', 'activa');
            })
            ->with('correoJuego:id,juego')
            ->latest()
            ->get();

        $pedidos = $cliente->pedidos()
            ->with('detalles:id,pedido_id,juego,tipo_cuenta,consola,cantidad,estado')
            ->select(['id', 'codigo', 'total', 'moneda', 'estado', 'estado_financiero', 'estado_entrega', 'created_at'])
            ->latest()
            ->limit(20)
            ->get();

        $juegos = $this->resumenJuegos($entregas, $ventas);

        return Inertia::render('PortalCliente/Index', [
            'cliente' => ['nombre' => $cliente->nombre, 'email' => $cliente->email],
            'juegos' => $juegos,
            'pedidos' => $pedidos,
            'resumen' => [
                'total' => $juegos->count(),
                'pedidos' => $cliente->pedidos()->count(),
                'historicos' => $ventas->count(),
            ],
        ]);
    }

    public function show(Request $request, string $origen, int $id): Response
    {
        $cliente = $request->user()->cliente()->firstOrFail();

        $juego = match ($origen) {
            'pedido' => $this->detallePedido($cliente->id, $id),
            'historica' => $this->detalleHistorico($cliente->id, $id),
            default => abort(404),
        };

        return Inertia::render('PortalCliente/Show', [
            'juego' => $juego,
            'recomendaciones' => [
                'No modifiques el correo, contraseña ni datos de la cuenta para conservar la garantía.',
                'No inicies sesión como invitado. Agrega la cuenta como un usuario normal en la consola.',
                'Utiliza la cuenta únicamente en la consola donde vas a instalar el juego; no la uses en PC o celular.',
            ],
        ]);
    }

    private function resumenJuegos(Collection $entregas, Collection $ventas): Collection
    {
        $nombres = $entregas->pluck('detalle.juego')->merge($ventas->pluck('correoJuego.juego'))->filter()->unique();
        $imagenes = $this->imagenes($nombres);

        $actuales = $entregas->map(fn ($entrega) => [
            'id' => $entrega->id,
            'origen' => 'pedido',
            'juego' => $entrega->detalle?->juego ?: $entrega->correoJuego?->juego,
            'imagen' => $this->imagenDe($imagenes, $entrega->detalle?->juego ?: $entrega->correoJuego?->juego),
            'tipo_cuenta' => $entrega->detalle?->tipo_cuenta,
            'consola' => $entrega->detalle?->consola,
            'pedido' => $entrega->detalle?->pedido?->codigo,
            'fecha' => optional($entrega->entregado_at ?: $entrega->asignado_at)->toIso8601String(),
            'estado' => $entrega->estado,
        ]);

        $historicos = $ventas->map(fn ($venta) => [
            'id' => $venta->id,
            'origen' => 'historica',
            'juego' => $venta->correoJuego?->juego ?: 'Juego digital',
            'imagen' => $this->imagenDe($imagenes, $venta->correoJuego?->juego),
            'tipo_cuenta' => $venta->tipo_cuenta,
            'consola' => $venta->consola,
            'pedido' => null,
            'fecha' => $venta->created_at?->toIso8601String(),
            'estado' => 'entregada',
        ]);

        return $actuales->concat($historicos)->sortByDesc('fecha')->values();
    }

    private function detallePedido(int $clienteId, int $id): array
    {
        $entrega = PedidoEntrega::query()
            ->whereKey($id)->where('estado', '!=', 'anulada')
            ->whereHas('detalle.pedido', fn ($query) => $query->where('cliente_id', $clienteId))
            ->with(['detalle.pedido:id,codigo,cliente_id', 'correoJuego:id,juego,correo,contrasena', 'codigoVerificacion:id,codigo'])
            ->firstOrFail();

        $nombre = $entrega->detalle?->juego ?: $entrega->correoJuego?->juego;

        return [
            'id' => $entrega->id, 'origen' => 'pedido', 'juego' => $nombre,
            'imagen' => $this->imagenUnica($nombre), 'tipo_cuenta' => $entrega->detalle?->tipo_cuenta,
            'consola' => $entrega->detalle?->consola, 'pedido' => $entrega->detalle?->pedido?->codigo,
            'usuario' => $entrega->correoJuego?->correo, 'contrasena' => $entrega->correoJuego?->contrasena,
            'codigo' => $entrega->codigoVerificacion?->codigo,
            'codigo_disponible' => (bool) $entrega->codigoVerificacion?->codigo,
            'tutorial' => $this->tutorial($entrega->detalle?->tipo_cuenta, $entrega->detalle?->consola),
        ];
    }

    private function detalleHistorico(int $clienteId, int $id): array
    {
        $venta = Ventas::query()->whereKey($id)->where('cliente_id', $clienteId)->whereNull('pedido_detalle_id')
            ->where(function ($query) { $query->whereNull('estado')->orWhere('estado', 'activa'); })
            ->with('correoJuego:id,juego,correo,contrasena')->firstOrFail();
        $nombre = $venta->correoJuego?->juego ?: 'Juego digital';

        return [
            'id' => $venta->id, 'origen' => 'historica', 'juego' => $nombre,
            'imagen' => $this->imagenUnica($nombre), 'tipo_cuenta' => $venta->tipo_cuenta,
            'consola' => $venta->consola, 'pedido' => null,
            'usuario' => $venta->correoJuego?->correo, 'contrasena' => $venta->correoJuego?->contrasena,
            'codigo' => null, 'codigo_disponible' => false,
            'tutorial' => $this->tutorial($venta->tipo_cuenta, $venta->consola),
        ];
    }

    private function imagenes(Collection $nombres): Collection
    {
        return Juego::query()->whereIn('nombre', $nombres)->get(['nombre', 'url_imagen'])
            ->mapWithKeys(fn ($juego) => [Str::lower(trim($juego->nombre)) => $juego->url_imagen]);
    }

    private function imagenDe(Collection $imagenes, ?string $nombre): ?string
    {
        return $nombre ? $imagenes->get(Str::lower(trim($nombre))) : null;
    }

    private function imagenUnica(?string $nombre): ?string
    {
        return $nombre ? Juego::query()->whereRaw('LOWER(nombre) = ?', [Str::lower(trim($nombre))])->value('url_imagen') : null;
    }

    private function tutorial(?string $tipo, ?string $consola): ?string
    {
        $clave = Str::lower((string) $tipo).'_'.Str::lower((string) $consola);
        return config("portal_clientes.tutoriales.{$clave}");
    }
}
