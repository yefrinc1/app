<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortalJuegoAcceso extends Model
{
    protected $table = 'portal_juego_accesos';

    protected $fillable = [
        'cliente_id', 'user_id', 'pedido_entrega_id', 'venta_id',
        'accion', 'ip', 'user_agent',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function pedidoEntrega()
    {
        return $this->belongsTo(PedidoEntrega::class);
    }

    public function venta()
    {
        return $this->belongsTo(Ventas::class, 'venta_id');
    }
}
