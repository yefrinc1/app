<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pedido extends Model
{
    use HasFactory;

    public const ESTADOS = ['borrador', 'pendiente', 'en_proceso', 'completado', 'cancelado', 'reembolsado'];
    public const ESTADOS_PAGO = ['pendiente', 'parcial', 'pagado'];
    public const ESTADOS_FINANCIEROS = ['pendiente', 'pago_parcial', 'pagado', 'reembolso_parcial', 'reembolsado'];
    public const ESTADOS_ENTREGA = ['pendiente', 'parcial', 'completado'];

    protected $fillable = [
        'codigo', 'cliente_id', 'canal_venta', 'moneda', 'subtotal',
        'descuento', 'total', 'estado_pago', 'estado_financiero', 'estado_entrega', 'estado',
        'observaciones', 'creado_por', 'completado_por', 'completado_at',
        'cancelado_por', 'cancelado_at', 'motivo_cancelacion',
        'inventario_intentado_at', 'reembolsado_por', 'reembolsado_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'descuento' => 'decimal:2',
        'total' => 'decimal:2',
        'completado_at' => 'datetime',
        'cancelado_at' => 'datetime',
        'inventario_intentado_at' => 'datetime',
        'reembolsado_at' => 'datetime',
    ];

    protected $appends = ['total_pagado_bruto', 'total_pagado', 'total_reembolsado', 'saldo_pendiente', 'saldo_reembolsable'];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function detalles()
    {
        return $this->hasMany(PedidoDetalle::class);
    }

    public function pagos()
    {
        return $this->hasMany(PedidoPago::class);
    }

    public function historial()
    {
        return $this->hasMany(PedidoHistorial::class)->latest();
    }

    public function reembolsos()
    {
        return $this->hasMany(PedidoReembolso::class);
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function usuarioReembolso()
    {
        return $this->belongsTo(User::class, 'reembolsado_por');
    }

    public function getTotalPagadoAttribute(): float
    {
        return max(0, $this->total_pagado_bruto - $this->total_reembolsado);
    }

    public function getTotalPagadoBrutoAttribute(): float
    {
        return $this->relationLoaded('pagos')
            ? (float) $this->pagos->where('estado', 'aprobado')->sum('valor_bruto')
            : (float) $this->pagos()->where('estado', 'aprobado')->sum('valor_bruto');
    }

    public function getTotalReembolsadoAttribute(): float
    {
        if ($this->relationLoaded('reembolsos')) {
            return (float) $this->reembolsos->where('estado', 'aprobado')->sum('valor');
        }

        return (float) $this->reembolsos()->where('estado', 'aprobado')->sum('valor');
    }

    public function getSaldoPendienteAttribute(): float
    {
        return max(0, (float) $this->total - $this->total_pagado);
    }

    public function getSaldoReembolsableAttribute(): float
    {
        return max(0, $this->total_pagado_bruto - $this->total_reembolsado);
    }

    public function estaTotalmenteReembolsado(): bool
    {
        return $this->estado_financiero === 'reembolsado';
    }
}
