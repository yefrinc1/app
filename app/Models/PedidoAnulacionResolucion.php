<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PedidoAnulacionResolucion extends Model
{
    use HasFactory;

    protected $table = 'pedido_anulacion_resoluciones';
    protected $guarded = [];

    protected $casts = [
        'cantidad' => 'integer',
        'valor_origen' => 'decimal:2',
        'resuelto_at' => 'datetime',
    ];

    public function entrega()
    {
        return $this->belongsTo(PedidoEntrega::class, 'pedido_entrega_id');
    }

    public function detalleOrigen()
    {
        return $this->belongsTo(PedidoDetalle::class, 'pedido_detalle_origen_id');
    }

    public function reemplazoDetalle()
    {
        return $this->belongsTo(PedidoDetalle::class, 'pedido_detalle_reemplazo_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'resuelto_por');
    }
}
