<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PedidoReembolso extends Model
{
    use HasFactory;

    protected $table = 'pedido_reembolsos';
    protected $guarded = [];

    protected $casts = [
        'valor' => 'decimal:2',
        'cantidad' => 'integer',
        'aprobado_at' => 'datetime',
        'rechazado_at' => 'datetime',
    ];

    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }

    public function pago()
    {
        return $this->belongsTo(PedidoPago::class, 'pedido_pago_id');
    }

    public function detalle()
    {
        return $this->belongsTo(PedidoDetalle::class, 'pedido_detalle_id');
    }

    public function entregaAnulada()
    {
        return $this->belongsTo(PedidoEntrega::class, 'pedido_entrega_anulada_id');
    }
}
