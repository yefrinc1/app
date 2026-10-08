<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Services\ClienteUnificacionService;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ClienteUnificacionController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'can:clientes.ver', 'can:clientes.editar']);
    }

    public function index()
    {
        return Inertia::render('Clientes/Unificar', [
            'historial' => DB::table('cliente_unificaciones')->latest('id')->paginate(10),
        ]);
    }

    public function buscar(Request $request)
    {
        $datos = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        return response()->json(Cliente::query()->buscar($datos['q'])
            ->with('cuentaPortal:id,email')
            ->withCount(['pedidos', 'ventas'])
            ->latest('id')->limit(15)
            ->get(['id', 'nombre', 'codigo_pais', 'telefono', 'usuario', 'email', 'user_id', 'notas']));
    }

    public function previa(Request $request, ClienteUnificacionService $service)
    {
        $datos = $request->validate([
            'principal_id' => ['required', 'integer'],
            'duplicado_id' => ['required', 'integer', 'different:principal_id'],
        ]);
        return response()->json($service->vistaPrevia((int) $datos['principal_id'], (int) $datos['duplicado_id']));
    }

    public function store(Request $request, ClienteUnificacionService $service)
    {
        $datos = $request->validate([
            'principal_id' => ['required', 'integer'],
            'duplicado_id' => ['required', 'integer', 'different:principal_id'],
            'firma' => ['required', 'string', 'size:64'],
            'selecciones' => ['required', 'array:nombre,telefono,usuario,email'],
            'selecciones.nombre' => ['required', Rule::in(['principal', 'duplicado'])],
            'selecciones.telefono' => ['required', Rule::in(['principal', 'duplicado'])],
            'selecciones.usuario' => ['required', Rule::in(['principal', 'duplicado'])],
            'selecciones.email' => ['required', Rule::in(['principal', 'duplicado'])],
            'motivo' => ['required', 'string', 'min:5', 'max:1000'],
            'confirmado' => ['required', 'accepted'],
        ]);
        try {
            $id = $service->unificar($datos, $request->user()->id);
        } catch (QueryException $error) {
            report($error);
            throw ValidationException::withMessages([
                'clientes' => 'No se pudo unificar por una restricción de la base de datos. Se revirtieron todos los cambios. Revisa el registro de errores antes de volver a intentarlo.',
            ]);
        }
        return redirect()->route('clientes.show', $id)
            ->with('success', 'Clientes unificados. Sus compras y el acceso al portal quedaron reunidos.');
    }
}
