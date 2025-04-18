<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ventas extends Model
{
    use HasFactory;

    protected $table = 'ventas';

    protected $guarded = [];

    public function correoJuego()
    {
        return $this->belongsTo(CorreoJuego::class, 'id_correo_juego');
    }
}
