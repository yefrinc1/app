<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstalacionEvidencia extends Model
{
    protected $table = 'instalacion_evidencias';

    protected $fillable = [
        'cliente_id', 'pedido_entrega_id', 'venta_id', 'tipo_archivo',
        'archivo_path', 'nombre_original', 'mime_type', 'tamano_bytes',
        'estado', 'declaracion_aceptada', 'enviado_at', 'revisado_por',
        'revisado_at', 'motivo_rechazo',
    ];

    protected $casts = [
        'declaracion_aceptada' => 'boolean',
        'enviado_at' => 'datetime',
        'revisado_at' => 'datetime',
        'tamano_bytes' => 'integer',
    ];

    protected $hidden = ['archivo_path'];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function pedidoEntrega()
    {
        return $this->belongsTo(PedidoEntrega::class);
    }

    public function venta()
    {
        return $this->belongsTo(Ventas::class, 'venta_id');
    }

    public function revisor()
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }
}
