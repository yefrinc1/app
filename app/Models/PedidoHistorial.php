<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PedidoHistorial extends Model
{
    use HasFactory;

    protected $table = 'pedido_historial';

    protected $fillable = [
        'pedido_id', 'usuario_id', 'evento', 'descripcion',
        'datos_anteriores', 'datos_nuevos',
    ];

    protected $casts = [
        'datos_anteriores' => 'array',
        'datos_nuevos' => 'array',
    ];

    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class);
    }
}
