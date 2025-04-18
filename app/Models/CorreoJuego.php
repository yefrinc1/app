<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorreoJuego extends Model
{
    use HasFactory;

    protected $table = 'correo_juegos';

    protected $guarded = [];

    public function ventas()
    {
        return $this->hasMany(Ventas::class, 'id_correo_juego');
    }

}
