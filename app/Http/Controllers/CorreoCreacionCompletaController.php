<?php

namespace App\Http\Controllers;

use App\Models\CodigoVerificacion;
use App\Models\CorreoGlobales;
use App\Models\CorreoJuego;
use App\Models\CorreoMadre;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CorreoCreacionCompletaController extends Controller
{
    public function __construct()
    {
        $this->middleware(['can:correos.madre', 'can:correos.juegos']);
    }

    public function create(Request $request)
    {
        return Inertia::render('CorreoJuego/CreacionCompleta', [
            'madres' => $this->serializarMadres($this->madresConCupo()->latest()->limit(40)->get()),
            'correosGlobales' => $this->correosGlobalesSugeridos(),
            'idUltimaMadre' => CorreoMadre::latest()->value('id') ?? 0,
            'mensaje' => $request->get('mensaje', ''),
        ]);
    }

    public function buscarMadres(Request $request)
    {
        $busqueda = trim((string) $request->get('q', ''));

        $madres = $this->madresConCupo()
            ->when($busqueda !== '', function ($query) use ($busqueda) {
                $query->where('correo', 'like', "%{$busqueda}%");
            })
            ->latest()
            ->limit(15)
            ->get();

        return response()->json($this->serializarMadres($madres));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'modo' => 'required|in:existente,nueva',
            'madre_id' => [
                'exclude_unless:modo,existente',
                'required',
                Rule::exists('correo_madre', 'id')->where('disponible', 1),
            ],
            'madre.id_correo_globales' => [
                'exclude_unless:modo,nueva',
                'required',
                Rule::exists('correo_globales', 'id')->where('disponible', 1),
                'unique:correo_madre,id_correo_globales',
            ],
            'madre.contrasena' => 'exclude_unless:modo,nueva|required|string|min:6',
            'madre.saldo_usd' => 'exclude_unless:modo,nueva|required|numeric',
            'madre.saldo_cop' => 'exclude_unless:modo,nueva|required|numeric',
            'madre.fecha_nacimiento' => 'exclude_unless:modo,nueva|required|date',
            'madre.codigo' => 'exclude_unless:modo,nueva|nullable|string',
            'hijos' => 'nullable|array|max:6',
            'hijos.*.id_correo_globales' => [
                'required',
                'distinct',
                Rule::exists('correo_globales', 'id')->where('disponible', 1),
            ],
            'hijos.*.contrasena' => 'required|string|min:6',
            'hijos.*.fecha_nacimiento' => 'required|date',
        ]);

        $hijos = collect($validated['hijos'] ?? []);

        if ($validated['modo'] === 'existente' && $hijos->isEmpty()) {
            throw ValidationException::withMessages([
                'hijos' => 'Agrega al menos un hijo para completar la madre.',
            ]);
        }

        $idsGlobales = $hijos->pluck('id_correo_globales');
        if ($validated['modo'] === 'nueva') {
            $idsGlobales->push($validated['madre']['id_correo_globales']);
        }

        if ($idsGlobales->filter()->unique()->count() !== $idsGlobales->filter()->count()) {
            throw ValidationException::withMessages([
                'hijos' => 'Cada correo debe ser distinto. Revisa las sugerencias repetidas.',
            ]);
        }

        $madre = DB::transaction(function () use ($validated, $hijos) {
            if ($validated['modo'] === 'nueva') {
                $globalMadre = CorreoGlobales::where('id', $validated['madre']['id_correo_globales'])
                    ->where('disponible', 1)
                    ->lockForUpdate()
                    ->first();

                if (! $globalMadre) {
                    throw ValidationException::withMessages([
                        'madre.id_correo_globales' => 'El correo de la madre ya no está disponible.',
                    ]);
                }

                $madre = CorreoMadre::create([
                    'correo' => $globalMadre->correo,
                    'contrasena' => $validated['madre']['contrasena'],
                    'id_correo_globales' => $globalMadre->id,
                    'saldo_usd' => $validated['madre']['saldo_usd'],
                    'saldo_cop' => $validated['madre']['saldo_cop'],
                    'fecha_nacimiento' => $validated['madre']['fecha_nacimiento'],
                ]);

                $globalMadre->update(['disponible' => 0]);
                $this->guardarCodigos($validated['madre']['codigo'] ?? null, [
                    'id_correo_madre' => $madre->id,
                ]);
            } else {
                $madre = CorreoMadre::where('id', $validated['madre_id'])
                    ->where('disponible', 1)
                    ->lockForUpdate()
                    ->first();

                if (! $madre) {
                    throw ValidationException::withMessages([
                        'madre_id' => 'Esta madre ya no está disponible.',
                    ]);
                }
            }

            $hijosActuales = CorreoJuego::where('id_correo_madre', $madre->id)->hijosReales()->lockForUpdate()->count();
            $cuposLibres = CorreoMadre::CUPO_HIJOS - $hijosActuales;

            if ($cuposLibres <= 0) {
                throw ValidationException::withMessages([
                    'madre_id' => 'Esta madre ya tiene 6 hijos.',
                ]);
            }

            if ($hijos->count() > $cuposLibres) {
                throw ValidationException::withMessages([
                    'hijos' => "Esta madre solo tiene {$cuposLibres} cupo(s) libre(s).",
                ]);
            }

            foreach ($hijos as $hijo) {
                $globalHijo = CorreoGlobales::where('id', $hijo['id_correo_globales'])
                    ->where('disponible', 1)
                    ->lockForUpdate()
                    ->first();

                if (! $globalHijo) {
                    throw ValidationException::withMessages([
                        'hijos' => 'Uno de los correos sugeridos ya no está disponible. Recarga la página.',
                    ]);
                }

                CorreoJuego::create([
                    'correo' => $globalHijo->correo,
                    'contrasena' => $hijo['contrasena'],
                    'id_correo_globales' => $globalHijo->id,
                    'id_correo_madre' => $madre->id,
                    'fecha_nacimiento' => $hijo['fecha_nacimiento'],
                ]);

                $globalHijo->update(['disponible' => 0]);
            }

            $madre->sincronizarDisponibilidad();

            return $madre->fresh();
        });

        $creados = $hijos->count();
        $mensaje = $validated['modo'] === 'nueva'
            ? "Se creó la madre {$madre->correo}".($creados ? " y {$creados} hijo(s)." : '.')
            : "Se agregaron {$creados} hijo(s) a {$madre->correo}.";

        return redirect()->route('correo-madre.show', [
            'correo_madre' => $madre->id,
            'mensaje_correo_creado' => $mensaje,
        ]);
    }

    private function madresConCupo()
    {
        return CorreoMadre::query()
            ->withCount(['correosJuegos as hijos_count' => fn ($query) => $query->hijosReales()])
            ->with(['correosJuegos' => function ($query) {
                $query->select('id', 'id_correo_madre', 'correo', 'contrasena', 'fecha_nacimiento', 'juego', 'es_cuenta_madre')
                    ->hijosReales()
                    ->latest();
            }])
            ->withExists('cuentaComoHijo')
            ->where('disponible', 1)
            ->whereRaw('(select count(*) from correo_juegos where correo_juegos.id_correo_madre = correo_madre.id and coalesce(correo_juegos.es_cuenta_madre, 0) = 0) < 6');
    }

    private function serializarMadres($madres)
    {
        return $madres->map(function (CorreoMadre $madre) {
            return [
                'id' => $madre->id,
                'correo' => $madre->correo,
                'contrasena' => $madre->contrasena,
                'saldo_usd' => $madre->saldo_usd,
                'hijos_count' => $madre->hijos_count,
                'usa_madre_como_hijo' => (bool) $madre->cuenta_como_hijo_exists,
                'hijos' => $madre->correosJuegos->map(function (CorreoJuego $hijo) {
                    return [
                        'id' => $hijo->id,
                        'correo' => $hijo->correo,
                        'contrasena' => $hijo->contrasena,
                        'fecha_nacimiento' => $hijo->fecha_nacimiento,
                        'juego' => $hijo->juego,
                    ];
                })->values(),
            ];
        })->values();
    }

    private function correosGlobalesSugeridos()
    {
        return CorreoGlobales::where('disponible', 1)
            ->orderByRaw('id_correo_principal is null')
            ->orderBy('id_correo_principal')
            ->orderBy('id')
            ->limit(80)
            ->get(['id', 'correo', 'id_correo_principal']);
    }

    private function guardarCodigos(?string $texto, array $atributos): void
    {
        if (trim((string) $texto) === '') {
            return;
        }

        $codigos = CodigoVerificacion::separarCodigos($texto);
        $codigoRespaldo = 1;

        foreach ($codigos as $clave => $codigo) {
            if ($clave > 0 && $codigoRespaldo == 1) {
                $codigoRespaldo = 0;
            }

            CodigoVerificacion::create([
                'codigo' => $codigo,
                'respaldo' => $codigoRespaldo,
                ...$atributos,
            ]);
        }
    }
}
