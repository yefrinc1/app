<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnularVentaPedidoRequest;
use App\Models\PedidoEntrega;
use App\Services\VentaAnulacionService;

class PedidoAnulacionController extends Controller
{
    public function __construct(private VentaAnulacionService $anulaciones)
    {
        $this->middleware('can:pedidos.anular');
    }

    public function store(AnularVentaPedidoRequest $request, PedidoEntrega $entrega)
    {
        $datos = $request->validated();
        $this->anulaciones->anular(
            $entrega,
            $datos['tipo'],
            $datos['motivo'],
            $request->user()->id,
        );

        return back()->with('success', 'Venta anulada y eliminada correctamente. Revisa la notificación del pedido para sincronizar Jumpseller y gestionar el reembolso.');
    }
}
