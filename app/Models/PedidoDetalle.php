<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PedidoDetalle extends Model
{
    use HasFactory;

    protected $table = 'pedido_detalles';

    protected $fillable = [
        'pedido_id', 'juego', 'tipo_cuenta', 'consola', 'cantidad',
        'precio_unitario', 'descuento', 'subtotal', 'cantidad_generada',
        'inventario_intentado_at', 'estado', 'observaciones',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'cantidad_generada' => 'integer',
        'precio_unitario' => 'decimal:2',
        'descuento' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'inventario_intentado_at' => 'datetime',
    ];

    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }

    public function entregas()
    {
        return $this->hasMany(PedidoEntrega::class);
    }

    public function ventas()
    {
        return $this->hasMany(Ventas::class, 'pedido_detalle_id');
    }

    public function reembolsos()
    {
        return $this->hasMany(PedidoReembolso::class, 'pedido_detalle_id');
    }

    public function resolucionesOrigen()
    {
        return $this->hasMany(PedidoAnulacionResolucion::class, 'pedido_detalle_origen_id');
    }

    public function resolucionReemplazo()
    {
        return $this->hasOne(PedidoAnulacionResolucion::class, 'pedido_detalle_reemplazo_id');
    }

    public function cantidadReembolsadaAprobada(): int
    {
        return (int) $this->reembolsos()->where('estado', 'aprobado')->sum('cantidad');
    }

    public function getCantidadPendienteAttribute(): int
    {
        return max(0, $this->cantidad - $this->cantidad_generada);
    }
}
