<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CodigoVerificacion extends Model
{
    use HasFactory;

    protected $table = 'codigo_verificacion';
    protected $guarded = [];

    public function correoJuego()
    {
        return $this->belongsTo(CorreoJuego::class, 'id_correo_juego');
    }

    public function entregas()
    {
        return $this->hasMany(PedidoEntrega::class, 'codigo_verificacion_id');
    }

    public static function separarCodigos($codigos): array
    {
        return array_values(array_filter(
            array_map('trim', explode("\n", $codigos)),
            fn ($value) => $value !== ''
        ));
    }
}
