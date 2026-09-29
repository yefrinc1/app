<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PedidoEntrega extends Model
{
    use HasFactory;

    protected $table = 'pedido_entregas';

    protected $fillable = [
        'pedido_detalle_id', 'venta_id', 'correo_juego_id',
        'codigo_verificacion_id', 'estado', 'asignado_por',
        'asignado_at', 'entregado_at', 'observaciones',
        'anulada_at', 'anulada_por', 'motivo_anulacion', 'inventario_liberado',
    ];

    protected $casts = [
        'asignado_at' => 'datetime',
        'entregado_at' => 'datetime',
        'anulada_at' => 'datetime',
        'inventario_liberado' => 'boolean',
    ];

    public function detalle()
    {
        return $this->belongsTo(PedidoDetalle::class, 'pedido_detalle_id');
    }

    public function venta()
    {
        return $this->belongsTo(Ventas::class, 'venta_id');
    }

    public function correoJuego()
    {
        return $this->belongsTo(CorreoJuego::class, 'correo_juego_id');
    }

    public function codigoVerificacion()
    {
        return $this->belongsTo(CodigoVerificacion::class, 'codigo_verificacion_id');
    }

    public function anulacion()
    {
        return $this->hasOne(VentaAnulacion::class, 'pedido_entrega_id');
    }

    public function resolucion()
    {
        return $this->hasOne(PedidoAnulacionResolucion::class, 'pedido_entrega_id');
    }

    public function reembolsoResolucion()
    {
        return $this->hasOne(PedidoReembolso::class, 'pedido_entrega_anulada_id')
            ->whereIn('estado', ['pendiente', 'aprobado']);
    }
}
