<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortalCodigoSolicitud extends Model
{
    protected $table = 'portal_codigo_solicitudes';

    protected $fillable = [
        'cliente_id', 'user_id', 'correo_juego_id', 'pedido_entrega_id', 'venta_id',
        'codigo_verificacion_id', 'codigo', 'ip', 'user_agent',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function correoJuego()
    {
        return $this->belongsTo(CorreoJuego::class, 'correo_juego_id');
    }
}
