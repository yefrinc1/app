<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorreoMadre extends Model
{
    use HasFactory;

    protected $table = 'correo_madre';

    protected $guarded = [];

    public function correosJuegos()
    {
        return $this->hasMany(CorreoJuego::class, 'id_correo_madre');
    }
}
