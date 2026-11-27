<?php

namespace App\Http\Controllers;

use App\Models\CodigoVerificacion;
use App\Models\CorreoGlobales;
use App\Models\CorreoJuego;
use App\Models\CorreoMadre;
use App\Models\Juego;
use App\Models\Notificaciones;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CorreoJuegoController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:correos.juegos', ['only' => ['index', 'create', 'store', 'update', 'destroy']]);
        $this->middleware('can:correos.juegos.manual', ['only' => ['crearJuegoManual', 'storeManual']]);
    }
    
    public function index(Request $request)
    {
        // Obtén el término de búsqueda
        $search = $request->get('search', ''); // Si no hay búsqueda, se usa una cadena vacía por defecto
        $mensaje_correo_creado = $request->get('mensaje_correo_creado', '');
        $icon_mensaje = $request->get('icon_mensaje', '');
    
        // Filtra los correos si se proporciona un término de búsqueda
        $correos = CorreoJuego::with([
            'correoMadre' => fn ($query) => $query
                ->select('id', 'correo', 'saldo_usd')
                ->withCount([
                    'codigosVerificacion as codigos_disponibles_count' => fn ($codigos) => $codigos->where('disponible', 1),
                ]),
        ])->withCount([
            'codigosVerificacion as codigos_disponibles_count' => fn ($query) => $query->where('disponible', 1),
        ])
        ->when($search, function ($query, $search) {
            return $query->where('correo', 'like', "%{$search}%");
        })
        ->orderBy('disponible', 'desc') // Ordena por 'disponible'
        ->latest() // Ordena por fecha
        ->paginate(10)
        ->appends($request->query());
    
        return Inertia::render('CorreoJuego/Index', [
            'correos' => $correos,
            'search' => $search,  // Pasa el término de búsqueda a la vista
            'mensaje_correo_creado' => $mensaje_correo_creado,
            'icon_mensaje' => $icon_mensaje,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $correosMadres = CorreoMadre::where('disponible', 1)->take(30)->latest()->get();

        // Obtener los ids de correo principal que tienen correos globales disponibles
        $principalesIds = CorreoGlobales::where('disponible', 1)
            ->distinct()
            ->pluck('id_correo_principal');

        // Por cada id de principal, traer hasta 2 correos globales disponibles
        $correosGlobales = collect();
        foreach ($principalesIds as $principalId) {
            $items = CorreoGlobales::where('disponible', 1)
                ->where('id_correo_principal', $principalId)
                ->take(2)
                ->latest()
                ->get();

            $correosGlobales = $correosGlobales->concat($items);
        }

        // Opcional: reindexar la colección y limitar total si se desea
        $correosGlobales = $correosGlobales->values();

        return Inertia::render('CorreoJuego/Create', [
            'correosMadres' => $correosMadres,
            'correosGlobales' => $correosGlobales,
        ]);
    }

    public function crearJuegoManual()
    {
        return Inertia::render('CorreoJuego/CreateManual');
    }

    public function storeManual(Request $request)
    {
        $validatedData = $request->validate([
            'correo' => 'required|unique:correo_globales,correo',
            'contrasena' => 'required',
            'juego' => 'required',
            'fecha_nacimiento' => 'required',
            'primaria_ps4' => 'required|integer|min:0|max:2',
            'primaria_ps5' => 'required|integer|min:0|max:2',
            'secundaria' => 'required|integer|min:0|max:1',
        ]);
        [$primariaPs4, $primariaPs5] = $this->licenciasSegunConsola(
            $validatedData['juego'],
            (int) $validatedData['primaria_ps4'],
            (int) $validatedData['primaria_ps5'],
        );

        $correoGlobal = CorreoGlobales::create([
            'correo' => $validatedData['correo'],
        ]);

        $correoJuegoDisponible = $primariaPs4 + $primariaPs5 + $validatedData['secundaria'] >= 5;

        $correoJuego = CorreoJuego::create([
            'correo' => $correoGlobal->correo,
            'contrasena' => $validatedData['contrasena'],
            'id_correo_globales' => $correoGlobal->id,
            'fecha_nacimiento' => $validatedData['fecha_nacimiento'],
            'juego' => $validatedData['juego'],
            'primaria_ps4' => $primariaPs4,
            'primaria_ps5' => $primariaPs5,
            'secundaria' => $validatedData['secundaria'],
            'disponible' => $correoJuegoDisponible ? 0 : 1,
        ]);

        $correoGlobal->update(['disponible' => 0]);

        if (trim($request->codigo) != '') {
            $codigos = CodigoVerificacion::separarCodigos($request->codigo);

            $codigoRespaldo = 1;
            foreach ($codigos as $clave => $codigo) {
                if ($clave > 0 && $codigoRespaldo == 1) {
                    $codigoRespaldo = 0;
                }
                
                CodigoVerificacion::create([
                    'codigo' => $codigo,
                    'id_correo_juego' => $correoJuego->id,
                    'respaldo' => $codigoRespaldo,
                ]);
            }
        }

        return redirect()->back();
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'contrasena' => 'required|string|min:6',
            'id_correo_globales' => 'required|exists:correo_globales,id',
            'id_correo_madre' => 'required|exists:correo_madre,id',
            'fecha_nacimiento' => 'required',
            'juego' => 'required|string',
            'primaria_ps4' => 'required|integer|min:0|max:2',
            'primaria_ps5' => 'required|integer|min:0|max:2',
            'secundaria' => 'required|integer|min:0|max:1',
        ]);
        [$primariaPs4, $primariaPs5] = $this->licenciasSegunConsola(
            $validatedData['juego'],
            (int) $validatedData['primaria_ps4'],
            (int) $validatedData['primaria_ps5'],
        );

        $correoGlobal = CorreoGlobales::findOrFail($validatedData['id_correo_globales']);
        $correoMadre = CorreoMadre::findOrFail($validatedData['id_correo_madre']);

        if ($request->precio_usd !== null && $request->precio_usd !== '' && (float) $request->precio_usd > (float) $correoMadre->saldo_usd) {
            throw ValidationException::withMessages([
                'precio_usd' => 'La madre solo tiene '.$correoMadre->saldo_usd.' USD. No alcanza para este precio.',
            ]);
        }

        $precioCOP = null;
        if ($request->precio_usd) {
            $saldoRestanteUSD = $correoMadre->saldo_usd - $request->precio_usd;
            $precioDolar = $correoMadre->saldo_usd != 0 ? ($correoMadre->saldo_cop / $correoMadre->saldo_usd) : 0;
            $precioCOP = $request->precio_usd * $precioDolar;
            $saldoRestanteCOP = $correoMadre->saldo_cop - $precioCOP;
            $correoMadre->update(['saldo_usd' => $saldoRestanteUSD, 'saldo_cop' => $saldoRestanteCOP]);
        }

        $correoJuego = CorreoJuego::create([
            'correo' => $correoGlobal->correo,
            'contrasena' => $validatedData['contrasena'],
            'id_correo_globales' => $validatedData['id_correo_globales'],
            'id_correo_madre' => $validatedData['id_correo_madre'],
            'fecha_nacimiento' => $validatedData['fecha_nacimiento'],
            'juego' => $request->juego,
            'precio_usd' => $request->precio_usd,
            'precio_cop' => $precioCOP,
            'primaria_ps4' => $primariaPs4,
            'primaria_ps5' => $primariaPs5,
            'secundaria' => $validatedData['secundaria'],
        ]);

        $notificacionExiste = Notificaciones::where('juego', $request->juego)
            ->where('tipo', 'crear_juego')
            ->first();

        if ($notificacionExiste) {
            $notificacionExiste->delete();
        }

        $correoMadre->sincronizarDisponibilidad();
        $correoGlobal->update(['disponible' => 0]);

        if (trim($request->codigo) != '') {
            $codigos = CodigoVerificacion::separarCodigos($request->codigo);
            $codigoRespaldo = 1;
            foreach ($codigos as $clave => $codigo) {
                if ($clave > 0 && $codigoRespaldo == 1) {
                    $codigoRespaldo = 0;
                }

                CodigoVerificacion::create([
                    'codigo' => $codigo,
                    'id_correo_juego' => $correoJuego->id,
                    'respaldo' => $codigoRespaldo,
                ]);
            }
        }

        return redirect()->route('correo-juegos.index', [
            'mensaje_correo_creado' => 'Se creo correctamente el correo del juego',
            'icon_mensaje' => 'success',
        ]);
    }

    public function update(Request $request, string $id)
    {
        $validatedData = $request->validate([
            'contrasena' => 'required|string|min:6',
            'fecha_nacimiento' => 'required',
            'juego' => 'required|string',
            'primaria_ps4' => 'required|integer|min:0|max:2',
            'primaria_ps5' => 'required|integer|min:0|max:2',
            'secundaria' => 'required|integer|min:0|max:1',
        ]);
        [$primariaPs4, $primariaPs5] = $this->licenciasSegunConsola(
            $validatedData['juego'],
            (int) $validatedData['primaria_ps4'],
            (int) $validatedData['primaria_ps5'],
        );

        $correoJuego = CorreoJuego::findOrFail($id);
        $correoMadre = CorreoMadre::find($correoJuego->id_correo_madre);

        if ($correoMadre) {
            $saldoNuevoMadreUSD = (float) $correoJuego->precio_usd + (float) $correoMadre->saldo_usd;
            $saldoNuevoMadreCOP = (float) $correoJuego->precio_cop + (float) $correoMadre->saldo_cop;

            if ($request->precio_usd !== null && $request->precio_usd !== '' && $saldoNuevoMadreUSD < (float) $request->precio_usd) {
                throw ValidationException::withMessages([
                    'precio_usd' => 'La madre solo tiene '.$saldoNuevoMadreUSD.' USD disponibles. No alcanza para este precio.',
                ]);
            }

            $saldoRestanteUSD = $saldoNuevoMadreUSD - $request->precio_usd;
            $precioDolar = $saldoNuevoMadreUSD != 0 ? ($saldoNuevoMadreCOP / $saldoNuevoMadreUSD) : 0;
            $precioCOP = $request->precio_usd * $precioDolar;
            $saldoRestanteCOP = $saldoNuevoMadreCOP - $precioCOP;
            $correoMadre->update(['saldo_usd' => $saldoRestanteUSD, 'saldo_cop' => $saldoRestanteCOP]);

            $correoJuego->update([
                'contrasena' => $request->contrasena,
                'fecha_nacimiento' => $request->fecha_nacimiento,
                'juego' => $request->juego,
                'precio_usd' => $request->precio_usd,
                'precio_cop' => $precioCOP,
                'disponible' => $request->disponible ? 1 : 0,
                'primaria_ps4' => $primariaPs4,
                'primaria_ps5' => $primariaPs5,
                'secundaria' => $validatedData['secundaria'],
            ]);

            if ($correoJuego->es_cuenta_madre) {
                $correoMadre->update([
                    'contrasena' => $request->contrasena,
                    'fecha_nacimiento' => $request->fecha_nacimiento,
                ]);
            }

            $mensaje = "Se actualizo correctamente el juego";
            $icon_mensaje = 'success';
        } else {
            $correoJuego->update([
                'contrasena' => $request->contrasena,
                'fecha_nacimiento' => $request->fecha_nacimiento,
                'juego' => $request->juego,
                'disponible' => $request->disponible ? 1 : 0,
                'primaria_ps4' => $primariaPs4,
                'primaria_ps5' => $primariaPs5,
                'secundaria' => $validatedData['secundaria'],
            ]);

            $mensaje = "Se actualizo correctamente el juego";
            $icon_mensaje = 'success';
        }

        if (trim((string) $request->codigo) !== '') {
            $this->reemplazarCodigos($correoJuego, $request->codigo);
        }

        $cuentaNueva = $primariaPs4 + $primariaPs5 + $validatedData['secundaria'];
        if ($icon_mensaje == 'success' && $cuentaNueva == 0) {
            $notificacionExiste = Notificaciones::where('juego', $request->juego)
                ->where('tipo', 'crear_juego')
                ->first();

            if ($notificacionExiste) {
                $notificacionExiste->delete();
            }
        }

        return redirect()->route('correo-juegos.index', [
            'page' => $request->page,
            'search' => $request->search,
            'mensaje_correo_creado' => $mensaje,
            'icon_mensaje' => $icon_mensaje,
        ]);
    }

    private function reemplazarCodigos(CorreoJuego $correoJuego, string $texto): void
    {
        $codigos = CodigoVerificacion::separarCodigos($texto);
        $esMadreJugable = $correoJuego->es_cuenta_madre && $correoJuego->id_correo_madre;

        DB::transaction(function () use ($correoJuego, $codigos, $esMadreJugable) {
            CodigoVerificacion::where('id_correo_juego', $correoJuego->id)->delete();

            if ($esMadreJugable) {
                CodigoVerificacion::where('id_correo_madre', $correoJuego->id_correo_madre)->delete();
            }

            foreach ($codigos as $indice => $codigo) {
                CodigoVerificacion::create([
                    'codigo' => $codigo,
                    'id_correo_juego' => $esMadreJugable ? null : $correoJuego->id,
                    'id_correo_madre' => $esMadreJugable ? $correoJuego->id_correo_madre : null,
                    'respaldo' => $indice === 0 ? 1 : 0,
                ]);
            }
        });
    }

    private function licenciasSegunConsola(string $nombreJuego, int $primariaPs4, int $primariaPs5): array
    {
        $consola = strtoupper((string) Juego::query()
            ->where('nombre', trim($nombreJuego))
            ->value('consola'));
        $admitePs4 = str_contains($consola, 'PS4');
        $admitePs5 = str_contains($consola, 'PS5');

        if ($admitePs5 && ! $admitePs4) {
            $primariaPs4 = 2;
        } elseif ($admitePs4 && ! $admitePs5) {
            $primariaPs5 = 2;
        }

        return [$primariaPs4, $primariaPs5];
    }

    public function destroy(string $id)
    {
        $correoJuego = CorreoJuego::findOrFail($id);

        $correoMadre = $correoJuego->id_correo_madre
            ? CorreoMadre::find($correoJuego->id_correo_madre)
            : null;

        if ($correoMadre) {
            $correoMadre->registrarHijoHistorial($correoJuego, 'eliminado', request()->user()?->id);
            $saldoRestanteUSD = $correoJuego->precio_usd + $correoMadre->saldo_usd;
            $saldoRestanteCOP = $correoJuego->precio_cop + $correoMadre->saldo_cop;
            $correoMadre->update([
                'saldo_usd' => $saldoRestanteUSD,
                'saldo_cop' => $saldoRestanteCOP,
            ]);
            $correoMadre->sincronizarDisponibilidad();
        }

        $comparteGlobalMadre = $correoMadre
            && (int) $correoJuego->id_correo_globales === (int) $correoMadre->id_correo_globales;

        $correoGlobal = CorreoGlobales::find($correoJuego->id_correo_globales);
        $correoJuego->delete();
        if ($correoGlobal && ! $comparteGlobalMadre) {
            $correoGlobal->update(['disponible' => 1]);
        }
        CodigoVerificacion::where('id_correo_juego', $id)->delete();

        return redirect()->back();
    }

    public function consultarInventario(Request $request)
    {
        $filtros = array_merge([
            'search' => '',
            'disponibilidad' => '',
            'orden' => 'recientes',
        ], $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'disponibilidad' => ['nullable', 'in:con_cupos,sin_cupos,ps4,ps5,secundaria'],
            'orden' => ['nullable', 'in:recientes,antiguos,juego,costo'],
        ]));
        $filtros['search'] = trim((string) $filtros['search']);

        $base = CorreoJuego::query()
            ->where('disponible', 1)
            ->where('es_cuenta_madre', 0)
            ->when($filtros['search'] !== '', fn ($query) => $query->where('juego', 'like', "%{$filtros['search']}%"))
            ->when($filtros['disponibilidad'] === 'con_cupos', fn ($query) => $query->where(function ($subquery) {
                $subquery->where('primaria_ps4', '<', 2)->orWhere('primaria_ps5', '<', 2)->orWhere('secundaria', 0);
            }))
            ->when($filtros['disponibilidad'] === 'sin_cupos', fn ($query) => $query
                ->where('primaria_ps4', '>=', 2)->where('primaria_ps5', '>=', 2)->where('secundaria', '>', 0))
            ->when($filtros['disponibilidad'] === 'ps4', fn ($query) => $query->where('primaria_ps4', '<', 2))
            ->when($filtros['disponibilidad'] === 'ps5', fn ($query) => $query->where('primaria_ps5', '<', 2))
            ->when($filtros['disponibilidad'] === 'secundaria', fn ($query) => $query->where('secundaria', 0));

        $resumen = (clone $base)->selectRaw("COUNT(*) cuentas, COUNT(DISTINCT juego) juegos,
            SUM(GREATEST(0, 2 - primaria_ps4)) cupos_primaria_ps4,
            SUM(GREATEST(0, 2 - primaria_ps5)) cupos_primaria_ps5,
            SUM(CASE WHEN secundaria = 0 THEN 1 ELSE 0 END) cupos_secundaria,
            SUM(COALESCE(precio_cop, 0)) valor_inventario")->first();

        $puedeVerDatos = $request->user()->can('inventario.datos.completos');
        $columnas = ['id', 'juego', 'primaria_ps4', 'primaria_ps5', 'secundaria', 'precio_cop', 'created_at'];
        if ($puedeVerDatos) {
            array_push($columnas, 'correo', 'contrasena');
        }

        $query = (clone $base)->select($columnas);
        match ($filtros['orden']) {
            'antiguos' => $query->orderBy('created_at'),
            'juego' => $query->orderBy('juego'),
            'costo' => $query->orderByDesc('precio_cop'),
            default => $query->latest(),
        };

        $correos = $query->paginate(15)->appends($request->query());
        $imagenes = DB::table('juegos')->whereNotNull('url_imagen')->get(['nombre', 'url_imagen'])
            ->keyBy(fn ($juego) => mb_strtolower(trim($juego->nombre)));
        $correos->getCollection()->transform(function (CorreoJuego $correo) use ($imagenes) {
            $correo->setAttribute('cupos_primaria_ps4', max(0, 2 - (int) $correo->primaria_ps4));
            $correo->setAttribute('cupos_primaria_ps5', max(0, 2 - (int) $correo->primaria_ps5));
            $correo->setAttribute('cupo_secundaria', (int) $correo->secundaria === 0 ? 1 : 0);
            $correo->setAttribute('imagen', $imagenes->get(mb_strtolower(trim((string) $correo->juego)))?->url_imagen);

            return $correo;
        });

        return Inertia::render('CorreoJuego/Inventario', [
            'correos' => $correos,
            'filtros' => $filtros,
            'resumen' => [
                'cuentas' => (int) ($resumen->cuentas ?? 0),
                'juegos' => (int) ($resumen->juegos ?? 0),
                'cupos_primaria_ps4' => (int) ($resumen->cupos_primaria_ps4 ?? 0),
                'cupos_primaria_ps5' => (int) ($resumen->cupos_primaria_ps5 ?? 0),
                'cupos_secundaria' => (int) ($resumen->cupos_secundaria ?? 0),
                'valor_inventario' => (int) ($resumen->valor_inventario ?? 0),
            ],
            'puede_ver_datos_completos' => $puedeVerDatos,
        ]);
    }
}
