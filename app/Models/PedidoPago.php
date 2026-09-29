<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PedidoPago extends Model
{
    use HasFactory;

    protected $table = 'pedido_pagos';

    protected $fillable = [
        'pedido_id', 'metodo_pago', 'valor_bruto', 'moneda', 'fecha_pago',
        'referencia', 'referencia_normalizada', 'comprobante_path',
        'comprobante_hash', 'estado', 'observaciones',
        'registrado_por', 'aprobado_por', 'aprobado_at', 'rechazado_por',
        'rechazado_at', 'motivo_rechazo',
    ];

    protected $casts = [
        'valor_bruto' => 'decimal:2',
        'fecha_pago' => 'datetime',
        'aprobado_at' => 'datetime',
        'rechazado_at' => 'datetime',
    ];

    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }

    public function liquidaciones()
    {
        return $this->hasMany(PagoLiquidacion::class, 'pedido_pago_id');
    }

    public function registrador()
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function aprobador()
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }
}
