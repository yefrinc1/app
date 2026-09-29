<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PagoLiquidacion extends Model
{
    use HasFactory;

    protected $table = 'pago_liquidaciones';

    protected $fillable = [
        'pedido_pago_id', 'valor_bruto', 'comision', 'retencion',
        'otros_descuentos', 'valor_neto', 'comprobante_path',
        'fecha_liquidacion', 'observaciones', 'registrado_por',
    ];

    protected $casts = [
        'valor_bruto' => 'decimal:2',
        'comision' => 'decimal:2',
        'retencion' => 'decimal:2',
        'otros_descuentos' => 'decimal:2',
        'valor_neto' => 'decimal:2',
        'fecha_liquidacion' => 'datetime',
    ];

    public function pago()
    {
        return $this->belongsTo(PedidoPago::class, 'pedido_pago_id');
    }
}
