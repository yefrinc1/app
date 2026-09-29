<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VentaAnulacion extends Model
{
    use HasFactory;

    protected $table = 'venta_anulaciones';
    protected $guarded = [];

    protected $casts = [
        'inventario_liberado' => 'boolean',
        'datos_inventario' => 'array',
    ];

    public function venta()
    {
        return $this->belongsTo(Ventas::class, 'venta_id');
    }

    public function entrega()
    {
        return $this->belongsTo(PedidoEntrega::class, 'pedido_entrega_id');
    }
}
