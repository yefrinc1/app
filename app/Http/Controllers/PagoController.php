<?php

namespace App\Http\Controllers;

use App\Models\Movimientos;
use App\Models\Pago;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PagoController extends Controller
{
    public function index()
    {

    }

    public function create()
    {
        $usuarios = DB::table('ventas')
        ->select('id_usuario')
        ->distinct()
        ->get();
    
        $ventasDesdeUltimoPago = [];
        
        foreach ($usuarios as $usuario) {
            // Último pago del usuario (puede ser null)
            $ultimoPago = DB::table('pagos')
                ->where('id_usuario', $usuario->id_usuario)
                ->orderByDesc('created_at')
                ->first();
        
            $fechaInicio = $ultimoPago ? $ultimoPago->created_at : '1900-01-01'; // Si no tiene pagos, contar todas las ventas
        
            $sumaVentas = DB::table('ventas')
                ->where('id_usuario', $usuario->id_usuario)
                ->where('created_at', '>=', $fechaInicio)
                ->sum('precio');

            $datosUsuario = User::findOrFail($usuario->id_usuario);
        
            $ventasDesdeUltimoPago[] = [
                'id_usuario' => $usuario->id_usuario,
                'nombre' => $datosUsuario->name,
                'fecha_ultimo_pago' => $ultimoPago?->created_at,
                'total_ventas' => (int) $sumaVentas,
                'porcentaje' => (int) ($sumaVentas * 0.05),
            ];
        }
    
        return Inertia::render('Pago/Create', ["ventas_ultimo_pago" => $ventasDesdeUltimoPago]);
    }

    public function store(Request $request)
    {
        Movimientos::create([
            'tipo' => 'Egreso',
            'descripcion' => 'Pago',
            'valor' => $request->valor,
            'observaciones' => "Pago para $request->nombre" ,
        ]);

        Pago::create([
            'id_usuario' => $request->id_usuario,
            'valor' => $request->valor,
        ]);

        return redirect()->back();
    }

    public function destroy(string $id)
    {
        
    }
}
