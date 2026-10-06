<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class Ventas extends Model
{
    use HasFactory;

    protected $table = 'ventas';
    protected $guarded = [];

    protected $casts = [
        'anulada_at' => 'datetime',
        'precio_bruto_pedido' => 'integer',
        'deduccion_liquidacion' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Ventas $venta) {
            if ($venta->pedido_detalle_id) {
                throw ValidationException::withMessages([
                    'venta' => 'Las ventas de pedidos no se eliminan. Utiliza la opción Anular venta.',
                ]);
            }
        });
    }

    public function correoJuego()
    {
        return $this->belongsTo(CorreoJuego::class, 'id_correo_juego');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function pedidoDetalle()
    {
        return $this->belongsTo(PedidoDetalle::class, 'pedido_detalle_id');
    }

    public function entrega()
    {
        return $this->hasOne(PedidoEntrega::class, 'venta_id');
    }

    public function anulacion()
    {
        return $this->hasOne(VentaAnulacion::class, 'venta_id');
    }

    public function evidenciasInstalacion()
    {
        return $this->hasMany(InstalacionEvidencia::class, 'venta_id');
    }

    public function ultimaEvidenciaInstalacion()
    {
        return $this->hasOne(InstalacionEvidencia::class, 'venta_id')->latestOfMany();
    }

    public function accesosPortal()
    {
        return $this->hasMany(PortalJuegoAcceso::class, 'venta_id');
    }
}
