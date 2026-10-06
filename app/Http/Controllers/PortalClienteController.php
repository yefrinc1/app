<?php

namespace App\Http\Controllers;

use App\Models\Juego;
use App\Models\InstalacionEvidencia;
use App\Models\PedidoEntrega;
use App\Models\PortalJuegoAcceso;
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
            ->with(['detalle:id,pedido_id,juego,tipo_cuenta,consola', 'detalle.pedido:id,codigo,cliente_id', 'correoJuego:id,juego', 'ultimaEvidenciaInstalacion'])
            ->latest('asignado_at')
            ->get();

        $ventas = Ventas::query()
            ->where('cliente_id', $cliente->id)
            ->whereNull('pedido_detalle_id')
            ->where(function ($query) {
                $query->whereNull('estado')->orWhere('estado', 'activa');
            })
            ->with(['correoJuego:id,juego', 'ultimaEvidenciaInstalacion'])
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

        $this->registrarAcceso($request, $cliente->id, $origen, $id, 'ver_datos');

        return Inertia::render('PortalCliente/Show', [
            'juego' => $juego,
            'garantia' => $this->garantia($cliente->id, $origen, $id, $juego),
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
            'garantia_estado' => $this->estadoGarantia($entrega->ultimaEvidenciaInstalacion?->estado),
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
            'garantia_estado' => $this->estadoGarantia($venta->ultimaEvidenciaInstalacion?->estado),
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

    private function garantia(int $clienteId, string $origen, int $id, array $juego): array
    {
        $evidencias = InstalacionEvidencia::query()
            ->where('cliente_id', $clienteId)
            ->when($origen === 'pedido', fn ($query) => $query->where('pedido_entrega_id', $id))
            ->when($origen === 'historica', fn ($query) => $query->where('venta_id', $id))
            ->latest('enviado_at')
            ->get();

        $ultima = $evidencias->first();
        $esPrimaria = Str::lower((string) $juego['tipo_cuenta']) === 'primaria';
        $estado = $this->estadoGarantia($ultima?->estado);

        return [
            'estado' => $estado,
            'puede_enviar' => ! in_array($estado, ['en_revision', 'activa'], true),
            'tipo_requerido' => $esPrimaria ? 'foto' : 'video',
            'accept' => $esPrimaria ? 'image/jpeg,image/png,image/webp' : 'video/mp4,video/quicktime,video/webm',
            'max_mb' => $esPrimaria ? 10 : 50,
            'instrucciones' => $this->instruccionesEvidencia($juego['tipo_cuenta'], $juego['consola']),
            'evidencias' => $evidencias->map(fn (InstalacionEvidencia $evidencia) => [
                'id' => $evidencia->id,
                'estado' => $evidencia->estado,
                'tipo_archivo' => $evidencia->tipo_archivo,
                'mime_type' => $evidencia->mime_type,
                'enviado_at' => $evidencia->enviado_at?->toIso8601String(),
                'revisado_at' => $evidencia->revisado_at?->toIso8601String(),
                'motivo_rechazo' => $evidencia->motivo_rechazo,
                'archivo_url' => route('portal.garantias.archivo', $evidencia),
            ])->values(),
        ];
    }

    private function estadoGarantia(?string $estadoEvidencia): string
    {
        return match ($estadoEvidencia) {
            'aprobada' => 'activa',
            'en_revision' => 'en_revision',
            'rechazada' => 'requiere_correccion',
            default => 'pendiente',
        };
    }

    private function instruccionesEvidencia(?string $tipo, ?string $consola): array
    {
        $esPrimaria = Str::lower((string) $tipo) === 'primaria';
        $esPs5 = Str::upper((string) $consola) === 'PS5';

        if ($esPrimaria) {
            return [
                'Durante el inicio de sesión, agrega el usuario como nuevo. No ingreses como invitado.',
                'Sigue todos los pasos del video de instalación correspondiente a tu consola.',
                'Cierra la sesión de la cuenta entregada sin eliminar el usuario de la consola.',
                $esPs5
                    ? 'La imagen de referencia muestra la opción Cerrar sesión. Selecciónala y después toma una foto clara que permita verificar que la sesión ya quedó cerrada; no basta con fotografiar el botón antes de cerrarla.'
                    : 'Toma una foto clara de Administración de cuentas donde aparezca Iniciar sesión, como en el ejemplo: esto permite verificar que la sesión de la cuenta entregada está cerrada.',
                'Sube aquí tu propia foto de la consola al finalizar. El equipo MRJUEGOZ la revisará para validar la instalación y brindarte la garantía correspondiente.',
            ];
        }

        return [
            'Agrega el usuario como nuevo en la consola, nunca como invitado, y sigue todos los pasos del video de instalación.',
            $esPs5
                ? 'PS5: en el minuto 1:20 del tutorial se muestra cómo desactivar Uso compartido de consola y juego offline para la cuenta entregada.'
                : 'PS4: en el minuto 0:40 del tutorial se muestra cómo desactivar la cuenta entregada como PS4 principal.',
            'Graba con otro dispositivo un video continuo y sin cortes mientras realizas la desactivación. Muestra la pantalla antes, la acción de desactivar y el estado desactivado al finalizar. No subas solo una foto ni un video del tutorial.',
            'Sube aquí ese video para que MRJUEGOZ pueda revisar este paso fundamental y activar la garantía. No modifiques el correo, contraseña ni seguridad de la cuenta.',
        ];
    }

    private function registrarAcceso(Request $request, int $clienteId, string $origen, int $id, string $accion): void
    {
        PortalJuegoAcceso::create([
            'cliente_id' => $clienteId,
            'user_id' => $request->user()->id,
            'pedido_entrega_id' => $origen === 'pedido' ? $id : null,
            'venta_id' => $origen === 'historica' ? $id : null,
            'accion' => $accion,
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
        ]);
    }
}
