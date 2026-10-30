<?php

namespace App\Http\Controllers;

use App\Models\Juego;
use App\Models\InstalacionEvidencia;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\PedidoEntrega;
use App\Models\PortalJuegoAcceso;
use App\Models\Ventas;
use App\Services\PortalCodigoService;
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

        $juegos = $this->resumenJuegos($entregas, $ventas);
        $oferta = $this->ofertaPortal($cliente->id, $juegos);

        return Inertia::render('PortalCliente/Index', [
            'cliente' => ['nombre' => $cliente->nombre, 'email' => $cliente->email],
            'juegos' => $juegos,
            'resumen' => [
                'total' => $juegos->count(),
                'pedidos' => $cliente->pedidos()->count(),
                'historicos' => $ventas->count(),
                'pendientes_garantia' => $juegos
                    ->reject(fn (array $juego) => $juego['saltar_evidencia'])
                    ->whereIn('garantia_estado', ['pendiente', 'requiere_correccion'])
                    ->count(),
            ],
            'oferta' => $oferta,
            'mostrar_oferta' => (bool) $oferta && $request->session()->pull('portal_mostrar_oferta', false),
        ]);
    }

    public function pedidos(Request $request): Response
    {
        $cliente = $request->user()->cliente()->firstOrFail();

        $pedidos = $cliente->pedidos()
            ->with(['detalles:id,pedido_id,juego,tipo_cuenta,consola,cantidad,cantidad_generada', 'detalles.juegoCatalogo'])
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $imagenes = $this->imagenes(
            $pedidos->getCollection()->flatMap(fn (Pedido $pedido) => $pedido->detalles->pluck('juego'))->filter()->unique()->values()
        );

        $pedidos->through(fn (Pedido $pedido) => $this->mapaPedido($pedido, false, $imagenes));

        return Inertia::render('PortalCliente/Pedidos', [
            'pedidos' => $pedidos,
        ]);
    }

    public function pedido(Request $request, int $pedido)
    {
        $cliente = $request->user()->cliente()->firstOrFail();

        $pedido = $cliente->pedidos()
            ->with([
                'detalles.juegoCatalogo',
                'detalles.entregas' => fn ($query) => $query->where('estado', '!=', 'anulada')->orderBy('id'),
            ])
            ->findOrFail($pedido);

        $mapa = $this->mapaPedido($pedido, true, $this->imagenes($pedido->detalles->pluck('juego')->filter()->unique()->values()));

        if (! $mapa['puede_ver_detalle']) {
            return redirect()->route('portal.pedidos.index');
        }

        return Inertia::render('PortalCliente/PedidoShow', [
            'pedido' => $mapa,
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

        $garantia = $this->garantia($cliente->id, $origen, $id, $juego);
        $verificacion = app(PortalCodigoService::class)->estado(
            $cliente->id,
            $origen,
            $id,
            $garantia['estado'],
            $garantia['saltar_evidencia'],
        );
        $juego['verificacion'] = $verificacion;
        $juego['codigo'] = $verificacion['visible'];
        $juego['codigo_disponible'] = (bool) $verificacion['visible'];

        return Inertia::render('PortalCliente/Show', [
            'juego' => $juego,
            'garantia' => $garantia,
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

        $actuales = $entregas->map(function ($entrega) use ($imagenes) {
            $estado = $this->estadoGarantia($entrega->ultimaEvidenciaInstalacion?->estado);
            $fecha = $entrega->entregado_at ?: $entrega->asignado_at;

            return [
                'id' => $entrega->id,
                'origen' => 'pedido',
                'juego' => $entrega->detalle?->juego ?: $entrega->correoJuego?->juego,
                'imagen' => $this->imagenDe($imagenes, $entrega->detalle?->juego ?: $entrega->correoJuego?->juego),
                'tipo_cuenta' => $entrega->detalle?->tipo_cuenta,
                'consola' => $entrega->detalle?->consola,
                'pedido' => $entrega->detalle?->pedido?->codigo,
                'fecha' => optional($fecha)->toIso8601String(),
                'estado' => $entrega->estado,
                'garantia_estado' => $estado,
                'saltar_evidencia' => false,
            ];
        });

        $historicos = $ventas->map(function ($venta) use ($imagenes) {
            $estado = $this->estadoGarantia($venta->ultimaEvidenciaInstalacion?->estado);
            $saltar = $this->debeSaltarEvidencia('historica', $estado);

            return [
                'id' => $venta->id,
                'origen' => 'historica',
                'juego' => $venta->correoJuego?->juego ?: 'Juego digital',
                'imagen' => $this->imagenDe($imagenes, $venta->correoJuego?->juego),
                'tipo_cuenta' => $venta->tipo_cuenta,
                'consola' => $venta->consola,
                'pedido' => null,
                'fecha' => $venta->created_at?->toIso8601String(),
                'estado' => 'entregada',
                'garantia_estado' => $saltar ? 'antigua' : $estado,
                'saltar_evidencia' => $saltar,
            ];
        });

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
            'tutorial_candado' => $this->tutorialCandado($entrega->detalle?->tipo_cuenta, $entrega->detalle?->consola),
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
            'tutorial_candado' => $this->tutorialCandado($venta->tipo_cuenta, $venta->consola),
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

    private function ofertaPortal(int $clienteId, Collection $juegos): ?array
    {
        if (! config('portal_clientes.oferta.activa')) {
            return null;
        }

        $consolas = $this->consolasCliente($clienteId, $juegos);
        $clave = $this->claveOferta($clienteId, $juegos, $consolas);
        $campana = config("portal_clientes.oferta.{$clave}");
        $items = collect($campana['juegos'] ?? [])->filter(fn ($item) => filled($item['nombre'] ?? null));

        if ($consolas !== []) {
            $items = $items->filter(function (array $item) use ($consolas) {
                $consolasItem = $this->extraerConsolas($item['consola'] ?? null);

                return $consolasItem === [] || count(array_intersect($consolasItem, $consolas)) > 0;
            });
        }

        if ($clave === 'varios') {
            $sinComprados = $items->reject(fn (array $item) => $this->clienteTieneJuego($clienteId, $juegos, $item['nombre']));
            $items = ($sinComprados->isNotEmpty() ? $sinComprados : $items)->take(6);
        }

        if ($items->isEmpty()) {
            return null;
        }

        $imagenes = $this->imagenes($items->pluck('nombre')->unique()->values());
        $subtitulo = $clave !== 'varios' && count($consolas) === 1
            ? 'Precios para '.$consolas[0].'.'
            : ($campana['subtitulo'] ?? null);

        return [
            'clave' => $clave,
            'presentacion' => $campana['presentacion'] ?? 'cartas',
            'titulo' => $campana['titulo'] ?? 'Oferta',
            'subtitulo' => $subtitulo,
            'whatsapp' => preg_replace('/\D/', '', (string) config('portal_clientes.oferta.whatsapp')),
            'url_tienda' => config('portal_clientes.oferta.url_tienda'),
            'juegos' => $items->map(function (array $item) use ($imagenes) {
                $precio = (int) ($item['precio'] ?? 0);
                $comparacion = (int) ($item['precio_comparacion'] ?? 0);

                return [
                    'nombre' => $item['nombre'],
                    'imagen' => $this->imagenDe($imagenes, $item['nombre']),
                    'tipo' => $item['tipo'] ?? 'Primaria',
                    'consola' => $item['consola'] ?? null,
                    'precio' => $precio,
                    'precio_comparacion' => $comparacion,
                    'descuento' => $comparacion > $precio && $comparacion > 0
                        ? (int) round((1 - ($precio / $comparacion)) * 100)
                        : 0,
                    'url' => $item['url'] ?? null,
                ];
            })->values(),
        ];
    }

    private function consolasCliente(int $clienteId, Collection $juegos): array
    {
        $consolas = collect();

        foreach ($juegos as $juego) {
            $consolas = $consolas->merge($this->extraerConsolas($juego['consola'] ?? null));
        }

        PedidoDetalle::query()
            ->whereHas('pedido', fn ($query) => $query->where('cliente_id', $clienteId)->whereNotIn('estado', ['cancelado', 'reembolsado']))
            ->pluck('consola')
            ->each(function (?string $consola) use (&$consolas) {
                $consolas = $consolas->merge($this->extraerConsolas($consola));
            });

        return $consolas->unique()->values()->all();
    }

    private function extraerConsolas(?string $consola): array
    {
        $texto = Str::upper((string) $consola);
        $encontradas = [];

        if (str_contains($texto, 'PS5')) {
            $encontradas[] = 'PS5';
        }

        if (str_contains($texto, 'PS4')) {
            $encontradas[] = 'PS4';
        }

        return $encontradas;
    }

    private function claveOferta(int $clienteId, Collection $juegos, array $consolas): string
    {
        if (in_array('PS5', $consolas, true) && ! $this->comproGtaVi($clienteId, $juegos)) {
            return 'gta';
        }

        return $this->comproFc27($clienteId, $juegos) ? 'varios' : 'fc27';
    }

    private function comproGtaVi(int $clienteId, Collection $juegos): bool
    {
        if ($juegos->contains(fn (array $juego) => $this->esGtaVi($juego['juego'] ?? null))) {
            return true;
        }

        return PedidoDetalle::query()
            ->whereHas('pedido', fn ($query) => $query->where('cliente_id', $clienteId)->whereNotIn('estado', ['cancelado', 'reembolsado']))
            ->pluck('juego')
            ->contains(fn (?string $nombre) => $this->esGtaVi($nombre));
    }

    private function esGtaVi(?string $nombre): bool
    {
        $nombre = Str::lower(trim((string) $nombre));
        if ($nombre === '') {
            return false;
        }

        return str_contains($nombre, 'grand theft auto vi')
            || str_contains($nombre, 'gta vi')
            || (bool) preg_match('/\bgta\s*6\b/', $nombre);
    }

    private function comproFc27(int $clienteId, Collection $juegos): bool
    {
        if ($juegos->contains(fn (array $juego) => $this->esFc27($juego['juego'] ?? null))) {
            return true;
        }

        return PedidoDetalle::query()
            ->whereHas('pedido', fn ($query) => $query->where('cliente_id', $clienteId)->whereNotIn('estado', ['cancelado', 'reembolsado']))
            ->pluck('juego')
            ->contains(fn (?string $nombre) => $this->esFc27($nombre));
    }

    private function esFc27(?string $nombre): bool
    {
        $nombre = Str::lower(trim((string) $nombre));
        if ($nombre === '') {
            return false;
        }

        return str_contains($nombre, 'ea sports fc 27')
            || str_contains($nombre, 'fc 27')
            || str_contains($nombre, 'fc27')
            || (bool) preg_match('/\bfc\s*27\b/', $nombre);
    }

    private function clienteTieneJuego(int $clienteId, Collection $juegos, string $nombre): bool
    {
        if ($juegos->contains(fn (array $juego) => $this->nombresJuegoCoinciden($juego['juego'] ?? null, $nombre))) {
            return true;
        }

        return PedidoDetalle::query()
            ->whereHas('pedido', fn ($query) => $query->where('cliente_id', $clienteId)->whereNotIn('estado', ['cancelado', 'reembolsado']))
            ->pluck('juego')
            ->contains(fn (?string $juego) => $this->nombresJuegoCoinciden($juego, $nombre));
    }

    private function nombresJuegoCoinciden(?string $a, ?string $b): bool
    {
        $a = $this->normalizarNombreJuego($a);
        $b = $this->normalizarNombreJuego($b);

        if ($a === '' || $b === '') {
            return false;
        }

        return $a === $b || str_contains($a, $b) || str_contains($b, $a);
    }

    private function normalizarNombreJuego(?string $nombre): string
    {
        $nombre = Str::lower(trim((string) $nombre));
        $nombre = str_replace(['’', '`'], "'", $nombre);

        return preg_replace('/\s+/', ' ', $nombre) ?: '';
    }

    private function tutorial(?string $tipo, ?string $consola): ?string
    {
        $clave = Str::lower((string) $tipo).'_'.Str::lower((string) $consola);
        return config("portal_clientes.tutoriales.{$clave}") ?: null;
    }

    private function tutorialCandado(?string $tipo, ?string $consola): ?string
    {
        $clave = Str::lower((string) $tipo).'_'.Str::lower((string) $consola);

        return config("portal_clientes.tutoriales_candado.{$clave}") ?: $this->tutorial($tipo, $consola);
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
        $saltarEvidencia = $this->debeSaltarEvidencia($origen, $estado);

        return [
            'estado' => $saltarEvidencia ? 'antigua' : $estado,
            'saltar_evidencia' => $saltarEvidencia,
            'puede_enviar' => ! $saltarEvidencia && ! in_array($estado, ['en_revision', 'activa'], true),
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

    private function debeSaltarEvidencia(string $origen, string $estadoGarantia): bool
    {
        return $origen === 'historica' && ! in_array($estadoGarantia, ['activa', 'en_revision', 'requiere_correccion'], true);
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
            'Sigue el tutorial y agrega el usuario como nuevo, nunca como invitado.',
            $esPs5
                ? 'En el minuto 1:20 desactiva Uso compartido de consola y juego offline.'
                : 'En el minuto 0:40 desactiva la cuenta como PS4 principal.',
            'Graba un video propio, continuo y sin cortes: pantalla antes, desactivación y estado final. No subas una foto ni el video del tutorial.',
        ];
    }

    private function mapaPedido(Pedido $pedido, bool $conCuentas = false, ?Collection $imagenes = null): array
    {
        $estado = $this->estadoPedidoCliente($pedido);
        $reembolsadoTotal = in_array($estado['clave'], ['reembolsado'], true);
        $reembolsoParcial = $estado['clave'] === 'reembolso_parcial';
        $imagenes ??= $this->imagenes($pedido->detalles->pluck('juego')->filter()->unique()->values());

        return [
            'id' => $pedido->id,
            'codigo' => $pedido->codigo,
            'fecha' => $pedido->created_at?->toIso8601String(),
            'total' => $this->formatoDinero($pedido),
            'estado' => $estado,
            'entrega' => $this->estadoEntregaCliente($pedido),
            'en_proceso' => in_array($estado['clave'], ['proceso', 'pago', 'parcial'], true),
            'puede_ver_detalle' => $reembolsadoTotal || $reembolsoParcial || $this->pedidoTieneJuegoGenerado($pedido),
            'reembolsado' => $reembolsadoTotal,
            'reembolso_parcial' => $reembolsoParcial,
            'mensaje_reembolso' => $reembolsadoTotal
                ? 'Este pedido fue reembolsado. Las cuentas de estos juegos ya no están disponibles para instalar.'
                : ($reembolsoParcial
                    ? 'Este pedido tiene un reembolso parcial. Algunos juegos de la compra ya no están disponibles.'
                    : null),
            'juegos' => $pedido->detalles->map(fn (PedidoDetalle $detalle) => $this->mapaDetallePedido($detalle, $conCuentas && ! $reembolsadoTotal, $imagenes))->values(),
        ];
    }

    private function mapaDetallePedido(PedidoDetalle $detalle, bool $conCuentas, Collection $imagenes): array
    {
        $entregas = $conCuentas
            ? $detalle->entregas->where('estado', '!=', 'anulada')->values()
            : collect();

        return [
            'id' => $detalle->id,
            'juego' => $detalle->juego,
            'imagen' => $detalle->juegoCatalogo?->url_imagen ?: $this->imagenDe($imagenes, $detalle->juego),
            'tipo_cuenta' => $detalle->tipo_cuenta,
            'consola' => $detalle->consola,
            'cantidad' => $detalle->cantidad,
            'cuentas' => $entregas->map(fn (PedidoEntrega $entrega) => [
                'id' => $entrega->id,
                'estado' => $entrega->estado === 'entregada' ? 'Entregada' : 'Lista para instalar',
                'url' => route('portal.juegos.show', ['origen' => 'pedido', 'id' => $entrega->id]),
            ])->values(),
            'pendientes' => $conCuentas ? max(0, (int) $detalle->cantidad - $entregas->count()) : 0,
        ];
    }

    private function pedidoTieneJuegoGenerado(Pedido $pedido): bool
    {
        if (in_array($pedido->estado_entrega, ['parcial', 'completado'], true)) {
            return true;
        }

        return $pedido->detalles->contains(fn (PedidoDetalle $detalle) => (int) $detalle->cantidad_generada > 0);
    }

    private function estadoPedidoCliente(Pedido $pedido): array
    {
        if ($pedido->estado === 'cancelado') {
            return ['texto' => 'Cancelado', 'clave' => 'cancelado'];
        }

        if ($pedido->estado === 'reembolsado' || $pedido->estado_financiero === 'reembolsado') {
            return ['texto' => 'Reembolsado', 'clave' => 'reembolsado'];
        }

        if ($pedido->estado_financiero === 'reembolso_parcial') {
            return ['texto' => 'Reembolso parcial', 'clave' => 'reembolso_parcial'];
        }

        if ($pedido->estado === 'completado' || $pedido->estado_entrega === 'completado') {
            return ['texto' => 'Completado', 'clave' => 'completado'];
        }

        if ($pedido->estado_entrega === 'parcial') {
            return ['texto' => 'Entrega parcial', 'clave' => 'parcial'];
        }

        if (in_array($pedido->estado_financiero, ['pendiente', 'pago_parcial'], true)) {
            return ['texto' => 'En proceso de pago', 'clave' => 'pago'];
        }

        return ['texto' => 'En proceso', 'clave' => 'proceso'];
    }

    private function estadoEntregaCliente(Pedido $pedido): array
    {
        return match ($pedido->estado_entrega) {
            'completado' => ['texto' => 'Entregado', 'clave' => 'completado'],
            'parcial' => ['texto' => 'Entrega parcial', 'clave' => 'parcial'],
            default => ['texto' => 'Pendiente de entrega', 'clave' => 'pendiente'],
        };
    }

    private function formatoDinero(Pedido $pedido): string
    {
        $total = (float) $pedido->total;

        if ($pedido->moneda === 'COP') {
            return '$'.number_format($total, 0, ',', '.');
        }

        return number_format($total, 2, ',', '.').' '.$pedido->moneda;
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
